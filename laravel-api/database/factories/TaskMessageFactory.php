<?php

namespace Database\Factories;

use App\Models\TaskMessage;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TaskMessage>
 */
class TaskMessageFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = TaskMessage::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'user_id' => User::factory(),
            'content' => $this->faker->paragraph(),
            'message_type' => $this->faker->randomElement(['comment', 'system', 'attachment']),
            'metadata' => $this->faker->optional(0.3)->randomElements([
                'attachment_url' => $this->faker->url(),
                'file_name' => $this->faker->word() . '.pdf',
                'file_size' => $this->faker->numberBetween(1000, 5000000),
            ]),
        ];
    }

    /**
     * Indicate that the message is a system message.
     */
    public function system(): static
    {
        return $this->state(fn (array $attributes) => [
            'message_type' => 'system',
            'content' => $this->faker->randomElement([
                'Task status changed to completed',
                'Task assigned to John Doe',
                'Due date updated',
                'Priority changed to high',
            ]),
        ]);
    }

    /**
     * Indicate that the message has an attachment.
     */
    public function withAttachment(): static
    {
        return $this->state(fn (array $attributes) => [
            'message_type' => 'attachment',
            'content' => 'Attached file: ' . $this->faker->word() . '.pdf',
            'metadata' => [
                'attachment_url' => $this->faker->url(),
                'file_name' => $this->faker->word() . '.pdf',
                'file_size' => $this->faker->numberBetween(1000, 5000000),
                'mime_type' => 'application/pdf',
            ],
        ]);
    }

    /**
     * Indicate that the message is a comment.
     */
    public function comment(): static
    {
        return $this->state(fn (array $attributes) => [
            'message_type' => 'comment',
            'content' => $this->faker->paragraph(),
        ]);
    }
}