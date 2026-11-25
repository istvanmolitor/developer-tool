<?php

use App\Http\Controllers\AgentContextController;
use App\Http\Controllers\AgentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentationController;
use App\Http\Controllers\IncomingEmailController;
use App\Http\Controllers\MailboxController;
use App\Http\Controllers\OutgoingEmailController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\PromptRunController;
use App\Http\Controllers\SearchController;
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

    // Global Elasticsearch search over tasks, docs, prompts, messages, mails and comments.
    Route::get('search', [SearchController::class, 'index'])->name('search.index');
    Route::get('search/suggest', [SearchController::class, 'suggest'])->name('search.suggest');

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
});

require __DIR__.'/settings.php';
