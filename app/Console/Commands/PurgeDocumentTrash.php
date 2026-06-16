<?php

namespace App\Console\Commands;

use App\Models\Document;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PurgeDocumentTrash extends Command
{
    protected $signature = 'documents:purge-trash';

    protected $description = 'Elimina permanentemente los documentos en papelera cuyo periodo de 2 meses ha vencido';

    public function handle(): void
    {
        $disk = Storage::disk('private');

        $expired = Document::onlyTrashed()
            ->whereNotNull('permanently_delete_at')
            ->where('permanently_delete_at', '<=', now())
            ->with('versions')
            ->get();

        if ($expired->isEmpty()) {
            $this->info('No hay documentos para purgar.');
            return;
        }

        $count = 0;

        foreach ($expired as $document) {
            DB::transaction(function () use ($document, $disk, &$count) {
                foreach ($document->versions as $version) {
                    if ($disk->exists($version->file_path)) {
                        $disk->delete($version->file_path);
                    }
                    $version->delete();
                }

                $document->forceDelete();
                $count++;
            });
        }

        $this->info("Purgados {$count} documento(s) de la papelera.");
    }
}
