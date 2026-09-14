<?php

namespace App\Services;

/**
 * Agrega la barra de título azul marino sobre el diagrama de flujo ya renderizado — igual que
 * el documento_ejemplo.docx trae "DIAGRAMA DE FLUJO — CÓDIGO NOMBRE (N pasos)" como banner fijo
 * encima del diagrama. Se compone aquí con GD (fuera del control de la IA) por la misma razón
 * que el encabezado del documento se construye en PHP: ni el modelo ni el SVG generado pueden
 * garantizar el mismo banner exacto en cada generación.
 */
class DiagramTitleBarComposer
{
    private const BAR_COLOR = [0x1F, 0x38, 0x64];
    private const TEXT_COLOR = [0xFF, 0xFF, 0xFF];
    private const BAR_HEIGHT = 46;

    /**
     * @param  int  $scale  Factor de escala con el que se renderizó $png (AiProcedureGenerationService::
     *                      DIAGRAM_RENDER_SCALE). La barra y su texto se dibujan a ese mismo factor
     *                      para no quedar desproporcionadamente delgados/pequeños frente a un
     *                      diagrama capturado en alta resolución.
     * @return string  PNG resultante (con la barra ya compuesta). Si GD no está disponible, o el
     *                 PNG de entrada no es válido, devuelve $png sin modificar en vez de fallar.
     */
    public function addTitleBar(string $png, string $title, int $scale = 1): string
    {
        if (! extension_loaded('gd')) {
            return $png;
        }

        $diagram = @imagecreatefromstring($png);
        if ($diagram === false) {
            return $png;
        }

        $barHeight = self::BAR_HEIGHT * $scale;

        $width = imagesx($diagram);
        $height = imagesy($diagram);

        $canvas = imagecreatetruecolor($width, $height + $barHeight);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $white);

        $barColor = imagecolorallocate($canvas, ...self::BAR_COLOR);
        imagefilledrectangle($canvas, 0, 0, $width - 1, $barHeight - 1, $barColor);

        imagecopy($canvas, $diagram, 0, $barHeight, 0, 0, $width, $height);
        imagedestroy($diagram);

        $this->drawCenteredTitle($canvas, $title, $width, $barHeight, $scale);

        ob_start();
        imagepng($canvas);
        $result = ob_get_clean();
        imagedestroy($canvas);

        return $result !== false ? $result : $png;
    }

    private function drawCenteredTitle($canvas, string $title, int $width, int $barHeight, int $scale): void
    {
        $title = mb_strtoupper($title, 'UTF-8');
        $textColor = imagecolorallocate($canvas, ...self::TEXT_COLOR);
        $font = $this->findBoldFont();
        $margin = 40 * $scale;

        if ($font !== null) {
            $size = 13 * $scale;
            $minSize = 7 * $scale;
            // imagettfbbox no soporta bien texto muy largo centrado a un tamaño fijo — se reduce
            // el tamaño de fuente hasta que quepa en el ancho del diagrama, con margen a los lados.
            do {
                $box = imagettfbbox($size, 0, $font, $title);
                $textWidth = abs($box[2] - $box[0]);
                $size--;
            } while ($textWidth > $width - $margin && $size > $minSize);

            $box = imagettfbbox($size + 1, 0, $font, $title);
            $textWidth = abs($box[2] - $box[0]);
            $textHeight = abs($box[7] - $box[1]);
            $x = max(20 * $scale, (int) (($width - $textWidth) / 2));
            $y = (int) (($barHeight + $textHeight) / 2);

            imagettftext($canvas, $size + 1, 0, $x, $y, $textColor, $font, $title);

            return;
        }

        // Sin fuente TTF disponible (ej. Linux sin fontconfig): fuente bitmap de GD como respaldo
        // (tamaño fijo de GD — no escala, pero es solo el caso sin fuentes TrueType instaladas).
        $charWidth = imagefontwidth(5);
        $x = max(10, (int) (($width - strlen($title) * $charWidth) / 2));
        $y = (int) (($barHeight - imagefontheight(5)) / 2);
        imagestring($canvas, 5, $x, $y, $title, $textColor);
    }

    /**
     * Si hay una fuente TTF bold disponible para el texto de la barra — sin ella, la barra se
     * dibuja igual pero con la fuente de mapa de bits de GD (más tosca). Solo para diagnóstico.
     */
    public function hasBoldFont(): bool
    {
        return $this->findBoldFont() !== null;
    }

    private function findBoldFont(): ?string
    {
        foreach ([
            'C:/Windows/Fonts/arialbd.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
        ] as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }
}
