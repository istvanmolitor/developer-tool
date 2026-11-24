<?php

namespace Database\Factories;

use App\Enums\MailEncryption;
use App\Models\Mailbox;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Mailbox>
 */
class MailboxFactory extends Factory
{
    protected $model = Mailbox::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $email = $this->faker->unique()->safeEmail();

        return [
            'name' => $this->faker->unique()->words(2, true),
            'email' => $email,
            'from_name' => $this->faker->name(),
            'username' => $email,
            'password' => $this->faker->password(12),
            'imap_host' => 'imap.example.test',
            'imap_port' => 993,
            'imap_encryption' => MailEncryption::Ssl,
            'imap_folder' => 'INBOX',
            'smtp_host' => 'smtp.example.test',
            'smtp_port' => 465,
            'smtp_encryption' => MailEncryption::Ssl,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
