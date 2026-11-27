<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'daily_cost_limit_usd.numeric' => 'A napi limit csak szám lehet (dollárban).',
            'weekly_cost_limit_usd.numeric' => 'A heti limit csak szám lehet (dollárban).',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'auto_start_tasks' => ['boolean'],
            'pause_prompt_runs' => ['boolean'],
            // Spending limits in USD; empty means no limit.
            'daily_cost_limit_usd' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'weekly_cost_limit_usd' => ['nullable', 'numeric', 'min:0', 'max:100000'],
        ];
    }
}
