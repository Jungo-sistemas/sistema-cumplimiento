<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Throwable;
use ZipArchive;

/**
 * Guarda la edición en línea de un .docx SUBIDO sin destruir su formato.
 *
 * Reconstruir el documento desde el HTML del editor (RegulationDocxBodyBuilder + PhpWord) pierde
 * todo lo que PhpWord no sabe pasar a HTML y de regreso: bordes y anchos de tabla, viñetas,
 * recuadros, líneas bajo los títulos, saltos de página, justificado… aunque no se haya tocado
 * nada. Aquí el .docx anterior es la guía: se empareja cada bloque del cuerpo (párrafo o tabla)
 * con el bloque del HTML editado por su texto, y
 *  - lo que no cambió se copia tal cual del .docx anterior (formato exacto);
 *  - un párrafo editado conserva su formato de párrafo (estilo, viñeta, sangría, bordes) y la
 *    fuente de su texto, y solo se le cambia el contenido;
 *  - en una tabla editada solo se cambian las filas/celdas tocadas; una fila nueva copia el
 *    formato de la fila vecina;
 *  - un párrafo nuevo toma el formato del párrafo de al lado (como al dar Enter en Word);
 *  - lo que no tiene de dónde copiar formato (una tabla o imagen nuevas, un título nuevo) se
 *    arma con PhpWord, como antes, solo ese bloque.
 * Las formas/cuadros de texto de Word que el editor no puede mostrar nunca se borran.
 *
 * Si el documento casi no se parece al HTML (p. ej. el cuerpo se reescribió por completo), regresa
 * null y quien llama reconstruye como siempre: un documento mal emparejado sería peor.
 */
class DocxLayoutPatcher
{
    private const W   = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    private const R   = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    private const A   = 'http://schemas.openxmlformats.org/drawingml/2006/main';
    private const REL = 'http://schemas.openxmlformats.org/package/2006/relationships';
    private const CT  = 'http://schemas.openxmlformats.org/package/2006/content-types';

    private const HEADER_REL_TYPE  = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/header';
    private const LINK_REL_TYPE    = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink';
    private const HEADER_CT        = 'application/vnd.openxmlformats-officedocument.wordprocessingml.header+xml';

    // Si menos de esta fracción de los bloques con texto del .docx anterior aparece igual en el
    // HTML, no se parcha (ver comentario de la clase).
    private const MIN_MATCH_RATIO = 0.6;

    // Tamaño máximo de la tabla de emparejamiento (LCS) por contenedor; arriba de esto el tramo
    // del medio se trata como reemplazado completo.
    private const MAX_LCS_CELLS = 400_000;

    private DOMDocument $doc;
    private DOMXPath $xp;
    private ZipArchive $zip;
    private DOMDocument $rels;
    private DOMDocument $types;
    private int $relSeq = 0;
    private int $mediaSeq = 0;
    private int $docPrSeq = 20000;
    /** @var array{matched: int, modified: int, inserted: int, deleted: int, rebuilt: int} */
    private array $stats;
    private int $oldWithText = 0;
    private int $oldMatchedWithText = 0;

    /**
     * @param  string  $baseDocx    .docx anterior (la guía).
     * @param  string  $html        HTML del editor, ya sanitizado (sanitizeHtmlForWord).
     * @param  ?string $headerDocx  .docx que solo trae el encabezado del sistema
     *                              (RegulationDocxHeaderBuilder); null = conservar el del documento.
     * @param  string  $outPath     dónde escribir el .docx resultante.
     * @return ?array{matched: int, modified: int, inserted: int, deleted: int, rebuilt: int}
     */
    public function patch(string $baseDocx, string $html, ?string $headerDocx, string $outPath): ?array
    {
        if (! copy($baseDocx, $outPath)) {
            return null;
        }

        try {
            $result = $this->run($html, $headerDocx, $outPath);
        } catch (Throwable $e) {
            report($e);
            $result = null;
        }

        if ($result === null) {
            @unlink($outPath);
        }

        return $result;
    }

    private function run(string $html, ?string $headerDocx, string $outPath): ?array
    {
        $this->stats = ['matched' => 0, 'modified' => 0, 'inserted' => 0, 'deleted' => 0, 'rebuilt' => 0];
        $this->oldWithText = $this->oldMatchedWithText = 0;

        $this->zip = new ZipArchive();
        if ($this->zip->open($outPath) !== true) {
            return null;
        }

        $documentXml = $this->zip->getFromName('word/document.xml');
        $relsXml = $this->zip->getFromName('word/_rels/document.xml.rels');
        $typesXml = $this->zip->getFromName('[Content_Types].xml');
        if ($documentXml === false || $relsXml === false || $typesXml === false) {
            $this->zip->close();

            return null;
        }

        $this->doc = $this->loadXml($documentXml);
        $this->rels = $this->loadXml($relsXml);
        $this->types = $this->loadXml($typesXml);
        $this->xp = $this->xpath($this->doc);

        $body = $this->xp->query('/w:document/w:body')->item(0);
        $newBlocks = $this->htmlBlocks($html);

        $this->patchContainer($body, $newBlocks);

        if ($this->oldWithText > 3 && $this->oldMatchedWithText / $this->oldWithText < self::MIN_MATCH_RATIO) {
            $this->zip->close();

            return null;
        }

        if ($headerDocx) {
            $this->replaceHeader($headerDocx);
        }

        $this->zip->addFromString('word/document.xml', $this->doc->saveXML());
        $this->zip->addFromString('word/_rels/document.xml.rels', $this->rels->saveXML());
        $this->zip->addFromString('[Content_Types].xml', $this->types->saveXML());
        $this->zip->close();

        return $this->stats;
    }

    // ── Emparejamiento ─────────────────────────────────────────────────────────────────────

