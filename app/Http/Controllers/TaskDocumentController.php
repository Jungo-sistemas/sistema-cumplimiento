<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TaskDocumentController extends Controller
{
    public function index(Task $task, Request $request)
    {
        $task->loadMissing([
            'requirement.company',
            'requirement.asset',
            'requirement.template',
        ]);

        abort_if(! $request->user()->canAccessCompany($task->requirement->company), 403);

        $task->load([
            'documents.uploader',
            'users',
        ]);

        $requirement = $task->requirement;
        $asset = $requirement->asset;

        $navContext = [
            'asset' => $asset,
            'requirement' => $requirement,
            'task' => $task,
            'documentSection' => true,
            'documentOwner' => 'task',
        ];

        return view('tasks.documents', compact(
            'task',
            'requirement',
            'asset',
            'navContext'
        ));
    }

    public function store(Task $task, Request $request)
    {
        $task->loadMissing('requirement.company');

        abort_if(! $request->user()->canAccessCompany($task->requirement->company), 403);
        abort_unless($request->user()->isAdmin() || $request->user()->isOperative(), 403);

        if ($task->type === Task::TYPE_RENEWAL) {
            return back()->withErrors([
                'file' => 'Las tareas de renovación deben gestionarse desde la documentación oficial.',
            ]);
        }

        $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'],
        ]);

        $existing = TaskDocument::where('task_id', $task->id)->latest()->first();

        if ($existing) {
            Storage::disk('public')->delete($existing->file_path);
            $existing->delete();
        }

        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();

        $path = $file->storeAs(
            "task-documents/{$task->id}",
            $originalName,
            'public'
        );

        TaskDocument::create([
            'task_id' => $task->id,
            'file_path' => $path,
            'uploaded_by' => $request->user()->id,
        ]);

        return back()->with('status', 'Documento subido.');
    }

    public function download(TaskDocument $document, Request $request)
    {
        $document->load('task.requirement.company');

        abort_if(! $request->user()->canAccessCompany($document->task->requirement->company), 403);

        return Storage::disk('public')->download($document->file_path);
    }

    public function destroy(TaskDocument $document, Request $request)
    {
        $document->load('task.requirement.company');

        abort_if(! $request->user()->canAccessCompany($document->task->requirement->company), 403);
        abort_if(! ($request->user()->isAdmin() || $request->user()->isOperative()), 403);

        Storage::disk('public')->delete($document->file_path);
        $document->delete();

        return back()->with('status', 'Documento eliminado.');
    }

    public function preview(Task $task, TaskDocument $document)
    {
        abort_unless($document->task_id === $task->id, 404);

        $task->loadMissing('requirement.company');

        abort_unless(
            $task->requirement?->company
            && auth()->user()->canAccessCompany($task->requirement->company),
            403
        );

        $path = $document->file_path;

        abort_unless(Storage::disk('public')->exists($path), 404);

        $mime = Storage::disk('public')->mimeType($path) ?? 'application/octet-stream';

        $allowed = [
            'application/pdf',
            'image/png',
            'image/jpeg',
            'image/jpg',
            'image/webp',
            'image/gif',
        ];

        if (! in_array($mime, $allowed, true)) {
            return back()->withErrors([
                'preview' => 'Este tipo de archivo no se puede previsualizar. Descárgalo en su lugar.',
            ]);
        }

        return response()->file(Storage::disk('public')->path($path), [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="' . basename($path) . '"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}