<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\AssetRequirementController;
use App\Http\Controllers\RequirementTaskController;
use App\Http\Controllers\TaskDocumentController;
use App\Http\Controllers\ComplianceDashboardController;
use App\Http\Controllers\AssetRequirementDocumentController;
use App\Http\Controllers\AssetExtraDocumentController;
use App\Http\Controllers\RequirementAuditLogController;
use App\Http\Controllers\RequirementHistoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserInvitationController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DocumentVersionController;
use App\Http\Controllers\RegulationController;
use App\Http\Controllers\RegulationVersionController;
use App\Http\Controllers\RegulationApprovalController;
use App\Http\Controllers\JobPositionController;
use App\Http\Controllers\ApiTokenController;
use App\Http\Controllers\SuperAdminController;
use App\Http\Controllers\ProcessesDashboardController;
use App\Http\Controllers\DocumentTrashController;
use App\Http\Controllers\DocumentReportController;
use App\Http\Controllers\MyApprovalsController;
use App\Http\Controllers\ProcessReportController;
use App\Http\Controllers\RegulationShareController;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::get('/dashboard', [ComplianceDashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth', 'module.access'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Superadmin
    |--------------------------------------------------------------------------
    */
    Route::prefix('superadmin')->name('superadmin.')->group(function () {
        Route::get('/', [SuperAdminController::class, 'dashboard'])->name('dashboard');
        Route::get('/groups', [SuperAdminController::class, 'groups'])->name('groups');
        Route::post('/groups', [SuperAdminController::class, 'storeGroup'])->name('groups.store');
        Route::delete('/groups/{group}', [SuperAdminController::class, 'destroyGroup'])->name('groups.destroy');
        Route::patch('/groups/{group}/limit', [SuperAdminController::class, 'updateGroupLimit'])->name('groups.limit');
        Route::get('/companies', [SuperAdminController::class, 'companies'])->name('companies');
        Route::post('/companies', [SuperAdminController::class, 'storeCompany'])->name('companies.store');
        Route::delete('/companies/{company}', [SuperAdminController::class, 'destroyCompany'])->name('companies.destroy');
        Route::patch('/companies/{company}/limit', [SuperAdminController::class, 'updateCompanyLimit'])->name('companies.limit');
        Route::get('/users', [SuperAdminController::class, 'users'])->name('users');
        Route::post('/users', [SuperAdminController::class, 'storeUser'])->name('users.store');
        Route::patch('/users/{user}', [SuperAdminController::class, 'updateUser'])->name('users.update');
        Route::delete('/users/{user}', [SuperAdminController::class, 'destroyUser'])->name('users.destroy');

        // API Tokens
        Route::get('/api-tokens', [ApiTokenController::class, 'index'])->name('api-tokens.index');
        Route::post('/api-tokens', [ApiTokenController::class, 'store'])->name('api-tokens.store');
        Route::delete('/api-tokens/{apiToken}', [ApiTokenController::class, 'destroy'])->name('api-tokens.destroy');

    });

    /*
    |--------------------------------------------------------------------------
    | Documents 2
    |--------------------------------------------------------------------------
    */
    Route::get('/documents', [DocumentController::class, 'index'])
        ->name('documents.index');

    Route::get('/documents/folders/{folder}', [DocumentController::class, 'showFolder'])
        ->name('documents.folders.show');

    // Documents directly in a folder (general folders, no categories)
    Route::post('/documents/folders/{category}/documents', [DocumentController::class, 'store'])
        ->name('documents.folders.documents.store');

    Route::get('/documents/folders/{category}/documents/{document}', [DocumentVersionController::class, 'show'])
        ->name('documents.folder.document.show');

    Route::post('/documents/folders/{category}/documents/{document}/versions', [DocumentVersionController::class, 'store'])
        ->name('documents.folder.document.versions.store');

    Route::delete('/documents/folders/{category}/documents/{document}/versions/{version}', [DocumentVersionController::class, 'destroy'])
        ->name('document-versions.folder.destroy');

    // Legacy category routes (kept for backward compatibility)
    Route::get('/documents/categories/{category}', [DocumentController::class, 'showCategory'])
        ->name('documents.categories.show');

    Route::post('/documents/categories/{category}/documents', [DocumentController::class, 'store'])
        ->name('documents.categories.documents.store');

    Route::get('/documents/categories/{category}/documents/{document}', [DocumentVersionController::class, 'show'])
        ->name('documents.document.show');

    Route::post('/documents/categories/{category}/documents/{document}/versions', [DocumentVersionController::class, 'store'])
        ->name('documents.document.versions.store');

    Route::delete('/documents/categories/{category}/documents/{document}/versions/{version}', [DocumentVersionController::class, 'destroy'])
        ->name('document-versions.destroy');

    Route::get('/document-versions/{version}/preview', [DocumentVersionController::class, 'preview'])
        ->name('document-versions.preview');

    Route::get('/document-versions/{version}/download', [DocumentVersionController::class, 'download'])
        ->name('document-versions.download');

    // Delete document → move to trash
    Route::delete('/documents/folders/{folder}/documents/{document}', [DocumentController::class, 'destroy'])
        ->name('documents.folders.documents.destroy');

    // Weekly report (admin + operative)
    Route::get('/documents/report/weekly', [DocumentReportController::class, 'weeklyReport'])
        ->name('documents.report.weekly');


    // Trash (admin only)
    Route::get('/documents/trash', [DocumentTrashController::class, 'index'])
        ->name('documents.trash.index');
    Route::post('/documents/trash/{id}/restore', [DocumentTrashController::class, 'restore'])
        ->name('documents.trash.restore');
    Route::delete('/documents/trash/{id}', [DocumentTrashController::class, 'forceDestroy'])
        ->name('documents.trash.force-destroy');

    /*
    |--------------------------------------------------------------------------
    | Processes / Regulations
    |--------------------------------------------------------------------------
    */
    Route::get('/procesos', [ProcessesDashboardController::class, 'index'])
        ->name('processes.dashboard');

    Route::get('/my-approvals', [MyApprovalsController::class, 'index'])
        ->name('my-approvals.index');

    Route::get('/processes', [RegulationController::class, 'index'])
        ->name('processes.index');

    Route::get('/processes/create', [RegulationController::class, 'create'])
        ->name('processes.create');

    Route::get('/processes/cargar', [RegulationController::class, 'cargar'])
        ->name('processes.cargar');

    Route::post('/processes/cargar', [RegulationController::class, 'storeCargar'])
        ->name('processes.storeCargar');

    Route::get('/processes/obsoleto', [RegulationController::class, 'obsoleto'])
        ->name('processes.obsoleto');

    Route::post('/processes/report', [ProcessReportController::class, 'export'])
        ->name('processes.report');

    Route::get('/processes/search-annexes', [RegulationController::class, 'searchAnnexes'])
        ->name('processes.searchAnnexes');

    Route::post('/processes/preview', [RegulationController::class, 'previewGenerate'])
        ->name('processes.preview.generate');

    Route::get('/processes/preview', [RegulationController::class, 'previewShow'])
        ->name('processes.preview.show');

    Route::post('/processes/preview/revise', [RegulationController::class, 'previewRevise'])
        ->name('processes.preview.revise');

    Route::post('/processes/preview/cancel', [RegulationController::class, 'previewCancel'])
        ->name('processes.preview.cancel');

    Route::post('/processes/preview/confirm', [RegulationController::class, 'previewConfirm'])
        ->name('processes.preview.confirm');

    Route::get('/processes/{regulation}', [RegulationController::class, 'show'])
        ->name('processes.show');

    Route::get('/processes/{regulation}/edit', [RegulationController::class, 'edit'])
        ->name('processes.edit');

    Route::post('/processes/{regulation}/preview', [RegulationController::class, 'previewGenerateEdit'])
        ->name('processes.preview.generateEdit');

    Route::get('/processes/{regulation}/edit-basic', [RegulationController::class, 'editBasic'])
        ->name('processes.editBasic');

    Route::put('/processes/{regulation}/update-basic', [RegulationController::class, 'updateBasic'])
        ->name('processes.updateBasic');

    Route::patch('/processes/{regulation}/set-flow', [RegulationController::class, 'setFlow'])
        ->name('processes.setFlow');

    Route::get('/processes/{regulation}/flow', [RegulationController::class, 'flowView'])
        ->name('processes.flow');

    Route::get('/processes/{regulation}/print', [RegulationController::class, 'printView'])
        ->name('processes.print');

    Route::post('/processes/{regulation}/versions', [RegulationVersionController::class, 'store'])
        ->name('processes.versions.store');

    Route::get('/regulation-versions/{version}/preview', [RegulationVersionController::class, 'preview'])
        ->name('regulation-versions.preview');

    Route::get('/regulation-versions/{version}/download', [RegulationVersionController::class, 'download'])
        ->name('regulation-versions.download');

    Route::get('/regulation-versions/{version}/edit', [RegulationVersionController::class, 'editForm'])
        ->name('regulation-versions.edit');

    Route::post('/regulation-versions/{version}/edit', [RegulationVersionController::class, 'saveEdit'])
        ->name('regulation-versions.saveEdit');

    Route::post('/regulation-versions/{version}/draft', [RegulationVersionController::class, 'saveDraft'])
        ->name('regulation-versions.saveDraft');

    Route::delete('/regulation-versions/{version}/lock', [RegulationVersionController::class, 'releaseLock'])
        ->name('regulation-versions.releaseLock');

    Route::delete('/processes/{regulation}/versions/{version}', [RegulationVersionController::class, 'destroy'])
        ->name('regulation-versions.destroy');

    // Approval flow
    Route::post('/processes/{regulation}/approve', [RegulationApprovalController::class, 'approve'])
        ->name('processes.approve');
    Route::post('/processes/{regulation}/reject', [RegulationApprovalController::class, 'reject'])
        ->name('processes.reject');
    Route::post('/processes/{regulation}/resubmit', [RegulationApprovalController::class, 'resubmit'])
        ->name('processes.resubmit');

    Route::patch('/processes/{regulation}/annexes', [RegulationController::class, 'setAnnexes'])
        ->name('processes.setAnnexes');

    Route::post('/processes/{regulation}/share', [RegulationShareController::class, 'store'])
        ->name('processes.share');

    Route::get('/processes/{regulation}/view/{token}', [RegulationShareController::class, 'track'])
        ->name('processes.view-track');

    // Job positions (admin de grupo)
    Route::get('/settings/positions', [JobPositionController::class, 'index'])
        ->name('job-positions.index');
    Route::post('/settings/positions/assign', [JobPositionController::class, 'assignUser'])
        ->name('job-positions.assign');
    Route::delete('/settings/positions/remove', [JobPositionController::class, 'removeUser'])
        ->name('job-positions.remove');


    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Users
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::patch('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

    // Assets CRUD
    Route::resource('assets', AssetController::class);

    // Asset activation
    Route::patch('assets/{asset}/deactivate', [AssetController::class, 'deactivate'])->name('assets.deactivate');
    Route::patch('assets/{asset}/activate', [AssetController::class, 'activate'])->name('assets.activate');


    // Extra documentation (no afecta el cumplimiento, solo informativo)
    Route::get('assets/{asset}/extra-documents', [AssetExtraDocumentController::class, 'index'])
        ->name('assets.extra-documents.index');

    Route::post('assets/{asset}/extra-documents', [AssetExtraDocumentController::class, 'store'])
        ->name('assets.extra-documents.store');

    Route::get('assets/{asset}/extra-documents/{document}/preview', [AssetExtraDocumentController::class, 'preview'])
        ->name('assets.extra-documents.preview');

    Route::get('assets/{asset}/extra-documents/{document}/download', [AssetExtraDocumentController::class, 'download'])
        ->name('assets.extra-documents.download');

    Route::delete('assets/{asset}/extra-documents/{document}', [AssetExtraDocumentController::class, 'destroy'])
        ->name('assets.extra-documents.destroy');

    // Requirements (nested under asset)
    Route::get('assets/{asset}/requirements/{requirement}', [AssetRequirementController::class, 'show'])
        ->name('assets.requirements.show');

    Route::patch('assets/{asset}/requirements/{requirement}/complete', [AssetRequirementController::class, 'complete'])
        ->name('assets.requirements.complete');

    Route::patch('assets/{asset}/requirements/{requirement}/transit', [AssetRequirementController::class, 'markInTransit'])
        ->name('assets.requirements.transit');

    Route::patch('assets/{asset}/requirements/{requirement}/reopen', [AssetRequirementController::class, 'reopen'])
        ->name('assets.requirements.reopen');

    // Requirement official documents
    Route::get('assets/{asset}/requirements/{requirement}/documents', [AssetRequirementDocumentController::class, 'index'])
        ->name('assets.requirements.documents.index');

    Route::post('assets/{asset}/requirements/{requirement}/documents', [AssetRequirementDocumentController::class, 'store'])
        ->name('assets.requirements.documents.store');

    Route::get('assets/{asset}/requirements/{requirement}/documents/history', [AssetRequirementDocumentController::class, 'documentHistory'])
        ->name('assets.requirements.documents.history');

    Route::get('assets/{asset}/requirements/{requirement}/documents/{document}/preview', [AssetRequirementDocumentController::class, 'preview'])
        ->name('assets.requirements.documents.preview');

    Route::get('assets/{asset}/requirements/{requirement}/documents/{document}/download', [AssetRequirementDocumentController::class, 'download'])
        ->name('assets.requirements.documents.download');

    Route::delete('assets/{asset}/requirements/{requirement}/documents/{document}', [AssetRequirementDocumentController::class, 'destroy'])
        ->name('assets.requirements.documents.destroy');

    Route::post('assets/{asset}/requirements/{requirement}/renewal-task', [AssetRequirementDocumentController::class, 'storeRenewalTask'])
        ->name('assets.requirements.renewal-task');

    // Tasks for a requirement
    Route::get('requirements/{requirement}/tasks/create', [RequirementTaskController::class, 'create'])
        ->name('requirements.tasks.create');

    Route::post('requirements/{requirement}/tasks', [RequirementTaskController::class, 'store'])
        ->name('requirements.tasks.store');

    Route::get('requirements/{requirement}/tasks/{task}/edit', [RequirementTaskController::class, 'edit'])
        ->name('requirements.tasks.edit');

    Route::put('requirements/{requirement}/tasks/{task}', [RequirementTaskController::class, 'update'])
        ->name('requirements.tasks.update');

    Route::delete('requirements/{requirement}/tasks/{task}', [RequirementTaskController::class, 'destroy'])
        ->name('requirements.tasks.destroy');

    Route::patch('requirements/{requirement}/tasks/{task}/complete', [RequirementTaskController::class, 'complete'])
        ->name('requirements.tasks.complete');

    Route::patch('requirements/{requirement}/tasks/{task}/reopen', [RequirementTaskController::class, 'reopen'])
        ->name('requirements.tasks.reopen');

    Route::post('assets/{asset}/requirements/{requirement}/checkout', [RequirementTaskController::class, 'checkout'])
        ->name('assets.requirements.checkout');

    Route::get('requirements/{requirement}/tasks/{task}', [RequirementTaskController::class, 'show'])
        ->name('requirements.tasks.show');

    // Task documents
    Route::get('tasks/{task}/documents', [TaskDocumentController::class, 'index'])
        ->name('tasks.documents.index');

    Route::post('tasks/{task}/documents', [TaskDocumentController::class, 'store'])
        ->name('tasks.documents.store');

    Route::get('tasks/{task}/documents/{document}/preview', [TaskDocumentController::class, 'preview'])
        ->name('tasks.documents.preview');

    Route::get('documents/{document}/download', [TaskDocumentController::class, 'download'])
        ->name('documents.download');

    Route::delete('documents/{document}', [TaskDocumentController::class, 'destroy'])
        ->name('documents.destroy');

    // Audit / history
    Route::get(
        'assets/{asset}/requirements/{requirement}/audit-logs',
        [RequirementAuditLogController::class, 'index']
    )->name('assets.requirements.audit-logs');

    Route::get(
        'assets/{asset}/requirements/{requirement}/history',
        [RequirementHistoryController::class, 'index']
    )->name('assets.requirements.history');

    Route::get(
        'assets/{asset}/requirements/{requirement}/tasks/{task}/history',
        [RequirementHistoryController::class, 'task']
    )->name('assets.requirements.tasks.history');
});

Route::get('/invitation/{token}', [UserInvitationController::class, 'show'])
    ->name('invitation.accept');

Route::post('/invitation/{token}', [UserInvitationController::class, 'store'])
    ->name('invitation.store');

require __DIR__ . '/auth.php';