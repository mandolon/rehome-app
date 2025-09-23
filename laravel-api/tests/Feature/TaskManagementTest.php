<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class TaskManagementTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $adminUser;
    protected User $teamUser;
    protected User $clientUser;
    protected Task $task;
    protected string $projectId;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->adminUser = User::factory()->admin()->create();
        $this->teamUser = User::factory()->team()->create();
        $this->clientUser = User::factory()->client()->create();
        $this->projectId = 'project_' . $this->faker->uuid();
        $this->task = Task::factory()->create([
            'project_id' => $this->projectId,
            'created_by' => $this->teamUser->id
        ]);
    }

    /**
     * Test GET /api/tasks endpoint
     */
    public function test_can_list_tasks(): void
    {
        $response = $this->actingAs($this->teamUser, 'sanctum')
            ->getJson('/api/tasks');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'task_id',
                        'title',
                        'status',
                        'created_at',
                        'updated_at'
                    ]
                ],
                'meta' => [
                    'success',
                    'message',
                    'timestamp'
                ],
                'errors'
            ]);
    }

    /**
     * Test GET /api/tasks/all endpoint (admin only)
     */
    public function test_admin_can_list_all_tasks_including_deleted(): void
    {
        $deletedTask = Task::factory()->deleted()->create();

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/tasks/all');

        $response->assertStatus(200);
        
        $data = $response->json('data');
        $this->assertTrue(count($data) >= 2); // At least our test task + deleted task
    }

    public function test_non_admin_cannot_list_all_tasks(): void
    {
        $response = $this->actingAs($this->teamUser, 'sanctum')
            ->getJson('/api/tasks/all');

        $response->assertStatus(403);
    }

    /**
     * Test PATCH /api/tasks/{id}/status endpoint
     */
    public function test_can_update_task_status(): void
    {
        $response = $this->actingAs($this->teamUser, 'sanctum')
            ->patchJson("/api/tasks/{$this->task->task_id}/status", [
                'status' => 'completed'
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('meta.success', true);

        $this->assertDatabaseHas('tasks', [
            'task_id' => $this->task->task_id,
            'status' => 'completed'
        ]);
    }

    public function test_cannot_update_task_status_with_invalid_status(): void
    {
        $response = $this->actingAs($this->teamUser, 'sanctum')
            ->patchJson("/api/tasks/{$this->task->task_id}/status", [
                'status' => 'invalid_status'
            ]);

        $response->assertStatus(422);
    }

    /**
     * Test POST /api/tasks/{id}/archive endpoint
     */
    public function test_can_archive_task(): void
    {
        $response = $this->actingAs($this->teamUser, 'sanctum')
            ->postJson("/api/tasks/{$this->task->task_id}/archive");

        $response->assertStatus(204);

        $this->task->refresh();
        $this->assertNotNull($this->task->archived_at);
    }

    public function test_client_cannot_archive_task_they_did_not_create(): void
    {
        $response = $this->actingAs($this->clientUser, 'sanctum')
            ->postJson("/api/tasks/{$this->task->task_id}/archive");

        $response->assertStatus(403);
    }

    /**
     * Test DELETE /api/tasks/{id}/permanent endpoint
     */
    public function test_admin_can_permanently_delete_task(): void
    {
        $taskId = $this->task->task_id;
        $this->task->delete(); // Soft delete first

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->deleteJson("/api/tasks/{$taskId}/permanent");

        $response->assertStatus(204);

        $this->assertDatabaseMissing('tasks', [
            'task_id' => $taskId
        ]);
    }

    public function test_non_admin_cannot_permanently_delete_task(): void
    {
        $taskId = $this->task->task_id;
        $this->task->delete(); // Soft delete first

        $response = $this->actingAs($this->teamUser, 'sanctum')
            ->deleteJson("/api/tasks/{$taskId}/permanent");

        $response->assertStatus(403);
    }

    /**
     * Test POST /api/tasks/{id}/restore endpoint
     */
    public function test_can_restore_deleted_task(): void
    {
        $taskId = $this->task->task_id;
        $this->task->delete(); // Soft delete

        $response = $this->actingAs($this->teamUser, 'sanctum')
            ->postJson("/api/tasks/{$taskId}/restore");

        $response->assertStatus(200)
            ->assertJsonPath('data.task_id', $taskId);

        $this->assertDatabaseHas('tasks', [
            'task_id' => $taskId,
            'deleted_at' => null
        ]);
    }

    public function test_cannot_restore_task_without_permission(): void
    {
        $taskId = $this->task->task_id;
        $this->task->delete(); // Soft delete

        $response = $this->actingAs($this->clientUser, 'sanctum')
            ->postJson("/api/tasks/{$taskId}/restore");

        $response->assertStatus(403);
    }

    /**
     * Test GET /api/projects/{projectId}/tasks endpoint
     */
    public function test_can_get_project_tasks(): void
    {
        Task::factory(3)->create(['project_id' => $this->projectId]);

        $response = $this->actingAs($this->teamUser, 'sanctum')
            ->getJson("/api/projects/{$this->projectId}/tasks");

        $response->assertStatus(200);
        
        $data = $response->json('data');
        $this->assertTrue(count($data) >= 4); // At least 4 tasks for this project
        
        foreach ($data as $task) {
            $this->assertEquals($this->projectId, $task['project_id']);
        }
    }

    /**
     * Test authorization scenarios
     */
    public function test_unauthenticated_user_cannot_access_tasks(): void
    {
        $response = $this->getJson('/api/tasks');
        $response->assertStatus(401);
    }

    public function test_admin_can_access_any_task(): void
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson("/api/tasks/{$this->task->task_id}");

        $response->assertStatus(200);
    }

    public function test_task_creator_can_access_their_task(): void
    {
        $response = $this->actingAs($this->teamUser, 'sanctum')
            ->getJson("/api/tasks/{$this->task->task_id}");

        $response->assertStatus(200);
    }

    /**
     * Test data consistency and API response format
     */
    public function test_api_response_format_is_consistent(): void
    {
        $response = $this->actingAs($this->teamUser, 'sanctum')
            ->getJson('/api/tasks');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta' => [
                    'success',
                    'message',
                    'timestamp'
                ],
                'errors'
            ]);

        $this->assertTrue($response->json('meta.success'));
        $this->assertNull($response->json('errors'));
    }

    public function test_validation_error_format_is_consistent(): void
    {
        $response = $this->actingAs($this->teamUser, 'sanctum')
            ->patchJson("/api/tasks/{$this->task->task_id}/status", [
                'status' => 'invalid'
            ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'data',
                'meta' => [
                    'success',
                    'message',
                    'timestamp'
                ],
                'errors'
            ]);

        $this->assertFalse($response->json('meta.success'));
        $this->assertNotNull($response->json('errors'));
    }
}