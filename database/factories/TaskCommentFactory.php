<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\TaskComment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskComment>
 */
class TaskCommentFactory extends Factory
{
    protected $model = TaskComment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'user_id' => null,
            'agent_id' => null,
            'body' => fake()->paragraph(),
        ];
    }
}
