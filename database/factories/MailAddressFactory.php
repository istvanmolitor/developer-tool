<?php

namespace Database\Factories;

use App\Models\MailAddress;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MailAddress>
 */
class MailAddressFactory extends Factory
{
    protected $model = MailAddress::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'mail' => Str::lower($this->faker->unique()->safeEmail()),
            'name' => $this->faker->name(),
            'is_spam' => false,
        ];
    }

    public function spam(): static
    {
        return $this->state(['is_spam' => true]);
    }
}
