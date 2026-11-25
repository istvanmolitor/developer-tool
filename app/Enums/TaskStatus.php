<?php

namespace App\Enums;

enum TaskStatus: string
{
    /** Not ready yet — being drafted. */
    case Draft = 'draft';

    /** Not started yet. */
    case Todo = 'todo';

    /** Currently being worked on. */
    case InProgress = 'in_progress';

    /** Finished. */
    case Done = 'done';

    public function isDone(): bool
    {
        return $this === self::Done;
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Piszkozat',
            self::Todo => 'Teendő',
            self::InProgress => 'Folyamatban',
            self::Done => 'Kész',
        };
    }
}
