<?php

namespace App\Console\Commands;

use App\Models\Document;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Reasigna a los tipos nuevos del catálogo (Actas, Recibos; Convenios ya existía) los documentos
 * generales que documents:import-general guardó como "Otros" porque en su momento esos tipos no
 * existían.
 *
 * El importador no guarda el tipo original del CSV, así que aquí se deduce por la primera
 * palabra del nombre del documento. Es conservador a propósito: solo cambia los que empiezan
 * con ACTA, CONVENIO o RECIBO; el resto se lista para revisarlo a mano desde "Editar", porque
 * por ejemplo una escritura pública ("E.P. …") que protocoliza un acta de asamblea es una
 * decisión del usuario, no algo que se deba adivinar.
 *
 * Volver a correr el importador no sirve para esto: reconoce las carpetas ya importadas por su
 * referencia y las salta sin tocarles el tipo.
 */
class RetypeOtherGeneralDocuments extends Command
{
    protected $signature = 'documents:retype-otros
        {--dry-run : Muestra lo que se haría sin escribir en base de datos}';

    protected $description = 'Reasigna a Actas / Convenios / Recibos los documentos generales que quedaron como "Otros".';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $documents = Document::where('document_type', 'Otros')
            ->with('company:id,name')
            ->orderBy('id')
            ->get();

        if ($documents->isEmpty()) {
            $this->info('No hay documentos con tipo "Otros" — nada que reasignar.');

            return self::SUCCESS;
        }

        $changed   = 0;
        $unmatched = [];

        foreach ($documents as $document) {
            $label   = "#{$document->id} \"" . Str::limit($document->name, 80) . "\" ({$document->company?->name})";
            $newType = $this->inferType($document->name);

            if (! $newType) {
                $unmatched[] = $label;

                continue;
            }

            $this->line(($dryRun ? '[dry-run] ' : '') . "{$label} -> {$newType}");

            if (! $dryRun) {
                $document->update(['document_type' => $newType]);
            }

            $changed++;
        }

        $this->newLine();
        $this->info(($dryRun ? '[DRY RUN] ' : '') . "Reasignados: {$changed} de {$documents->count()}.");

        if (! empty($unmatched)) {
            $this->warn('Se quedan como "Otros" — revisarlos a mano desde "Editar" si les corresponde otro tipo:');
            foreach ($unmatched as $u) {
                $this->line("  - {$u}");
            }
        }

        return self::SUCCESS;
    }

    private function inferType(string $name): ?string
    {
        $name = Str::upper(Str::ascii(trim($name)));
        $firstWord = Str::before($name, ' ');

        if (in_array($firstWord, ['ACTA', 'ACTAS'], true)) {
            // Respeta los tipos de acta más específicos que ya existían en el catálogo.
            if (Str::contains($name, 'CONSTITUTIVA')) {
                return 'Acta constitutiva';
            }

            if (Str::contains($name, 'ASAMBLEA')) {
                return 'Acta de asamblea';
            }

            return 'Actas';
        }

        return match ($firstWord) {
            'CONVENIO', 'CONVENIOS' => 'Convenios',
            'RECIBO', 'RECIBOS'     => 'Recibos',
            default                 => null,
        };
    }
}
