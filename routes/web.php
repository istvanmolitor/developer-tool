<?php

use App\Http\Controllers\AgentContextController;
use App\Http\Controllers\AgentController;
use App\Http\Controllers\AllTasksController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentationController;
use App\Http\Controllers\IncomingEmailController;
use App\Http\Controllers\MailboxController;
use App\Http\Controllers\OutgoingEmailController;
use App\Http\Controllers\ProjectAttachmentController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\PromptRunController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\TaskAttachmentController;
use App\Http\Controllers\TaskChangeController;
use App\Http\Controllers\TaskCommentController;
use App\Http\Controllers\TaskController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // Every task across all projects: filterable, sortable, paginated.
    Route::get('tasks', [AllTasksController::class, 'index'])->name('tasks.index');

    // Global Elasticsearch search over tasks, docs, prompts, messages, mails and comments.
    Route::get('search', [SearchController::class, 'index'])->name('search.index');
    Route::get('search/suggest', [SearchController::class, 'suggest'])->name('search.suggest');

    // Application-wide settings (key-value store).
    Route::get('app-settings', [SettingController::class, 'edit'])->name('app-settings.edit');
    Route::patch('app-settings', [SettingController::class, 'update'])->name('app-settings.update');

    // The prompt queue: the runs still waiting to execute, the global pause
    // switch that holds them, and the control to clear them out.
    Route::get('prompt-runs', [PromptRunController::class, 'index'])->name('prompt-runs.index');
    Route::patch('prompt-runs/pause', [PromptRunController::class, 'pause'])->name('prompt-runs.pause');
    Route::delete('prompt-runs/pending', [PromptRunController::class, 'clearPending'])->name('prompt-runs.clear');

    // Refresh the agents from their definition files on disk.
    Route::post('agents/sync', [AgentController::class, 'sync'])->name('agents.sync');
    Route::get('agents/{agent}/profile', [AgentController::class, 'profile'])->name('agents.profile');
    Route::get('agents/{agent}/tasks', [AgentController::class, 'tasks'])->name('agents.tasks');
    Route::get('agents/{agent}/messages', [AgentController::class, 'messages'])->name('agents.messages');
    Route::resource('agents', AgentController::class);

    Route::resource('mailboxes', MailboxController::class);
    // Download the mailbox's new messages from its IMAP server.
    Route::post('mailboxes/{mailbox}/fetch', [MailboxController::class, 'fetch'])->name('mailboxes.fetch');
    Route::resource('mailboxes.emails', IncomingEmailController::class)
        ->only('destroy')
        ->parameters(['emails' => 'incomingEmail'])
        ->scoped();
    // Hand the message to the mailbox's agent as a prompt on the mailbox's project.
    Route::post('mailboxes/{mailbox}/emails/{incomingEmail}/read', [IncomingEmailController::class, 'read'])
        ->scopeBindings()
        ->name('mailboxes.emails.read');
    Route::get('mailboxes/{mailbox}/emails/{incomingEmail}/attachments/{attachment}', [IncomingEmailController::class, 'downloadAttachment'])
        ->scopeBindings()
        ->name('mailboxes.emails.attachments.download');
    // Approve (send) or discard a reply the mailbox's agent wrote.
    Route::post('mailboxes/{mailbox}/replies/{outgoingEmail}/send', [OutgoingEmailController::class, 'send'])
        ->scopeBindings()
        ->name('mailboxes.replies.send');
    Route::delete('mailboxes/{mailbox}/replies/{outgoingEmail}', [OutgoingEmailController::class, 'destroy'])
        ->scopeBindings()
        ->name('mailboxes.replies.destroy');

    Route::resource('projects', ProjectController::class);
    Route::get('projects/{project}/prompts', [ProjectController::class, 'prompts'])->name('projects.prompts');
    Route::post('projects/{project}/runs', [PromptRunController::class, 'store'])->name('projects.runs.store');
    // Start a new chat context (fresh claude session) from an existing one.
    Route::post('agent-contexts/{agentContext}/new', [AgentContextController::class, 'store'])->name('agent-contexts.store');

    // Reload the project's docs/documentations folder into the database.
    Route::post('projects/{project}/documentations/sync', [DocumentationController::class, 'sync'])
        ->name('projects.documentations.sync');
    // Download the project's full documentation as a single PDF.
    Route::get('projects/{project}/documentations/pdf', [DocumentationController::class, 'pdf'])
        ->name('projects.documentations.pdf');
    Route::resource('projects.documentations', DocumentationController::class)->scoped();

    // Files stored for the project, and reloading its attachments folder.
    Route::get('projects/{project}/attachments', [ProjectAttachmentController::class, 'index'])
        ->name('projects.attachments.index');
    Route::post('projects/{project}/attachments', [ProjectAttachmentController::class, 'store'])
        ->name('projects.attachments.store');
    Route::post('projects/{project}/attachments/sync', [ProjectAttachmentController::class, 'sync'])
        ->name('projects.attachments.sync');
    // JSON endpoints for the chat attachment picker: list the project's files and
    // upload a new one without leaving the page.
    Route::get('projects/{project}/attachments/picker', [ProjectAttachmentController::class, 'picker'])
        ->name('projects.attachments.picker');
    Route::post('projects/{project}/attachments/picker', [ProjectAttachmentController::class, 'pickerUpload'])
        ->name('projects.attachments.picker.upload');
    Route::get('projects/{project}/attachments/{attachment}/download', [ProjectAttachmentController::class, 'download'])
        ->scopeBindings()
        ->name('projects.attachments.download');
    Route::delete('projects/{project}/attachments/{attachment}', [ProjectAttachmentController::class, 'destroy'])
        ->scopeBindings()
        ->name('projects.attachments.destroy');

    // Change log of the project's tasks (status, priority, type, assignee, due date).
    Route::get('projects/{project}/task-changes', [TaskChangeController::class, 'index'])
        ->name('projects.task-changes.index');
    Route::resource('projects.tasks', TaskController::class)->scoped();
    // Hand the task to its assigned agent as a prompt run on the task's project.
    Route::post('projects/{project}/tasks/{task}/run', [TaskController::class, 'run'])
        ->scopeBindings()
        ->name('projects.tasks.run');
    Route::post('projects/{project}/tasks/{task}/comments', [TaskCommentController::class, 'store'])
        ->scopeBindings()
        ->name('projects.tasks.comments.store');

    // Files attached to a task, so an agent can reference them.
    Route::post('projects/{project}/tasks/{task}/attachments', [TaskAttachmentController::class, 'store'])
        ->scopeBindings()
        ->name('projects.tasks.attachments.store');
    Route::get('projects/{project}/tasks/{task}/attachments/{attachment}/download', [TaskAttachmentController::class, 'download'])
        ->scopeBindings()
        ->name('projects.tasks.attachments.download');
    Route::delete('projects/{project}/tasks/{task}/attachments/{attachment}', [TaskAttachmentController::class, 'destroy'])
        ->scopeBindings()
        ->name('projects.tasks.attachments.destroy');
});

require __DIR__.'/settings.php';
