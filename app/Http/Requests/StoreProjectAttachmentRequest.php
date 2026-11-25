<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjectAttachmentRequest extends FormRequest
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
            // Up to 25 MB; files are stored as-is and only ever sent as downloads.
            'file' => ['required', 'file', 'max:25600'],
        ];
    }
}
