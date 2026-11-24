<?php

namespace Database\Factories;

use App\Models\IncomingEmail;
use App\Models\Mailbox;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IncomingEmail>
 */
class IncomingEmailFactory extends Factory
{
    protected $model = IncomingEmail::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'mailbox_id' => Mailbox::factory(),
            'uid' => $this->faker->unique()->numberBetween(1, 1_000_000),
            'message_id' => '<'.$this->faker->uuid().'@example.test>',
            'from_address' => $this->faker->safeEmail(),
            'from_name' => $this->faker->name(),
            'to' => [$this->faker->safeEmail()],
            'cc' => null,
            'subject' => $this->faker->sentence(),
            'body_text' => $this->faker->paragraphs(2, true),
            'body_html' => null,
            'received_at' => $this->faker->dateTimeBetween('-1 month'),
            'read_at' => null,
        ];
    }

    /**
     * Indicate that the message has already been opened.
     */
    public function read(): static
    {
        return $this->state(fn (array $attributes): array => [
            'read_at' => now(),
        ]);
    }
}
