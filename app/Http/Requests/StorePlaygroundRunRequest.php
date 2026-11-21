<?php

namespace App\Http\Requests;

use App\Services\ProjectService;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class StorePlaygroundRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'project' => [
                'required',
                'string',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (app(ProjectService::class)->find((string) $value) === null) {
                        $fail('The selected project does not exist.');
                    }
                },
            ],
            'prompt' => ['required', 'string', 'min:3', 'max:20000'],
        ];
    }
}
