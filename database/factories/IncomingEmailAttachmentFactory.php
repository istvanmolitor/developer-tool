<?php

namespace Database\Factories;

use App\Models\IncomingEmail;
use App\Models\IncomingEmailAttachment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IncomingEmailAttachment>
 */
class IncomingEmailAttachmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'incoming_email_id' => IncomingEmail::factory(),
            'filename' => $this->faker->word().'.pdf',
            'mime_type' => 'application/pdf',
            'size' => $this->faker->numberBetween(1_000, 500_000),
            'path' => 'mail-attachments/'.$this->faker->uuid(),
        ];
    }
}