    /**
     * Empareja los bloques (w:p / w:tbl) de un contenedor del .docx (el cuerpo o una celda) con
     * los bloques del HTML y lo deja con el contenido nuevo. Lo que no es bloque (propiedades de
     * sección o de celda, marcadores) se queda donde estaba.
     *
     * @param  list<array{kind: string, key: string, node: DOMNode}>  $new
     */
    private function patchContainer(DOMElement $container, array $new): void
    {
        $old = [];
        foreach ($this->childElements($container) as $node) {
            if (in_array($node->localName, ['p', 'tbl', 'sdt'], true)) {
                $old[] = ['node' => $node, 'key' => $this->docxKey($node), 'kind' => $node->localName];
            }
        }

        // El parecido entre documentos (MIN_MATCH_RATIO) se mide solo en el cuerpo.
        $top = $container->localName === 'body';
        if ($top) {
            foreach ($old as $o) {
                if ($this->hasText($o['key'])) {
                    $this->oldWithText++;
                }
            }
        }

        // Punto de inserción: lo nuevo se pone antes del siguiente bloque conservado, o antes de
        // las propiedades del contenedor que van al final (w:sectPr del cuerpo).
        $tailAnchor = null;
        foreach ($this->childElements($container) as $node) {
            if ($node->localName === 'sectPr') {
                $tailAnchor = $node;
            }
        }

        $ops = $this->diff(array_column($old, 'key'), array_column($new, 'key'));

        // Se arma la secuencia final en orden: cada op dice qué bloque viejo/nuevo va.
        $cursorBefore = null; // último nodo del .docx ya colocado
        $hunkOld = $hunkNew = [];

        $flush = function () use (&$hunkOld, &$hunkNew, &$cursorBefore, $old, $new, $container, $tailAnchor) {
            if (! $hunkOld && ! $hunkNew) {
                return;
            }
            $cursorBefore = $this->resolveHunk(
                array_map(fn ($i) => $old[$i], $hunkOld),
                array_map(fn ($j) => $new[$j], $hunkNew),
                $container,
                $cursorBefore,
                $tailAnchor
            );
            $hunkOld = $hunkNew = [];
        };

        foreach ($ops as [$op, $i, $j]) {
            if ($op === 'eq') {
                $flush();
                $this->stats['matched']++;
                if ($top && $this->hasText($old[$i]['key'])) {
                    $this->oldMatchedWithText++;
                }
                $cursorBefore = $old[$i]['node'];
                continue;
            }
            if ($op === 'del') {
                $hunkOld[] = $i;
            } else {
                $hunkNew[] = $j;
            }
        }
        $flush();

        // Una celda de tabla siempre tiene que terminar con un párrafo.
        if ($container->localName === 'tc') {
            $last = null;
            foreach ($this->childElements($container) as $node) {
                $last = $node;
            }
            if (! $last || $last->localName !== 'p') {
                $container->appendChild($this->doc->createElementNS(self::W, 'w:p'));
            }
        }
    }

    /**
     * Resuelve un tramo donde el .docx anterior y el HTML no coinciden. Regresa el último nodo
     * colocado (para saber dónde insertar lo que sigue).
     *
     * @param  list<array{node: DOMElement, key: string, kind: string}>  $old
     * @param  list<array{kind: string, key: string, node: DOMNode}>  $new
     */
    private function resolveHunk(array $old, array $new, DOMElement $container, ?DOMNode $cursor, ?DOMNode $tailAnchor): ?DOMNode
    {
        $j = 0;
        foreach ($old as $o) {
            // PhpWord parte en varios bloques del HTML un párrafo de Word que trae tabuladores o
            // saltos: si varios bloques seguidos juntos dan el mismo texto, el párrafo no cambió.
            $merged = $this->findMergedRun($o['key'], $new, $j);
            if ($merged !== null) {
                [$start, $end] = $merged;
                for (; $j < $start; $j++) {
                    $cursor = $this->insertNew($new[$j], $container, $cursor, $tailAnchor);
                }
                $j = $end + 1;
                $this->stats['matched']++;
                if ($container->localName === 'body' && $this->hasText($o['key'])) {
                    $this->oldMatchedWithText++;
                }
                $cursor = $o['node'];
                continue;
            }

            if ($j < count($new) && $this->compatible($o, $new[$j])) {
                $cursor = $this->modify($o, $new[$j], $container, $cursor, $tailAnchor);
                $j++;
                continue;
            }

            // Un bloque viejo sin pareja: si lo que sigue en el HTML es otra cosa (p. ej. una tabla
            // nueva antes de este párrafo), se inserta primero y se reintenta con este bloque.
            while ($j < count($new) && ! $this->compatible($o, $new[$j]) && $new[$j]['kind'] === 'table' && $o['kind'] !== 'tbl') {
                $cursor = $this->insertNew($new[$j], $container, $cursor, $tailAnchor);
                $j++;
            }
            if ($j < count($new) && $this->compatible($o, $new[$j])) {
                $cursor = $this->modify($o, $new[$j], $container, $cursor, $tailAnchor);
                $j++;
                continue;
            }

            $cursor = $this->deleteOld($o, $cursor);
        }

        for (; $j < count($new); $j++) {
            $cursor = $this->insertNew($new[$j], $container, $cursor, $tailAnchor);
        }

        return $cursor;
    }

    /** @return ?array{0: int, 1: int} */
    private function findMergedRun(string $oldKey, array $new, int $from): ?array
    {
        [$oldType, $oldText] = $this->splitKey($oldKey);
        if ($oldType !== 'P' || $oldText === '') {
            return null;
        }

        for ($start = $from; $start < count($new); $start++) {
            $acc = '';
            for ($end = $start; $end < count($new); $end++) {
                [$type, $text] = $this->splitKey($new[$end]['key']);
                if ($type !== 'P') {
                    break;
                }
                $acc .= $text;
                if ($acc === $oldText) {
                    return $end > $start ? [$start, $end] : null;
                }
                if (! str_starts_with($oldText, $acc)) {
                    break;
                }
            }
            // Solo se busca justo al inicio o después de bloques que se insertarán; no se salta
            // más de un par para no emparejar texto repetido lejano.
            if ($start - $from >= 2) {
                break;
            }
        }

        return null;
    }

    private function compatible(array $old, array $new): bool
    {
        // Las imágenes de un párrafo editado son las que ya tenía (se conservan sus dibujos): si
        // ahora trae más, hay una imagen nueva y ese bloque se arma con PhpWord (insertNew).
        if (count($this->htmlImages($new['node'])) > $this->xp->query('.//a:blip[not(ancestor::w:txbxContent)]', $old['node'])->length) {
            return false;
        }

        return match ($old['kind']) {
            'tbl'   => $new['kind'] === 'table',
            'p'     => in_array($new['kind'], ['p', 'li', 'h'], true) && ! $this->isProtected($old['node']),
            default => false,
        };
    }

    // ── Operaciones ────────────────────────────────────────────────────────────────────────

    private function modify(array $old, array $new, DOMElement $container, ?DOMNode $cursor, ?DOMNode $tailAnchor): DOMNode
    {
        $this->stats['modified']++;
        $node = $old['node'];

        if ($old['kind'] === 'tbl') {
            if (! $this->patchTable($node, $new['node'])) {
                // La estructura no se pudo seguir (columnas distintas): se arma esa tabla de nuevo.
                $replacement = $this->renderFallback($new['node']);
                $this->insertNodesAfter($replacement, $container, $cursor, $tailAnchor, $node);
                $container->removeChild($node);

                return end($replacement) ?: ($cursor ?? $container);
            }

            // Una tabla que se pudo seguir fila por fila es la misma tabla, editada.
            if ($container->localName === 'body') {
                $this->oldMatchedWithText++;
            }

            return $node;
        }

        $this->rewriteParagraph($node, $new);

        return $node;
    }

