<?php

namespace App\Enums;

use App\Models\AgentMessage;
use App\Models\Documentation;
use App\Models\IncomingEmail;
use App\Models\OutgoingEmail;
use App\Models\PromptRun;
use App\Models\Task;
use App\Models\TaskComment;
use App\Services\Search\Searchable;
use Illuminate\Database\Eloquent\Model;

enum SearchType: string
{
    case Task = 'task';

    case Documentation = 'documentation';

    case Prompt = 'prompt';

    case Message = 'message';

    case Mail = 'mail';

    case Comment = 'comment';

    public function label(): string
    {
        return match ($this) {
            self::Task => 'Feladat',
            self::Documentation => 'Dokumentáció',
            self::Prompt => 'Prompt',
            self::Message => 'Üzenet',
            self::Mail => 'Levél',
            self::Comment => 'Komment',
        };
    }

    /**
     * The models whose rows are indexed as this type.
     *
     * @return list<class-string<Model&Searchable>>
     */
    public function models(): array
    {
        return match ($this) {
            self::Task => [Task::class],
            self::Documentation => [Documentation::class],
            self::Prompt => [PromptRun::class],
            self::Message => [AgentMessage::class],
            self::Mail => [IncomingEmail::class, OutgoingEmail::class],
            self::Comment => [TaskComment::class],
        };
    }
}
