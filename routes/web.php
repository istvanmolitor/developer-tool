<?php

use App\Http\Controllers\AgentController;
use App\Http\Controllers\DocumentationController;
use App\Http\Controllers\IncomingEmailController;
use App\Http\Controllers\MailboxController;
use App\Http\Controllers\OutgoingEmailController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\PromptRunController;
use App\Http\Controllers\TaskController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

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

    // Reload the project's docs/documentations folder into the database.
    Route::post('projects/{project}/documentations/sync', [DocumentationController::class, 'sync'])
        ->name('projects.documentations.sync');
    // Download the project's full documentation as a single PDF.
    Route::get('projects/{project}/documentations/pdf', [DocumentationController::class, 'pdf'])
        ->name('projects.documentations.pdf');
    Route::resource('projects.documentations', DocumentationController::class)->scoped();

    Route::resource('projects.tasks', TaskController::class)->scoped();
});

require __DIR__.'/settings.php';