    private function insertNew(array $new, DOMElement $container, ?DOMNode $cursor, ?DOMNode $tailAnchor): ?DOMNode
    {
        $this->stats['inserted']++;

        $template = $new['kind'] === 'p' || $new['kind'] === 'li'
            ? $this->paragraphTemplate($container, $cursor, $new['kind'] === 'li')
            : null;

        if ($template && $this->htmlImages($new['node']) === []) {
            $p = $template->cloneNode(true);
            $this->rewriteParagraph($p, $new);
            $this->insertNodesAfter([$p], $container, $cursor, $tailAnchor);

            return $p;
        }

        $nodes = $this->renderFallback($new['node']);
        $this->insertNodesAfter($nodes, $container, $cursor, $tailAnchor);

        return $nodes ? end($nodes) : $cursor;
    }

    private function deleteOld(array $old, ?DOMNode $cursor): ?DOMNode
    {
        $node = $old['node'];

        // Formas, cuadros de texto y objetos de Word: el editor no los muestra, así que no hay
        // forma de que alguien los haya borrado a propósito.
        if ($this->isProtected($node) || $old['kind'] === 'sdt') {
            return $node;
        }

        // Un párrafo que cierra una sección (w:sectPr) no se puede quitar sin juntar secciones:
        // se vacía y se deja.
        if ($node->localName === 'p' && $this->xp->query('./w:pPr/w:sectPr', $node)->length) {
            foreach ($this->childElements($node) as $child) {
                if ($child->localName !== 'pPr') {
                    $node->removeChild($child);
                }
            }

            return $node;
        }

        $this->stats['deleted']++;
        $node->parentNode->removeChild($node);

        return $cursor;
    }

    /**
     * Tabla editada: se emparejan las filas por texto; las iguales se quedan, las editadas
     * cambian solo sus celdas y las nuevas copian la fila de al lado. Si una fila editada no
     * tiene el mismo número de celdas, regresa false (la tabla se arma de nuevo).
     */
    private function patchTable(DOMElement $tbl, DOMNode $htmlTable): bool
    {
        $oldRows = array_values(array_filter($this->childElements($tbl), fn ($n) => $n->localName === 'tr'));
        $newRows = [];
        foreach ($this->htmlRows($htmlTable) as $tr) {
            $newRows[] = $tr;
        }

        $oldKeys = array_map(fn ($tr) => $this->rowKey($this->docxCells($tr)), $oldRows);
        $newKeys = array_map(fn ($tr) => $this->rowKey($this->htmlCells($tr)), $newRows);

        // Primero se verifica que todo lo editado se pueda seguir, para no dejar la tabla a medias.
        $ops = $this->diff($oldKeys, $newKeys);
        $plan = [];
        $pendingOld = [];
        foreach ($ops as [$op, $i, $j]) {
            if ($op === 'eq') {
                $plan[] = ['keep', $i, $j];
            } elseif ($op === 'del') {
                $pendingOld[] = $i;
                $plan[] = ['del', $i, null];
            } else {
                // Se empareja con la fila borrada pendiente más cercana (fila editada).
                if ($pendingOld) {
                    $i2 = array_shift($pendingOld);
                    foreach ($plan as $k => $step) {
                        if ($step[0] === 'del' && $step[1] === $i2) {
                            $plan[$k] = ['mod', $i2, $j];
                            break;
                        }
                    }
                } else {
                    $plan[] = ['ins', null, $j];
                }
            }
            if ($op === 'eq') {
                $pendingOld = [];
            }
        }

        foreach ($plan as [$step, $i, $j]) {
            if ($step === 'mod' && count($this->docxCells($oldRows[$i])) !== count($this->htmlCells($newRows[$j]))) {
                return false;
            }
            if ($step === 'ins') {
                $template = $this->nearestRow($oldRows, $plan, $j);
                if (! $template || count($this->docxCells($template)) !== count($this->htmlCells($newRows[$j]))) {
                    return false;
                }
            }
        }

        $lastRow = null;
        foreach ($plan as [$step, $i, $j]) {
            if ($step === 'keep') {
                $lastRow = $oldRows[$i];
            } elseif ($step === 'del') {
                $tbl->removeChild($oldRows[$i]);
            } elseif ($step === 'mod') {
                $this->patchRow($oldRows[$i], $newRows[$j]);
                $lastRow = $oldRows[$i];
            } else {
                $row = $this->nearestRow($oldRows, $plan, $j)->cloneNode(true);
                $this->patchRow($row, $newRows[$j]);
                if ($lastRow && $lastRow->nextSibling) {
                    $tbl->insertBefore($row, $lastRow->nextSibling);
                } elseif ($lastRow) {
                    $tbl->appendChild($row);
                } else {
                    $first = null;
                    foreach ($this->childElements($tbl) as $child) {
                        if ($child->localName === 'tr') {
                            $first = $child;
                            break;
                        }
                    }
                    $first ? $tbl->insertBefore($row, $first) : $tbl->appendChild($row);
                }
                $lastRow = $row;
            }
        }

        return true;
    }

    private function nearestRow(array $oldRows, array $plan, int $newIndex): ?DOMElement
    {
        $before = $after = null;
        foreach ($plan as [$step, $i, $j]) {
            if ($i === null || $step === 'del') {
                continue;
            }
            if ($j !== null && $j < $newIndex) {
                $before = $oldRows[$i];
            } elseif ($j !== null && $j > $newIndex && ! $after) {
                $after = $oldRows[$i];
            }
        }

        // Sin vecina conservada (toda la tabla cambió): la última fila del .docx.
        return $before ?? $after ?? (end($oldRows) ?: null);
    }

    private function patchRow(DOMElement $tr, DOMNode $htmlRow): void
    {
        $cells = $this->docxCells($tr);
        $htmlCells = $this->htmlCells($htmlRow);

        foreach ($cells as $k => $tc) {
            $newBlocks = $this->htmlBlocksOf($htmlCells[$k]);
            if ($this->containerKey($tc) === implode('', array_map(fn ($b) => $this->splitKey($b['key'])[1], $newBlocks))) {
                continue;
            }
            $this->patchContainer($tc, $newBlocks);
        }
    }

    // ── Párrafos ───────────────────────────────────────────────────────────────────────────

