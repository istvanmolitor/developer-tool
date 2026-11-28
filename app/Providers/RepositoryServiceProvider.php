<?php

namespace App\Providers;

use App\Repositories\Contracts\AgentContextRepository;
use App\Repositories\Contracts\AgentMessageRepository;
use App\Repositories\Contracts\AgentRepository;
use App\Repositories\Contracts\AttachmentRepository;
use App\Repositories\Contracts\DocumentRepository;
use App\Repositories\Contracts\IncomingEmailRepository;
use App\Repositories\Contracts\MailAddressRepository;
use App\Repositories\Contracts\MailboxRepository;
use App\Repositories\Contracts\OutgoingEmailRepository;
use App\Repositories\Contracts\ProjectRepository;
use App\Repositories\Contracts\PromptRunRepository;
use App\Repositories\Contracts\SettingRepository;
use App\Repositories\Contracts\TaskChangeRepository;
use App\Repositories\Contracts\TaskRepository;
use App\Repositories\Eloquent\EloquentAgentContextRepository;
use App\Repositories\Eloquent\EloquentAgentMessageRepository;
use App\Repositories\Eloquent\EloquentAgentRepository;
use App\Repositories\Eloquent\EloquentAttachmentRepository;
use App\Repositories\Eloquent\EloquentDocumentRepository;
use App\Repositories\Eloquent\EloquentIncomingEmailRepository;
use App\Repositories\Eloquent\EloquentMailAddressRepository;
use App\Repositories\Eloquent\EloquentMailboxRepository;
use App\Repositories\Eloquent\EloquentOutgoingEmailRepository;
use App\Repositories\Eloquent\EloquentProjectRepository;
use App\Repositories\Eloquent\EloquentPromptRunRepository;
use App\Repositories\Eloquent\EloquentSettingRepository;
use App\Repositories\Eloquent\EloquentTaskChangeRepository;
use App\Repositories\Eloquent\EloquentTaskRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Binds every repository contract to its Eloquent implementation, so callers
 * type-hint the interface and the container hands them the concrete repository.
 * Swapping a data source (e.g. for a test double) is a one-line change here.
 */
class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * All contract => implementation bindings.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        ProjectRepository::class => EloquentProjectRepository::class,
        TaskRepository::class => EloquentTaskRepository::class,
        TaskChangeRepository::class => EloquentTaskChangeRepository::class,
        AgentRepository::class => EloquentAgentRepository::class,
        PromptRunRepository::class => EloquentPromptRunRepository::class,
        AgentContextRepository::class => EloquentAgentContextRepository::class,
        AgentMessageRepository::class => EloquentAgentMessageRepository::class,
        MailboxRepository::class => EloquentMailboxRepository::class,
        IncomingEmailRepository::class => EloquentIncomingEmailRepository::class,
        OutgoingEmailRepository::class => EloquentOutgoingEmailRepository::class,
        MailAddressRepository::class => EloquentMailAddressRepository::class,
        AttachmentRepository::class => EloquentAttachmentRepository::class,
        DocumentRepository::class => EloquentDocumentRepository::class,
        SettingRepository::class => EloquentSettingRepository::class,
    ];
}
