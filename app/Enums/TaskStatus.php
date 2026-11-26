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

    /**
     * Whether a task in this status may be handed to its agent for a run.
     *
     * Draft is still being prepared and Closed is terminal; everything in
     * between — todo, in progress, done — can be (re)started.
     */
    public function isRunnable(): bool
    {
        return in_array($this, [self::Todo, self::InProgress, self::Done], true);
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