    /**
     * Deja el párrafo con el contenido del bloque HTML: conserva w:pPr (estilo, viñeta, sangría,
     * bordes) y usa como fuente la del texto principal del párrafo; las imágenes/formas que ya
     * tenía se conservan.
     */
    private function rewriteParagraph(DOMElement $p, array $new): void
    {
        $baseRPr = $this->dominantRPr($p);
        $keep = [];
        $keepImages = $this->htmlImages($new['node']) !== [];

        foreach ($this->childElements($p) as $child) {
            if ($child->localName === 'pPr') {
                continue;
            }
            // Formas y objetos siempre se quedan; una imagen se queda si el bloque editado la
            // sigue trayendo (si ya no, la borraron en el editor).
            $isShape = $this->xp->query('.//w:txbxContent|.//wps:wsp|.//wpg:wgp|.//w:object', $child)->length > 0;
            $isImage = $this->xp->query('.//w:drawing|.//w:pict|.//mc:AlternateContent', $child)->length > 0;
            if ($isShape || ($isImage && $keepImages)) {
                $keep[] = $child;
                continue;
            }
            $p->removeChild($child);
        }

        $this->adjustListFormat($p, $new['kind'] === 'li');

        foreach ($this->inlineRuns($new['node'], $baseRPr, []) as $run) {
            $p->appendChild($run);
        }

        foreach ($keep as $node) {
            $p->insertBefore($node, $this->firstRunOf($p));
        }
    }

    private function firstRunOf(DOMElement $p): ?DOMNode
    {
        foreach ($this->childElements($p) as $child) {
            if ($child->localName !== 'pPr') {
                return $child;
            }
        }

        return null;
    }

    /**
     * Un párrafo que ahora es elemento de lista (<li> en el editor). Al revés no se quita la
     * viñeta: el HTML que PhpWord saca de un .docx trae las viñetas como <p> sueltos, así que un
     * <p> no quiere decir que alguien le haya quitado la viñeta.
     */
    private function adjustListFormat(DOMElement $p, bool $wantList): void
    {
        $pPr = $this->xp->query('./w:pPr', $p)->item(0);
        $isList = $pPr && ($this->xp->query('./w:numPr', $pPr)->length || $this->isListStyle($pPr));

        if (! $wantList || $isList) {
            return;
        }

        $list = $this->firstListParagraph();
        if ($list) {
            $newPPr = $this->xp->query('./w:pPr', $list)->item(0)->cloneNode(true);
            $pPr ? $p->replaceChild($newPPr, $pPr) : $p->insertBefore($newPPr, $p->firstChild);
        }
    }

    private function isListStyle(DOMElement $pPr): bool
    {
        $style = $this->xp->query('./w:pStyle/@w:val', $pPr)->item(0)?->nodeValue ?? '';

        return (bool) preg_match('/list|lista|vi[ñn]eta|bullet/i', $style);
    }

    private ?DOMElement $listParagraphCache = null;

    private function firstListParagraph(): ?DOMElement
    {
        return $this->listParagraphCache ??= (function () {
            foreach ($this->xp->query('//w:body//w:p[w:pPr/w:numPr or contains(translate(w:pPr/w:pStyle/@w:val, "LISTA", "lista"), "lista") or contains(w:pPr/w:pStyle/@w:val, "List")]') as $p) {
                return $p;
            }

            return null;
        })();
    }

    /**
     * Párrafo del que copia formato uno nuevo: el anterior con texto (como Enter en Word), sin
     * los bordes/viñeta de un título; si se pide lista, uno de lista.
     */
    private function paragraphTemplate(DOMElement $container, ?DOMNode $cursor, bool $list): ?DOMElement
    {
        $candidates = [];
        for ($n = $cursor; $n; $n = $n->previousSibling) {
            if ($n instanceof DOMElement && $n->localName === 'p' && $n->parentNode === $container) {
                $candidates[] = $n;
            }
        }
        $start = $cursor ? $cursor->nextSibling : $container->firstChild;
        for ($n = $start; $n; $n = $n->nextSibling) {
            if ($n instanceof DOMElement && $n->localName === 'p') {
                $candidates[] = $n;
            }
        }

        foreach ($candidates as $p) {
            $pPr = $this->xp->query('./w:pPr', $p)->item(0);
            $isList = $pPr && ($this->xp->query('./w:numPr', $pPr)->length || $this->isListStyle($pPr));
            $isTitle = $pPr && ($this->xp->query('./w:pBdr', $pPr)->length
                || preg_match('/heading|t[ií]tulo|ttulo/i', $this->xp->query('./w:pStyle/@w:val', $pPr)->item(0)?->nodeValue ?? ''));

            if ($list ? $isList : (! $isList && ! $isTitle && $this->hasText($this->docxKey($p)))) {
                return $this->emptyCopy($p);
            }
        }

        if ($list && ($any = $this->firstListParagraph())) {
            return $this->emptyCopy($any);
        }

        return $candidates ? $this->stripTitle($this->emptyCopy($candidates[0])) : null;
    }

    private function emptyCopy(DOMElement $p): DOMElement
    {
        $copy = $p->cloneNode(true);
        foreach ($this->childElements($copy) as $child) {
            if ($child->localName === 'pPr') {
                foreach ($this->xp->query('./w:sectPr', $child) as $sect) {
                    $child->removeChild($sect);
                }
                continue;
            }
            // Se deja una corrida vacía con la fuente del texto para que rewriteParagraph la tome.
            if ($child->localName === 'r' && $this->xp->query('./w:t', $child)->length && ! isset($keptRun)) {
                $keptRun = true;
                foreach ($this->xp->query('./w:t', $child) as $t) {
                    $t->nodeValue = '';
                }
                continue;
            }
            $copy->removeChild($child);
        }

        return $copy;
    }

    private function stripTitle(DOMElement $p): DOMElement
    {
        $pPr = $this->xp->query('./w:pPr', $p)->item(0);
        if ($pPr) {
            foreach ($this->xp->query('./w:pBdr|./w:numPr|./w:pStyle', $pPr) as $n) {
                $pPr->removeChild($n);
            }
        }

        return $p;
    }

