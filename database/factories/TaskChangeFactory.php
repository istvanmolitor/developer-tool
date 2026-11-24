<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\TaskChange;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskChange>
 */
class TaskChangeFactory extends Factory
{
    protected $model = TaskChange::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'user_id' => null,
            'agent_id' => null,
            'changes' => ['status' => ['old' => 'todo', 'new' => 'in_progress']],
        ];
    }
}
