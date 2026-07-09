<?php

namespace Database\Seeders\Concerns;

use Illuminate\Support\Str;

trait GuessesRequirementSubtype
{
    /**
     * Infiere el subtipo de normativa (nom, dictamen, contrato, poliza, permiso...)
     * a partir del nombre del documento, para poder agruparlos al listarlos.
     */
    private function guessSubtype(string $documentName): string
    {
        $normalized = Str::of($documentName)->lower()->ascii()->value();

        return match (true) {
            (bool) preg_match('/\bnom\b|\bnom-/', $normalized) => 'nom',
            str_contains($normalized, 'dictamen') => 'dictamen',
            str_contains($normalized, 'contrato') => 'contrato',
            str_contains($normalized, 'poliza') || str_contains($normalized, 'seguro') => 'poliza',
            str_contains($normalized, 'licencia') => 'licencia',
            str_contains($normalized, 'permiso') => 'permiso',
            str_contains($normalized, 'aviso') => 'aviso',
            str_contains($normalized, 'estudio') || str_contains($normalized, 'evaluacion') || str_contains($normalized, 'manifestacion') => 'estudio',
            str_contains($normalized, 'fotografia') || str_contains($normalized, 'foto ') => 'fotografia',
            default => 'otro',
        };
    }
}