    /** rPr de la corrida con más texto, sin negritas/cursivas/subrayado (esas las da el HTML). */
    private function dominantRPr(DOMElement $p): ?DOMElement
    {
        $best = null;
        $bestLen = -1;
        foreach ($this->xp->query('.//w:r[w:t][not(ancestor::w:txbxContent)]', $p) as $r) {
            $len = mb_strlen($r->textContent);
            if ($len > $bestLen) {
                $best = $r;
                $bestLen = $len;
            }
        }

        $rPr = $best ? $this->xp->query('./w:rPr', $best)->item(0) : null;
        if (! $rPr) {
            return null;
        }

        $rPr = $rPr->cloneNode(true);
        foreach ($this->xp->query('./w:b|./w:bCs|./w:i|./w:iCs|./w:u|./w:highlight|./w:shd|./w:rStyle', $rPr) as $n) {
            $rPr->removeChild($n);
        }

        return $rPr;
    }

    /**
     * Corridas de Word para el contenido en línea de un bloque HTML.
     *
     * @param  array{b?: bool, i?: bool, u?: bool, strike?: bool, color?: string}  $fmt
     * @return list<DOMElement>
     */
    private function inlineRuns(DOMNode $node, ?DOMElement $baseRPr, array $fmt): array
    {
        $runs = [];

        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                $text = preg_replace('/[ \t\r\n]+/u', ' ', $child->nodeValue);
                if ($text !== '') {
                    $runs[] = $this->textRun($text, $baseRPr, $fmt);
                }
                continue;
            }
            if (! $child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->nodeName);
            if (in_array($tag, ['ul', 'ol', 'table', 'img'], true)) {
                continue;
            }
            if ($tag === 'br') {
                $r = $this->doc->createElementNS(self::W, 'w:r');
                $r->appendChild($this->doc->createElementNS(self::W, 'w:br'));
                $runs[] = $r;
                continue;
            }

            $childFmt = $fmt;
            if (in_array($tag, ['strong', 'b'], true)) {
                $childFmt['b'] = true;
            } elseif (in_array($tag, ['em', 'i'], true)) {
                $childFmt['i'] = true;
            } elseif ($tag === 'u') {
                $childFmt['u'] = true;
            } elseif (in_array($tag, ['s', 'strike', 'del'], true)) {
                $childFmt['strike'] = true;
            }

            $style = strtolower($child->getAttribute('style'));
            if (preg_match('/font-weight\s*:\s*(bold|[6-9]00)/', $style)) {
                $childFmt['b'] = true;
            }
            if (preg_match('/font-style\s*:\s*italic/', $style)) {
                $childFmt['i'] = true;
            }
            if (str_contains($style, 'underline')) {
                $childFmt['u'] = true;
            }
            if (preg_match('/(?:^|;)\s*color\s*:\s*#([0-9a-f]{6}|[0-9a-f]{3})\b/', $style, $c)) {
                $childFmt['color'] = strtoupper(strlen($c[1]) === 3 ? preg_replace('/(.)/', '$1$1', $c[1]) : $c[1]);
            }

            // Menciones del editor (@persona / #documento): se ven igual que en el editor.
            if ($child->hasAttribute('data-type') && str_contains($child->getAttribute('data-type'), 'mention')) {
                $childFmt['b'] = true;
                $childFmt['color'] = '1D4ED8';
            }

            if ($tag === 'a' && preg_match('#^https?://#i', $child->getAttribute('href'))) {
                $link = $this->doc->createElementNS(self::W, 'w:hyperlink');
                $link->setAttributeNS(self::R, 'r:id', $this->addRelationship(self::LINK_REL_TYPE, $child->getAttribute('href'), external: true));
                foreach ($this->inlineRuns($child, $baseRPr, $childFmt + ['u' => true, 'color' => '1D4ED8']) as $r) {
                    $link->appendChild($r);
                }
                $runs[] = $link;
                continue;
            }

            // Párrafos dentro de un elemento de lista o celda: se separan con salto de línea.
            if ($tag === 'p' && $runs) {
                $r = $this->doc->createElementNS(self::W, 'w:r');
                $r->appendChild($this->doc->createElementNS(self::W, 'w:br'));
                $runs[] = $r;
            }

