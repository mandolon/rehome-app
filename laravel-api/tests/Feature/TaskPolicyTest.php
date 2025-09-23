<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\TaskMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $teamUser;
    protected User $clientUser;
    protected User $otherUser;
    protected Task $task;
    protected string $projectId;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->adminUser = User::factory()->admin()->create();
        $this->teamUser = User::factory()->team()->create();
        $this->clientUser = User::factory()->client()->create();
        $this->otherUser = User::factory()->client()->create();
        
        $this->projectId = 'project_123';
        $this->task = Task::factory()->create([
            'project_id' => $this->projectId,
            'created_by' => $this->teamUser->id
        ]);
    }

    /**
     * Test viewAny policy
     */
    public function test_all_authenticated_users_can_view_any_tasks(): void
    {
        $this->assertTrue($this->adminUser->can('viewAny', Task::class));
        $this->assertTrue($this->teamUser->can('viewAny', Task::class));
        $this->assertTrue($this->clientUser->can('viewAny', Task::class));
    }

    /**
     * Test viewAll policy (deleted tasks)
     */
    public function test_only_admin_can_view_all_tasks_including_deleted(): void
    {
        $this->assertTrue($this->adminUser->can('viewAll', Task::class));
        $this->assertFalse($this->teamUser->can('viewAll', Task::class));
        $this->assertFalse($this->clientUser->can('viewAll', Task::class));
    }

    /**
     * Test view policy
     */
    public function test_admin_can_view_any_task(): void
    {
        $this->assertTrue($this->adminUser->can('view', $this->task));
    }

    public function test_team_user_can_view_project_tasks(): void
    {
        $this->assertTrue($this->teamUser->can('view', $this->task));
    }

    public function test_task_creator_can_view_their_task(): void
    {
        $this->assertTrue($this->teamUser->can('view', $this->task));
    }

    public function test_client_can_view_assigned_task(): void
    {
        $clientTask = Task::factory()->create([
            'assignee' => $this->clientUser->email,
            'created_by' => $this->teamUser->id
        ]);

        $this->assertTrue($this->clientUser->can('view', $clientTask));
    }

    public function test_client_can_view_task_they_created(): void
    {
        $clientTask = Task::factory()->create([
            'created_by' => $this->clientUser->id
        ]);

        $this->assertTrue($this->clientUser->can('view', $clientTask));
    }

    /**
     * Test create policy
     */
    public function test_all_authenticated_users_can_create_tasks(): void
    {
        $this->assertTrue($this->adminUser->can('create', Task::class));
        $this->assertTrue($this->teamUser->can('create', Task::class));
        $this->assertTrue($this->clientUser->can('create', Task::class));
    }

    /**
     * Test update policy
     */
    public function test_admin_can_update_any_task(): void
    {
        $this->assertTrue($this->adminUser->can('update', $this->task));
    }

    public function test_team_user_can_update_project_tasks(): void
    {
        $this->assertTrue($this->teamUser->can('update', $this->task));
    }

    public function test_task_creator_can_update_their_task(): void
    {
        $this->assertTrue($this->teamUser->can('update', $this->task));
    }

    public function test_other_users_cannot_update_task(): void
    {
        $this->assertFalse($this->otherUser->can('update', $this->task));
    }

    /**
     * Test updateStatus policy
     */
    public function test_update_status_follows_update_policy(): void
    {
        $this->assertTrue($this->adminUser->can('updateStatus', $this->task));
        $this->assertTrue($this->teamUser->can('updateStatus', $this->task));
        $this->assertFalse($this->otherUser->can('updateStatus', $this->task));
    }

    /**
     * Test archive policy
     */
    public function test_admin_can_archive_any_task(): void
    {
        $this->assertTrue($this->adminUser->can('archive', $this->task));
    }

    public function test_team_user_can_archive_project_tasks(): void
    {
        $this->assertTrue($this->teamUser->can('archive', $this->task));
    }

    public function test_task_creator_can_archive_their_task(): void
    {
        $this->assertTrue($this->teamUser->can('archive', $this->task));
    }

    public function test_other_users_cannot_archive_task(): void
    {
        $this->assertFalse($this->otherUser->can('archive', $this->task));
    }

    /**
     * Test delete policy (soft delete)
     */
    public function test_delete_follows_update_policy(): void
    {
        $this->assertTrue($this->adminUser->can('delete', $this->task));
        $this->assertTrue($this->teamUser->can('delete', $this->task));
        $this->assertFalse($this->otherUser->can('delete', $this->task));
    }

    /**
     * Test restore policy
     */
    public function test_admin_can_restore_any_task(): void
    {
        $this->task->delete();
        $this->assertTrue($this->adminUser->can('restore', $this->task));
    }

    public function test_team_user_can_restore_project_tasks(): void
    {
        $this->task->delete();
        $this->assertTrue($this->teamUser->can('restore', $this->task));
    }

    public function test_task_creator_can_restore_their_task(): void
    {
        $this->task->delete();
        $this->assertTrue($this->teamUser->can('restore', $this->task));
    }

    public function test_other_users_cannot_restore_task(): void
    {
        $this->task->delete();
        $this->assertFalse($this->otherUser->can('restore', $this->task));
    }

    /**
     * Test forceDelete policy (permanent delete)
     */
    public function test_only_admin_can_permanently_delete_tasks(): void
    {
        $this->assertTrue($this->adminUser->can('forceDelete', $this->task));
        $this->assertFalse($this->teamUser->can('forceDelete', $this->task));
        $this->assertFalse($this->clientUser->can('forceDelete', $this->task));
    }

    /**
     * Test viewProjectTasks policy
     */
    public function test_admin_can_view_any_project_tasks(): void
    {
        $this->assertTrue($this->adminUser->can('viewProjectTasks', [Task::class, $this->projectId]));
    }

    public function test_team_user_can_view_project_tasks_with_access(): void
    {
        $this->assertTrue($this->teamUser->can('viewProjectTasks', [Task::class, $this->projectId]));
    }

    public function test_client_can_view_project_tasks_with_access(): void
    {
        $this->assertTrue($this->clientUser->can('viewProjectTasks', [Task::class, $this->projectId]));
    }
}

