<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $firstName = fake()->firstName();
        $lastName = fake()->lastName();
        
        return [
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => fake()->randomElement(['admin', 'team', 'client']),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'avatar' => null,
            'status' => 'active',
            'bio' => fake()->optional()->sentence(),
            'company' => fake()->optional()->company(),
            'last_active' => fake()->optional()->dateTimeBetween('-1 hour', 'now'),
            'notifications_muted' => fake()->boolean(20),
            'show_online_status' => fake()->boolean(80),
            'show_last_active' => fake()->boolean(70),
            'avatar_color' => fake()->hexColor(),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user is an admin.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'admin',
        ]);
    }

    /**
     * Indicate that the user is a team member.
     */
    public function team(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'team',
        ]);
    }

    /**
     * Indicate that the user is a client.
     */
    public function client(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'client',
        ]);
    }
}
