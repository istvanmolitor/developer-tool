<?php

namespace App\Mcp\Concerns;

use App\Models\Attachment;
use App\Models\Task;

trait FormatsAttachments
{
    use FormatsTasks;

    /**
     * Resolve a task by its numeric id.
     */
    protected function resolveTask(int|string $id): ?Task
    {
        return Task::query()->whereKey((int) $id)->first();
    }

    /**
     * Resolve an attachment by its numeric id.
     */
    protected function resolveAttachment(int|string $id): ?Attachment
    {
        return Attachment::query()->whereKey((int) $id)->first();
    }

    /**
     * Shape an attachment into a compact array for MCP responses. `path` is the
     * absolute filesystem path the agent uses to read the file.
     *
     * @return array<string, mixed>
     */
    protected function attachmentToArray(Attachment $attachment): array
    {
        return [
            'id' => $attachment->id,
            'filename' => $attachment->filename,
            'mime_type' => $attachment->mime_type,
            'size' => $attachment->size,
            'source' => $attachment->source,
            'project' => $attachment->project?->name,
            'relative_path' => $attachment->relative_path,
            'path' => $attachment->absolutePath(),
            'task_ids' => $attachment->relationLoaded('tasks')
                ? $attachment->tasks->pluck('id')->all()
                : null,
            'created_at' => $attachment->created_at?->toIso8601String(),
        ];
    }
}
