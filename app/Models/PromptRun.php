<?php

namespace App\Models;

use App\Enums\PromptRunStatus;
use Database\Factories\PromptRunFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PromptRun extends Model
{
    /** @use HasFactory<PromptRunFactory> */
    use HasFactory;

    protected $fillable = [
        'project',
        'prompt',
        'status',
        'exit_code',
        'output',
        'error_output',
        'started_at',
        'finished_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PromptRunStatus::class,
            'exit_code' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
