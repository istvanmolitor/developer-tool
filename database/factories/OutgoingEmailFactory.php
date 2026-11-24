<?php

namespace Database\Factories;

use App\Enums\OutgoingEmailStatus;
use App\Models\Mailbox;
use App\Models\OutgoingEmail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OutgoingEmail>
 */
class OutgoingEmailFactory extends Factory
{
    protected $model = OutgoingEmail::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'mailbox_id' => Mailbox::factory(),
            'incoming_email_id' => null,
            'prompt_run_id' => null,
            'to' => [$this->faker->safeEmail()],
            'subject' => 'Re: '.$this->faker->sentence(),
            'body_text' => $this->faker->paragraphs(2, true),
            'in_reply_to' => null,
            'status' => OutgoingEmailStatus::Draft,
            'error' => null,
            'sent_at' => null,
        ];
    }

    /**
     * Indicate that the message has already been sent.
     */
    public function sent(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OutgoingEmailStatus::Sent,
            'sent_at' => now(),
        ]);
    }

    /**
     * Indicate that sending the message failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OutgoingEmailStatus::Failed,
            'error' => 'Connection refused',
        ]);
    }
}