            array_push($runs, ...$this->inlineRuns($child, $baseRPr, $childFmt));
        }

        return $runs;
    }

    private function textRun(string $text, ?DOMElement $baseRPr, array $fmt): DOMElement
    {
        $r = $this->doc->createElementNS(self::W, 'w:r');
        $rPr = $baseRPr ? $baseRPr->cloneNode(true) : $this->doc->createElementNS(self::W, 'w:rPr');

        $set = function (string $name, ?string $val = null) use ($rPr) {
            foreach ($this->xp->query('./w:' . $name, $rPr) as $existing) {
                $rPr->removeChild($existing);
            }
            $el = $this->doc->createElementNS(self::W, 'w:' . $name);
            if ($val !== null) {
                $el->setAttributeNS(self::W, 'w:val', $val);
            }
            $rPr->appendChild($el);
        };

        // Orden del esquema de w:rPr: b, i, strike, color, u (Word es estricto con el orden).
        $ordered = [];
        foreach (['b', 'i', 'strike'] as $flag) {
            if (! empty($fmt[$flag])) {
                $ordered[$flag] = null;
            }
        }
        if (! empty($fmt['color'])) {
            $ordered['color'] = $fmt['color'];
        }
        if (! empty($fmt['u'])) {
            $ordered['u'] = 'single';
        }
        foreach ($ordered as $name => $val) {
            $set($name, $val);
        }
        $this->sortRPr($rPr);

        if ($rPr->hasChildNodes()) {
            $r->appendChild($rPr);
        }

        $t = $this->doc->createElementNS(self::W, 'w:t');
        $t->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
        $t->appendChild($this->doc->createTextNode($text));
        $r->appendChild($t);

        return $r;
    }

    // Orden de los hijos de w:rPr según el esquema de WordprocessingML.
    private const RPR_ORDER = ['rStyle', 'rFonts', 'b', 'bCs', 'i', 'iCs', 'caps', 'smallCaps', 'strike', 'dstrike',
        'outline', 'shadow', 'emboss', 'imprint', 'noProof', 'snapToGrid', 'vanish', 'webHidden', 'color', 'spacing',
        'w', 'kern', 'position', 'sz', 'szCs', 'highlight', 'u', 'effect', 'bdr', 'shd', 'fitText', 'vertAlign',
        'rtl', 'cs', 'em', 'lang', 'eastAsianLayout', 'specVanish', 'oMath'];

    private function sortRPr(DOMElement $rPr): void
    {
        $children = $this->childElements($rPr);
        usort($children, function ($a, $b) {
            $ia = array_search($a->localName, self::RPR_ORDER, true);
            $ib = array_search($b->localName, self::RPR_ORDER, true);

            return ($ia === false ? 999 : $ia) <=> ($ib === false ? 999 : $ib);
        });
        foreach ($children as $child) {
            $rPr->appendChild($child);
        }
    }

    // ── Bloques del HTML ───────────────────────────────────────────────────────────────────

    /** @return list<array{kind: string, key: string, node: DOMNode}> */
    private function htmlBlocks(string $html): array
    {
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8"><div id="__root">' . $html . '</div>', LIBXML_NOERROR | LIBXML_NOWARNING);

        return $this->htmlBlocksOf($dom->getElementById('__root'));
    }

    /** @return list<array{kind: string, key: string, node: DOMNode}> */
    private function htmlBlocksOf(DOMNode $parent): array
    {
        $blocks = [];
        $inline = null;

        foreach (iterator_to_array($parent->childNodes) as $child) {
            $tag = $child instanceof DOMElement ? strtolower($child->nodeName) : null;
            $isBlock = in_array($tag, ['p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'table', 'ul', 'ol', 'li', 'div', 'section',
                'article', 'blockquote', 'pre', 'tbody', 'thead'], true);

            if (! $isBlock) {
                // Texto suelto (p. ej. dentro de una celda sin <p>): se junta como un párrafo.
                if ($child->nodeType === XML_TEXT_NODE && trim($child->nodeValue) === '' && ! $inline) {
                    continue;
                }
                $inline ??= $child->ownerDocument->createElement('p');
                $inline->appendChild($child->cloneNode(true));
                continue;
            }

            if ($inline) {
                $blocks[] = $this->block('p', $inline);
                $inline = null;
            }

            if (in_array($tag, ['div', 'section', 'article', 'blockquote', 'ul', 'ol', 'tbody', 'thead'], true)) {
                array_push($blocks, ...$this->htmlBlocksOf($child));
            } elseif ($tag === 'li') {
                $blocks[] = $this->block('li', $child);
                foreach ($child->childNodes as $nested) {
                    if ($nested instanceof DOMElement && in_array(strtolower($nested->nodeName), ['ul', 'ol'], true)) {
                        array_push($blocks, ...$this->htmlBlocksOf($nested));
                    }
                }
            } elseif ($tag === 'table') {
                $blocks[] = $this->block('table', $child);
            } elseif ($tag[0] === 'h') {
                $blocks[] = $this->block('h', $child);
            } else {
                $blocks[] = $this->block('p', $child);
            }
        }

        if ($inline) {
            $blocks[] = $this->block('p', $inline);
        }

        return $blocks;
    }

    private function block(string $kind, DOMNode $node): array
    {
        $text = $kind === 'li' ? $this->htmlText($node, skipLists: true) : $this->htmlText($node);

        return ['kind' => $kind, 'key' => ($kind === 'table' ? 'T:' : 'P:') . $text, 'node' => $node];
    }

    private function htmlText(DOMNode $node, bool $skipLists = false): string
    {
        $out = '';
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                $out .= $child->nodeValue;
            } elseif ($child instanceof DOMElement) {
                $tag = strtolower($child->nodeName);
                if ($skipLists && in_array($tag, ['ul', 'ol'], true)) {
                    continue;
                }
                // Las imágenes se emparejan por posición, no por contenido: al pasar al editor se
                // recortan como las muestra Word (DocxImageGeometry) y sus bytes ya no son los del .docx.
                if ($tag === 'img') {
                    $out .= '[img]';
                    continue;
                }
                $out .= $this->htmlText($child, $skipLists);
            }
        }

        return $this->normalize($out);
    }

    private function htmlRows(DOMNode $table): array
    {
        $rows = [];
        foreach ($table->childNodes as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }
            $tag = strtolower($child->nodeName);
            if ($tag === 'tr') {
                $rows[] = $child;
            } elseif (in_array($tag, ['thead', 'tbody', 'tfoot'], true)) {
                array_push($rows, ...$this->htmlRows($child));
            }
        }

        return $rows;
    }

    private function htmlCells(DOMNode $tr): array
    {
        $cells = [];
        foreach ($tr->childNodes as $child) {
            if ($child instanceof DOMElement && in_array(strtolower($child->nodeName), ['td', 'th'], true)) {
                $cells[] = $child;
            }
        }

        return $cells;
    }

    /** @return list<DOMElement> las <img> del bloque (incluido él mismo si es una). */
    private function htmlImages(DOMNode $node): array
    {
        if (! $node instanceof DOMElement) {
            return [];
        }
        if (strtolower($node->nodeName) === 'img') {
            return [$node];
        }

        return iterator_to_array($node->getElementsByTagName('img'), false);
    }

    // ── Bloques del .docx ──────────────────────────────────────────────────────────────────

    private function docxKey(DOMElement $node): string
    {
        $prefix = $node->localName === 'tbl' ? 'T:' : 'P:';

        return $prefix . $this->containerKey($node);
    }

    /** Texto del bloque (sin cuadros de texto ni texto borrado) con marcas de las imágenes. */
    private function containerKey(DOMElement $node): string
    {
        $out = '';
        foreach ($this->xp->query('.//w:t[not(ancestor::w:txbxContent)][not(ancestor::w:del)]|.//a:blip[not(ancestor::w:txbxContent)]', $node) as $n) {
            if ($n->localName === 'blip') {
                $out .= '[img]';
            } else {
                $out .= $n->textContent;
            }
        }

        return $this->normalize($out);
    }

    private function docxCells(DOMElement $tr): array
    {
        $cells = [];
        foreach ($this->childElements($tr) as $child) {
            if ($child->localName !== 'tc') {
                continue;
            }
            // Celdas combinadas hacia abajo (w:vMerge sin "restart"): el HTML no las trae.
            $vMerge = $this->xp->query('./w:tcPr/w:vMerge', $child)->item(0);
            if ($vMerge && $vMerge->getAttributeNS(self::W, 'val') !== 'restart') {
                continue;
            }
            $cells[] = $child;
        }

        return $cells;
    }

    private function rowKey(array $cells): string
    {
        return implode('|', array_map(
            fn ($c) => $c instanceof DOMElement && $c->namespaceURI === self::W ? $this->containerKey($c) : $this->htmlText($c),
            $cells
        ));
    }

    private function isProtected(DOMElement $node): bool
    {
        return $this->xp->query('.//w:txbxContent|.//wps:wsp|.//wpg:wgp|.//w:object|.//v:shape[not(.//v:imagedata)]', $node)->length > 0;
    }

    // ── Bloques nuevos armados con PhpWord ──────────────────────────────────────────────────

    /**
     * Arma con PhpWord (igual que la reconstrucción normal) un bloque que no tiene de dónde
     * copiar formato, y trae sus nodos —y sus imágenes— a este documento.
     *
     * @return list<DOMElement>
     */
    private function renderFallback(DOMNode $htmlNode): array
    {
        $this->stats['rebuilt']++;

        $fragment = $htmlNode->ownerDocument->saveHTML($htmlNode);
        if (strtolower($htmlNode->nodeName) === 'li') {
            $fragment = '<p>• ' . $this->innerHtml($htmlNode) . '</p>';
        }

        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        app(RegulationDocxBodyBuilder::class)->apply($section, $fragment);

        $tmp = tempnam(sys_get_temp_dir(), 'docx_fragment_');
        IOFactory::createWriter($phpWord, 'Word2007')->save($tmp);

        $src = new ZipArchive();
        $src->open($tmp);
        $srcDoc = $this->loadXml($src->getFromName('word/document.xml'));
        $srcRels = $this->loadXml($src->getFromName('word/_rels/document.xml.rels') ?: '<Relationships xmlns="' . self::REL . '"/>');
        $srcXp = $this->xpath($srcDoc);

        $nodes = [];
        foreach ($srcXp->query('/w:document/w:body/*[not(self::w:sectPr)]') as $node) {
            $imported = $this->doc->importNode($node, true);
            $this->remapRelationships($imported, $srcRels, $src);
            $nodes[] = $imported;
        }

        $src->close();
        @unlink($tmp);

        return $nodes;
    }

    private function innerHtml(DOMNode $node): string
    {
        $html = '';
        foreach ($node->childNodes as $child) {
            $html .= $node->ownerDocument->saveHTML($child);
        }

        return $html;
    }

    /** Copia al documento las imágenes/ligas que usan los nodos importados y les cambia el r:id. */
    private function remapRelationships(DOMElement $node, DOMDocument $srcRels, ZipArchive $src): void
    {
        $srcRelXp = new DOMXPath($srcRels);
        $srcRelXp->registerNamespace('rel', self::REL);

        $xp = $this->xpath($node->ownerDocument);
        foreach ($xp->query('.//@r:id|.//@r:embed|.//@r:link', $node) as $attr) {
            $rel = $srcRelXp->query('//rel:Relationship[@Id="' . $attr->value . '"]')->item(0);
            if (! $rel) {
                continue;
            }

            $type = $rel->getAttribute('Type');
            $target = $rel->getAttribute('Target');

            if ($rel->getAttribute('TargetMode') === 'External') {
                $attr->value = $this->addRelationship($type, $target, external: true);
                continue;
            }

            $bytes = $src->getFromName('word/' . ltrim($target, '/'));
            if ($bytes === false) {
                continue;
            }
            $ext = strtolower(pathinfo($target, PATHINFO_EXTENSION));
            $newTarget = 'media/edit_' . uniqid() . '_' . (++$this->mediaSeq) . '.' . $ext;
            $this->zip->addFromString('word/' . $newTarget, $bytes);
            $this->ensureDefaultContentType($ext);
            $attr->value = $this->addRelationship($type, $newTarget);
        }

        // Ids de dibujo únicos dentro del documento.
        foreach ($xp->query('.//wp:docPr', $node) as $docPr) {
            $docPr->setAttribute('id', (string) (++$this->docPrSeq));
        }
    }

    // ── Encabezado ─────────────────────────────────────────────────────────────────────────

    /**
     * Pone el encabezado del sistema (versión, elaboró, aprobó, página X de Y) en lugar del que
     * traía el documento, en todas sus secciones. Pies de página y márgenes se quedan igual.
     */
    private function replaceHeader(string $headerDocx): void
    {
        $src = new ZipArchive();
        if ($src->open($headerDocx) !== true) {
            return;
        }

        $srcRels = $this->loadXml($src->getFromName('word/_rels/document.xml.rels'));
        $srcRelXp = new DOMXPath($srcRels);
        $srcRelXp->registerNamespace('rel', self::REL);
        $headerRel = $srcRelXp->query('//rel:Relationship[@Type="' . self::HEADER_REL_TYPE . '"]')->item(0);
        if (! $headerRel) {
            $src->close();

            return;
        }

        $srcPart = ltrim($headerRel->getAttribute('Target'), '/');
        $headerXml = $this->loadXml($src->getFromName('word/' . $srcPart));
        $partRels = $src->getFromName('word/_rels/' . basename($srcPart) . '.rels');

        $name = 'header_sys' . uniqid() . '.xml';
        if ($partRels !== false) {
            // Las imágenes del encabezado (el logo) se copian con nombre propio.
            $partRelsDoc = $this->loadXml($partRels);
            $prx = new DOMXPath($partRelsDoc);
            $prx->registerNamespace('rel', self::REL);
            foreach ($prx->query('//rel:Relationship[not(@TargetMode="External")]') as $rel) {
                $bytes = $src->getFromName('word/' . ltrim($rel->getAttribute('Target'), '/'));
                if ($bytes === false) {
                    continue;
                }
                $ext = strtolower(pathinfo($rel->getAttribute('Target'), PATHINFO_EXTENSION));
                $target = 'media/header_' . uniqid() . '_' . (++$this->mediaSeq) . '.' . $ext;
                $this->zip->addFromString('word/' . $target, $bytes);
                $this->ensureDefaultContentType($ext);
                $rel->setAttribute('Target', $target);
            }
            $this->zip->addFromString('word/_rels/' . $name . '.rels', $partRelsDoc->saveXML());
        }

        $this->zip->addFromString('word/' . $name, $headerXml->saveXML());
        $src->close();

        $override = $this->types->createElementNS(self::CT, 'Override');
        $override->setAttribute('PartName', '/word/' . $name);
        $override->setAttribute('ContentType', self::HEADER_CT);
        $this->types->documentElement->appendChild($override);

        $rid = $this->addRelationship(self::HEADER_REL_TYPE, $name);
        $evenAndOdd = str_contains((string) $this->zip->getFromName('word/settings.xml'), 'evenAndOddHeaders');

        foreach ($this->xp->query('//w:sectPr') as $sectPr) {
            foreach ($this->xp->query('./w:headerReference', $sectPr) as $ref) {
                $sectPr->removeChild($ref);
            }
            $types = ['default'];
            if ($this->xp->query('./w:titlePg', $sectPr)->length) {
                $types[] = 'first';
            }
            if ($evenAndOdd) {
                $types[] = 'even';
            }
            foreach (array_reverse($types) as $type) {
                $ref = $this->doc->createElementNS(self::W, 'w:headerReference');
                $ref->setAttributeNS(self::W, 'w:type', $type);
                $ref->setAttributeNS(self::R, 'r:id', $rid);
                $sectPr->insertBefore($ref, $sectPr->firstChild);
            }
        }
    }

    // ── Utilidades ─────────────────────────────────────────────────────────────────────────

    /**
     * Diferencia entre dos listas de llaves: recorta el principio y el final iguales y empareja el
     * medio con LCS.
     *
     * @return list<array{0: string, 1: ?int, 2: ?int}>  ['eq', i, j] | ['del', i, null] | ['ins', null, j]
     */
    private function diff(array $a, array $b): array
    {
        $n = count($a);
        $m = count($b);
        $pre = 0;
        while ($pre < $n && $pre < $m && $a[$pre] === $b[$pre]) {
            $pre++;
        }
        $suf = 0;
        while ($suf < $n - $pre && $suf < $m - $pre && $a[$n - 1 - $suf] === $b[$m - 1 - $suf]) {
            $suf++;
        }

        $ops = [];
        for ($k = 0; $k < $pre; $k++) {
            $ops[] = ['eq', $k, $k];
        }

        $ma = array_slice($a, $pre, $n - $pre - $suf);
        $mb = array_slice($b, $pre, $m - $pre - $suf);
        $p = count($ma);
        $q = count($mb);

        if ($p * $q > self::MAX_LCS_CELLS) {
            for ($i = 0; $i < $p; $i++) {
                $ops[] = ['del', $pre + $i, null];
            }
            for ($j = 0; $j < $q; $j++) {
                $ops[] = ['ins', null, $pre + $j];
            }
        } else {
            $L = array_fill(0, $p + 1, array_fill(0, $q + 1, 0));
            for ($i = $p - 1; $i >= 0; $i--) {
                for ($j = $q - 1; $j >= 0; $j--) {
                    $L[$i][$j] = $ma[$i] === $mb[$j] ? $L[$i + 1][$j + 1] + 1 : max($L[$i + 1][$j], $L[$i][$j + 1]);
                }
            }
            $i = $j = 0;
            while ($i < $p && $j < $q) {
                if ($ma[$i] === $mb[$j]) {
                    $ops[] = ['eq', $pre + $i++, $pre + $j++];
                } elseif ($L[$i + 1][$j] >= $L[$i][$j + 1]) {
                    $ops[] = ['del', $pre + $i++, null];
                } else {
                    $ops[] = ['ins', null, $pre + $j++];
                }
            }
            for (; $i < $p; $i++) {
                $ops[] = ['del', $pre + $i, null];
            }
            for (; $j < $q; $j++) {
                $ops[] = ['ins', null, $pre + $j];
            }
        }

        for ($k = $suf; $k > 0; $k--) {
            $ops[] = ['eq', $n - $k, $m - $k];
        }

        return $ops;
    }

    /** Sin espacios: el HTML separa celdas y tabuladores con espacios que el .docx no tiene. */
    private function normalize(string $text): string
    {
        return preg_replace('/[\s\x{00A0}\x{200B}\x{FEFF}]+/u', '', $text);
    }

    /** @return array{0: string, 1: string} */
    private function splitKey(string $key): array
    {
        return [substr($key, 0, 1), substr($key, 2)];
    }

    private function hasText(string $key): bool
    {
        return $this->splitKey($key)[1] !== '';
    }

    /** @param  list<DOMElement>  $nodes */
    private function insertNodesAfter(array $nodes, DOMElement $container, ?DOMNode $cursor, ?DOMNode $tailAnchor, ?DOMNode $before = null): void
    {
        $ref = $before ?? ($cursor ? $cursor->nextSibling : $this->firstBlockOrAnchor($container, $tailAnchor));
        // Nunca después de las propiedades de sección del cuerpo.
        if ($ref === null && $tailAnchor && $tailAnchor->parentNode === $container) {
            $ref = $tailAnchor;
        }
        foreach ($nodes as $node) {
            $ref ? $container->insertBefore($node, $ref) : $container->appendChild($node);
        }
    }

    private function firstBlockOrAnchor(DOMElement $container, ?DOMNode $tailAnchor): ?DOMNode
    {
        foreach ($this->childElements($container) as $child) {
            // En una celda, después de sus propiedades (w:tcPr).
            if (in_array($child->localName, ['tcPr', 'tblPr', 'trPr'], true)) {
                continue;
            }

            return $child;
        }

        return $tailAnchor;
    }

    /** @return list<DOMElement> */
    private function childElements(DOMNode $node): array
    {
        $out = [];
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $out[] = $child;
            }
        }

        return $out;
    }

    private function addRelationship(string $type, string $target, bool $external = false): string
    {
        $id = 'rIdEdit' . (++$this->relSeq);
        $rel = $this->rels->createElementNS(self::REL, 'Relationship');
        $rel->setAttribute('Id', $id);
        $rel->setAttribute('Type', $type);
        $rel->setAttribute('Target', $target);
        if ($external) {
            $rel->setAttribute('TargetMode', 'External');
        }
        $this->rels->documentElement->appendChild($rel);

        return $id;
    }

    private function ensureDefaultContentType(string $ext): void
    {
        $xp = new DOMXPath($this->types);
        $xp->registerNamespace('ct', self::CT);
        if ($xp->query('//ct:Default[translate(@Extension, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")="' . $ext . '"]')->length) {
            return;
        }

        $mime = match ($ext) {
            'png'         => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif'         => 'image/gif',
            'bmp'         => 'image/bmp',
            'emf'         => 'image/x-emf',
            'wmf'         => 'image/x-wmf',
            default       => 'application/octet-stream',
        };
        $default = $this->types->createElementNS(self::CT, 'Default');
        $default->setAttribute('Extension', $ext);
        $default->setAttribute('ContentType', $mime);
        $this->types->documentElement->insertBefore($default, $this->types->documentElement->firstChild);
    }

    private function loadXml(string $xml): DOMDocument
    {
        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = true;
        $dom->loadXML($xml, LIBXML_PARSEHUGE);

        return $dom;
    }

    private function xpath(DOMDocument $dom): DOMXPath
    {
        $xp = new DOMXPath($dom);
        $xp->registerNamespace('w', self::W);
        $xp->registerNamespace('r', self::R);
        $xp->registerNamespace('a', self::A);
        $xp->registerNamespace('wp', 'http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing');
        $xp->registerNamespace('wps', 'http://schemas.microsoft.com/office/word/2010/wordprocessingShape');
        $xp->registerNamespace('wpg', 'http://schemas.microsoft.com/office/word/2010/wordprocessingGroup');
        $xp->registerNamespace('mc', 'http://schemas.openxmlformats.org/markup-compatibility/2006');
        $xp->registerNamespace('v', 'urn:schemas-microsoft-com:vml');

        return $xp;
    }
}