class TaskMessagePolicyTest extends TestCase
{
    use RefreshDatabase;

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
     * Test viewAny policy
     */
    public function test_all_authenticated_users_can_view_any_messages(): void
    {
        $this->assertTrue($this->adminUser->can('viewAny', TaskMessage::class));
        $this->assertTrue($this->teamUser->can('viewAny', TaskMessage::class));
        $this->assertTrue($this->clientUser->can('viewAny', TaskMessage::class));
    }

    /**
     * Test view policy
     */
    public function test_admin_can_view_any_message(): void
    {
        $this->assertTrue($this->adminUser->can('view', $this->message));
    }

    public function test_team_user_can_view_project_task_messages(): void
    {
        $this->assertTrue($this->teamUser->can('view', $this->message));
    }

    /**
     * Test create policy
     */
    public function test_all_authenticated_users_can_create_messages(): void
    {
        $this->assertTrue($this->adminUser->can('create', TaskMessage::class));
        $this->assertTrue($this->teamUser->can('create', TaskMessage::class));
        $this->assertTrue($this->clientUser->can('create', TaskMessage::class));
    }

    /**
     * Test update policy
     */
    public function test_admin_can_update_any_message(): void
    {
        $this->assertTrue($this->adminUser->can('update', $this->message));
    }

    public function test_message_author_can_update_their_message(): void
    {
        $this->assertTrue($this->teamUser->can('update', $this->message));
    }

    public function test_other_users_cannot_update_message(): void
    {
        $this->assertFalse($this->otherUser->can('update', $this->message));
    }

    /**
     * Test delete policy
     */
    public function test_admin_can_delete_any_message(): void
    {
        $this->assertTrue($this->adminUser->can('delete', $this->message));
    }

    public function test_message_author_can_delete_their_message(): void
    {
        $this->assertTrue($this->teamUser->can('delete', $this->message));
    }

    public function test_other_users_cannot_delete_message(): void
    {
        $this->assertFalse($this->otherUser->can('delete', $this->message));
    }
}