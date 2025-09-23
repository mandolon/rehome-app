<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Task>
 */
class TaskFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Task::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'task_id' => 'task_' . $this->faker->uuid(),
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
            'status' => $this->faker->randomElement(['redline', 'backlog', 'in_progress', 'in_review', 'completed']),
            'priority' => $this->faker->randomElement(['low', 'medium', 'high', 'urgent']),
            'project_id' => 'project_' . $this->faker->uuid(),
            'project' => $this->faker->company(),
            'due_date' => $this->faker->optional()->dateTimeBetween('now', '+30 days'),
            'assignee' => $this->faker->email(),
            'tags' => $this->faker->randomElements(['urgent', 'bug', 'feature', 'enhancement'], 2),
            'created_by' => User::factory(),
        ];
    }

    /**
     * Indicate that the task is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'completed_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
        ]);
    }

    /**
     * Indicate that the task is deleted.
     */
    public function deleted(): static
    {
        return $this->state(fn (array $attributes) => [
            'deleted_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
            'deleted_by' => User::factory(),
        ]);
    }

    /**
     * Indicate that the task is archived.
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'archived_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
            'archived_by' => User::factory(),
        ]);
    }

    /**
     * Indicate that the task is overdue.
     */
    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'due_date' => $this->faker->dateTimeBetween('-7 days', '-1 day'),
            'status' => $this->faker->randomElement(['redline', 'backlog', 'in_progress', 'in_review']),
        ]);
    }
}