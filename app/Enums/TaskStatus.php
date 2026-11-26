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

    /** Finished — handed back to the project manager for closing. */
    case Done = 'done';

    /** Closed by the project manager — terminal state. */
    case Closed = 'closed';

    public function isDone(): bool
    {
        return $this === self::Done;
    }

    public function isClosed(): bool
    {
        return $this === self::Closed;
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Piszkozat',
            self::Todo => 'Teendő',
            self::InProgress => 'Folyamatban',
            self::Done => 'Kész',
            self::Closed => 'Lezárt',
        };
    }
}
