<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\TaskMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class TaskMessageManagementTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $adminUser;
    protected User $teamUser;
    protected User $clientUser;
    protected User $otherUser;
    protected Task $task;
    protected TaskMessage $message;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->adminUser = User::factory()->admin()->create();
        $this->teamUser = User::factory()->team()->create();
        $this->clientUser = User::factory()->client()->create();
        $this->otherUser = User::factory()->client()->create();
        
        $this->task = Task::factory()->create([
            'created_by' => $this->teamUser->id
        ]);
        
        $this->message = TaskMessage::factory()->create([
            'task_id' => $this->task->task_id,
            'user_id' => $this->teamUser->id
        ]);
    }

    /**
     * Test GET /api/tasks/{taskId}/messages endpoint
     */
    public function test_can_get_task_messages(): void
    {
        TaskMessage::factory(3)->create([
            'task_id' => $this->task->task_id
        ]);

        $response = $this->actingAs($this->teamUser, 'sanctum')
            ->getJson("/api/tasks/{$this->task->task_id}/messages");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'task_id',
                        'content',
                        'message_type',
                        'created_at',
                        'user'
                    ]
                ],
                'meta' => [
                    'success',
                    'message',
                    'timestamp'
                ],
                'errors'
            ]);

        $data = $response->json('data');
        $this->assertTrue(count($data) >= 4); // At least 4 messages for this task
    }

    public function test_cannot_get_messages_without_task_access(): void
    {
        $response = $this->actingAs($this->otherUser, 'sanctum')
            ->getJson("/api/tasks/{$this->task->task_id}/messages");

        $response->assertStatus(403);
    }

    /**
     * Test POST /api/tasks/{taskId}/messages endpoint
     */
    public function test_can_create_task_message(): void
    {
        $messageData = [
            'content' => $this->faker->paragraph(),
            'user_id' => $this->teamUser->id,
            'message_type' => 'comment'
        ];

        $response = $this->actingAs($this->teamUser, 'sanctum')
            ->postJson("/api/tasks/{$this->task->task_id}/messages", $messageData);

        $response->assertStatus(201)
            ->assertJsonPath('data.content', $messageData['content'])
            ->assertJsonPath('data.message_type', 'comment')
            ->assertJsonPath('meta.success', true);

        $this->assertDatabaseHas('task_messages', [
            'task_id' => $this->task->task_id,
            'content' => $messageData['content'],
            'user_id' => $this->teamUser->id
        ]);
    }

    public function test_cannot_create_message_without_content(): void
    {
        $response = $this->actingAs($this->teamUser, 'sanctum')
            ->postJson("/api/tasks/{$this->task->task_id}/messages", [
                'user_id' => $this->teamUser->id
            ]);

        $response->assertStatus(422);
    }

    /**
     * Test PUT /api/tasks/{taskId}/messages/{messageId} endpoint
     */
    public function test_can_update_own_message(): void
    {
        $newContent = 'Updated message content';

        $response = $this->actingAs($this->teamUser, 'sanctum')
            ->putJson("/api/tasks/{$this->task->task_id}/messages/{$this->message->id}", [
                'content' => $newContent
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.content', $newContent)
            ->assertJsonPath('meta.success', true);

        $this->assertDatabaseHas('task_messages', [
            'id' => $this->message->id,
            'content' => $newContent
        ]);
    }

    public function test_cannot_update_other_users_message(): void
    {
        $response = $this->actingAs($this->clientUser, 'sanctum')
            ->putJson("/api/tasks/{$this->task->task_id}/messages/{$this->message->id}", [
                'content' => 'Trying to update'
            ]);

        $response->assertStatus(403);
    }

    public function test_admin_can_update_any_message(): void
    {
        $newContent = 'Admin updated message';

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->putJson("/api/tasks/{$this->task->task_id}/messages/{$this->message->id}", [
                'content' => $newContent
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.content', $newContent);
    }

    /**
     * Test DELETE /api/tasks/{taskId}/messages/{messageId} endpoint
     */
    public function test_can_delete_own_message(): void
    {
        $messageId = $this->message->id;

        $response = $this->actingAs($this->teamUser, 'sanctum')
            ->deleteJson("/api/tasks/{$this->task->task_id}/messages/{$messageId}");

        $response->assertStatus(204);

        $this->assertDatabaseMissing('task_messages', [
            'id' => $messageId
        ]);
    }

    public function test_cannot_delete_other_users_message(): void
    {
        $response = $this->actingAs($this->clientUser, 'sanctum')
            ->deleteJson("/api/tasks/{$this->task->task_id}/messages/{$this->message->id}");

        $response->assertStatus(403);
    }

    public function test_admin_can_delete_any_message(): void
    {
        $messageId = $this->message->id;

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->deleteJson("/api/tasks/{$this->task->task_id}/messages/{$messageId}");

        $response->assertStatus(204);

        $this->assertDatabaseMissing('task_messages', [
            'id' => $messageId
        ]);
    }

    /**
     * Test message with metadata
     */
    public function test_can_create_message_with_metadata(): void
    {
        $metadata = [
            'attachment_url' => 'https://example.com/file.pdf',
            'file_name' => 'document.pdf',
            'file_size' => '1024000'
        ];

        $messageData = [
            'content' => 'Check out this attachment',
            'user_id' => $this->teamUser->id,
            'message_type' => 'attachment',
            'metadata' => $metadata
        ];

        $response = $this->actingAs($this->teamUser, 'sanctum')
            ->postJson("/api/tasks/{$this->task->task_id}/messages", $messageData);

        $response->assertStatus(201)
            ->assertJsonPath('data.message_type', 'attachment')
            ->assertJsonPath('data.metadata.file_name', 'document.pdf');

        $this->assertDatabaseHas('task_messages', [
            'task_id' => $this->task->task_id,
            'message_type' => 'attachment'
        ]);
    }

    public function test_can_update_message_metadata(): void
    {
        $newMetadata = [
            'updated_field' => 'new_value'
        ];

        $response = $this->actingAs($this->teamUser, 'sanctum')
            ->putJson("/api/tasks/{$this->task->task_id}/messages/{$this->message->id}", [
                'metadata' => $newMetadata
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.metadata.updated_field', 'new_value');
    }

    /**
     * Test system messages
     */
    public function test_can_create_system_message(): void
    {
        $messageData = [
            'content' => 'Task status changed to completed',
            'user_id' => $this->teamUser->id,
            'message_type' => 'system'
        ];

        $response = $this->actingAs($this->teamUser, 'sanctum')
            ->postJson("/api/tasks/{$this->task->task_id}/messages", $messageData);

        $response->assertStatus(201)
            ->assertJsonPath('data.message_type', 'system');
    }

    /**
     * Test validation scenarios
     */
    public function test_message_content_cannot_exceed_limit(): void
    {
        $longContent = str_repeat('a', 10001); // Exceeds 10000 character limit

        $response = $this->actingAs($this->teamUser, 'sanctum')
            ->putJson("/api/tasks/{$this->task->task_id}/messages/{$this->message->id}", [
                'content' => $longContent
            ]);

        $response->assertStatus(422);
    }

    public function test_invalid_message_type_rejected(): void
    {
        $response = $this->actingAs($this->teamUser, 'sanctum')
            ->postJson("/api/tasks/{$this->task->task_id}/messages", [
                'content' => 'Test message',
                'user_id' => $this->teamUser->id,
                'message_type' => 'invalid_type'
            ]);

        $response->assertStatus(422);
    }

    /**
     * Test error handling
     */
    public function test_returns_404_for_nonexistent_task(): void
    {
        $response = $this->actingAs($this->teamUser, 'sanctum')
            ->getJson('/api/tasks/nonexistent-task/messages');

        $response->assertStatus(404);
    }

    public function test_returns_404_for_nonexistent_message(): void
    {
        $response = $this->actingAs($this->teamUser, 'sanctum')
            ->putJson("/api/tasks/{$this->task->task_id}/messages/999999", [
                'content' => 'Updated content'
            ]);

        $response->assertStatus(404);
    }

    /**
     * Test response format consistency
     */
    public function test_message_response_includes_user_relationship(): void
    {
        $response = $this->actingAs($this->teamUser, 'sanctum')
            ->getJson("/api/tasks/{$this->task->task_id}/messages");

        $response->assertStatus(200);
        
        $data = $response->json('data');
        foreach ($data as $message) {
            $this->assertArrayHasKey('user', $message);
            $this->assertArrayHasKey('id', $message['user']);
            $this->assertArrayHasKey('email', $message['user']);
        }
    }
}