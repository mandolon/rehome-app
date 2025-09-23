# Sanctum Token Migration Implementation

## Token Migration Service

### app/Services/TokenMigrationService.php
```php
<?php

namespace App\Services;

use App\Models\User;
use App\Models\PersonalAccessToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use App\Services\ExpressSessionService;
use App\Services\AuthMigrationService;

class TokenMigrationService
{
    protected $expressSessionService;
    protected $batchSize = 100;
    protected $cacheKey = 'token_migration_progress';

    public function __construct(ExpressSessionService $expressSessionService)
    {
        $this->expressSessionService = $expressSessionService;
    }

    /**
     * Migrate all users to Sanctum tokens
     */
    public function migrateAllUsers(): array
    {
        $stats = [
            'total_users' => 0,
            'migrated_users' => 0,
            'failed_migrations' => 0,
            'skipped_users' => 0,
            'errors' => [],
        ];

        // Get all users
        $users = User::all();
        $stats['total_users'] = $users->count();

        // Set migration in progress
        AuthMigrationService::setMigrationInProgress(true);
        AuthMigrationService::setRollbackAvailable(true);

        try {
            // Process users in batches
            $users->chunk($this->batchSize, function ($userBatch) use (&$stats) {
                foreach ($userBatch as $user) {
                    try {
                        $result = $this->migrateUser($user);
                        
                        if ($result['success']) {
                            $stats['migrated_users']++;
                        } else {
                            $stats['failed_migrations']++;
                            $stats['errors'][] = [
                                'user_id' => $user->id,
                                'username' => $user->username,
                                'error' => $result['error'],
                            ];
                        }
                    } catch (\Exception $e) {
                        $stats['failed_migrations']++;
                        $stats['errors'][] = [
                            'user_id' => $user->id,
                            'username' => $user->username,
                            'error' => $e->getMessage(),
                        ];
                    }
                }

                // Update progress
                $this->updateMigrationProgress($stats);
            });

            // Update final stats
            AuthMigrationService::updateMigrationStats($stats);

        } catch (\Exception $e) {
            $stats['errors'][] = [
                'error' => 'Migration failed: ' . $e->getMessage(),
            ];
        } finally {
            // Clear migration in progress flag
            AuthMigrationService::setMigrationInProgress(false);
        }

        return $stats;
    }

    /**
     * Migrate single user to Sanctum tokens
     */
    public function migrateUser(User $user): array
    {
        try {
            DB::beginTransaction();

            // Check if user already has Sanctum tokens
            if ($user->tokens()->exists()) {
                return [
                    'success' => false,
                    'error' => 'User already has Sanctum tokens',
                ];
            }

            // Create Sanctum token
            $token = $user->createToken('migration-token', ['*']);
            
            // Migrate Express session data
            $this->migrateExpressSession($user);
            
            // Update user migration status
            $user->update([
                'migrated_to_sanctum_at' => now(),
                'migration_token_id' => $token->accessToken->id,
            ]);

            DB::commit();

            return [
                'success' => true,
                'token_id' => $token->accessToken->id,
                'token_name' => $token->accessToken->name,
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Migrate Express session data for user
     */
    protected function migrateExpressSession(User $user): void
    {
        // Get all Express sessions for this user
        $sessions = $this->expressSessionService->getUserSessions($user->id);
        
        foreach ($sessions as $session) {
            // Create Laravel session with Express data
            $sessionData = [
                'user_id' => $user->id,
                'username' => $user->username,
                'migrated_from_express' => true,
                'express_session_id' => $session['id'],
                'migrated_at' => now()->toISOString(),
            ];
            
            // Store in Laravel session table
            DB::table('sessions')->insert([
                'id' => $this->generateSessionId(),
                'user_id' => $user->id,
                'ip_address' => $session['ip_address'] ?? null,
                'user_agent' => $session['user_agent'] ?? null,
                'payload' => base64_encode(json_encode($sessionData)),
                'last_activity' => time(),
            ]);
        }
    }

    /**
     * Rollback user migration
     */
    public function rollbackUser(User $user): array
    {
        try {
            DB::beginTransaction();

            // Revoke all Sanctum tokens
            $user->tokens()->delete();
            
            // Remove migration status
            $user->update([
                'migrated_to_sanctum_at' => null,
                'migration_token_id' => null,
            ]);

            // Restore Express sessions
            $this->restoreExpressSessions($user);

            DB::commit();

            return [
                'success' => true,
                'message' => 'User migration rolled back successfully',
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Restore Express sessions for user
     */
    protected function restoreExpressSessions(User $user): void
    {
        // Get Laravel sessions that were migrated from Express
        $laravelSessions = DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('payload', 'like', '%migrated_from_express%')
            ->get();

        foreach ($laravelSessions as $session) {
            $payload = json_decode(base64_decode($session->payload), true);
            
            if (isset($payload['express_session_id'])) {
                // Restore Express session
                $expressSessionData = [
                    'user_id' => $user->id,
                    'username' => $user->username,
                    'restored_at' => now()->toISOString(),
                ];
                
                $this->expressSessionService->createSession($expressSessionData);
            }
        }
    }

    /**
     * Get migration progress
     */
    public function getMigrationProgress(): array
    {
        $progress = Cache::get($this->cacheKey, [
            'total_users' => 0,
            'migrated_users' => 0,
            'failed_migrations' => 0,
            'current_batch' => 0,
            'total_batches' => 0,
            'started_at' => null,
            'estimated_completion' => null,
        ]);

        // Calculate percentage
        if ($progress['total_users'] > 0) {
            $progress['percentage'] = round(
                ($progress['migrated_users'] / $progress['total_users']) * 100,
                2
            );
        } else {
            $progress['percentage'] = 0;
        }

        return $progress;
    }

    /**
     * Update migration progress
     */
    protected function updateMigrationProgress(array $stats): void
    {
        $progress = [
            'total_users' => $stats['total_users'],
            'migrated_users' => $stats['migrated_users'],
            'failed_migrations' => $stats['failed_migrations'],
            'current_batch' => ceil($stats['migrated_users'] / $this->batchSize),
            'total_batches' => ceil($stats['total_users'] / $this->batchSize),
            'started_at' => Cache::get($this->cacheKey . '_started_at', now()),
            'estimated_completion' => $this->estimateCompletion($stats),
        ];

        Cache::put($this->cacheKey, $progress, 3600); // 1 hour
    }

    /**
     * Estimate completion time
     */
    protected function estimateCompletion(array $stats): ?string
    {
        if ($stats['migrated_users'] === 0) {
            return null;
        }

        $startedAt = Cache::get($this->cacheKey . '_started_at');
        if (!$startedAt) {
            return null;
        }

        $elapsed = now()->diffInSeconds($startedAt);
        $rate = $stats['migrated_users'] / $elapsed; // users per second
        $remaining = $stats['total_users'] - $stats['migrated_users'];
        $estimatedSeconds = $remaining / $rate;

        return now()->addSeconds($estimatedSeconds)->toISOString();
    }

    /**
     * Start migration process
     */
    public function startMigration(): void
    {
        Cache::put($this->cacheKey . '_started_at', now(), 3600);
        AuthMigrationService::setMigrationInProgress(true);
        AuthMigrationService::setRollbackAvailable(true);
    }

    /**
     * Complete migration process
     */
    public function completeMigration(): void
    {
        AuthMigrationService::setMigrationInProgress(false);
        AuthMigrationService::setRollbackAvailable(false);
        Cache::forget($this->cacheKey . '_started_at');
    }

    /**
     * Generate secure session ID
     */
    protected function generateSessionId(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Get migration statistics
     */
    public function getMigrationStats(): array
    {
        $totalUsers = User::count();
        $migratedUsers = User::whereNotNull('migrated_to_sanctum_at')->count();
        $failedMigrations = User::whereNull('migrated_to_sanctum_at')
            ->whereNotNull('migration_token_id')
            ->count();

        return [
            'total_users' => $totalUsers,
            'migrated_users' => $migratedUsers,
            'failed_migrations' => $failedMigrations,
            'migration_percentage' => $totalUsers > 0 ? round(($migratedUsers / $totalUsers) * 100, 2) : 0,
            'last_migration_at' => User::whereNotNull('migrated_to_sanctum_at')
                ->latest('migrated_to_sanctum_at')
                ->value('migrated_to_sanctum_at'),
        ];
    }

    /**
     * Clean up old migration data
     */
    public function cleanupMigrationData(): int
    {
        // Remove old migration tokens
        $deletedTokens = PersonalAccessToken::where('name', 'migration-token')
            ->where('created_at', '<', now()->subDays(30))
            ->delete();

        // Remove old migration status
        $updatedUsers = User::where('migrated_to_sanctum_at', '<', now()->subDays(30))
            ->update([
                'migration_token_id' => null,
            ]);

        return $deletedTokens + $updatedUsers;
    }
}
```

## Migration Controller

### app/Http/Controllers/Auth/TokenMigrationController.php
```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\TokenMigrationService;
use App\Services\AuthMigrationService;
use Illuminate\Support\Facades\Validator;

class TokenMigrationController extends Controller
{
    protected $tokenMigrationService;

    public function __construct(TokenMigrationService $tokenMigrationService)
    {
        $this->tokenMigrationService = $tokenMigrationService;
    }

    /**
     * Start token migration process
     */
    public function startMigration(Request $request): JsonResponse
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'confirm' => 'required|boolean|accepted',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Migration confirmation required',
                'errors' => $validator->errors(),
            ], 400);
        }

        // Check if migration is already in progress
        if (AuthMigrationService::isMigrationInProgress()) {
            return response()->json([
                'success' => false,
                'message' => 'Migration is already in progress',
            ], 409);
        }

        try {
            // Start migration
            $this->tokenMigrationService->startMigration();
            
            // Run migration in background
            dispatch(new \App\Jobs\TokenMigrationJob());

            return response()->json([
                'success' => true,
                'message' => 'Token migration started',
                'migration_id' => uniqid(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to start migration',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get migration progress
     */
    public function getProgress(): JsonResponse
    {
        try {
            $progress = $this->tokenMigrationService->getMigrationProgress();
            $stats = $this->tokenMigrationService->getMigrationStats();

            return response()->json([
                'success' => true,
                'progress' => $progress,
                'stats' => $stats,
                'is_in_progress' => AuthMigrationService::isMigrationInProgress(),
                'can_rollback' => AuthMigrationService::canRollback(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get migration progress',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Migrate specific user
     */
    public function migrateUser(Request $request, int $userId): JsonResponse
    {
        try {
            $user = \App\Models\User::findOrFail($userId);
            $result = $this->tokenMigrationService->migrateUser($user);

            return response()->json([
                'success' => $result['success'],
                'message' => $result['success'] ? 'User migrated successfully' : 'Migration failed',
                'data' => $result,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to migrate user',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Rollback user migration
     */
    public function rollbackUser(Request $request, int $userId): JsonResponse
    {
        try {
            $user = \App\Models\User::findOrFail($userId);
            $result = $this->tokenMigrationService->rollbackUser($user);

            return response()->json([
                'success' => $result['success'],
                'message' => $result['success'] ? 'User rollback completed' : 'Rollback failed',
                'data' => $result,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to rollback user',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Complete migration process
     */
    public function completeMigration(Request $request): JsonResponse
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'confirm' => 'required|boolean|accepted',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Completion confirmation required',
                'errors' => $validator->errors(),
            ], 400);
        }

        try {
            // Complete migration
            $this->tokenMigrationService->completeMigration();
            
            // Enable Sanctum-only mode
            $this->updateEnvFile('AUTH_SANCTUM_ONLY', 'true');
            $this->updateEnvFile('AUTH_MIGRATION_MODE', 'sanctum');
            
            // Clear config cache
            \Artisan::call('config:clear');

            return response()->json([
                'success' => true,
                'message' => 'Migration completed successfully',
                'auth_mode' => 'sanctum_only',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to complete migration',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Rollback entire migration
     */
    public function rollbackMigration(Request $request): JsonResponse
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'confirm' => 'required|boolean|accepted',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Rollback confirmation required',
                'errors' => $validator->errors(),
            ], 400);
        }

        if (!AuthMigrationService::canRollback()) {
            return response()->json([
                'success' => false,
                'message' => 'Rollback is not available',
            ], 400);
        }

        try {
            // Disable Sanctum-only mode
            $this->updateEnvFile('AUTH_SANCTUM_ONLY', 'false');
            $this->updateEnvFile('AUTH_MIGRATION_MODE', 'dual');
            
            // Clear migration flags
            AuthMigrationService::setMigrationInProgress(false);
            AuthMigrationService::setRollbackAvailable(false);
            
            // Clear config cache
            \Artisan::call('config:clear');

            return response()->json([
                'success' => true,
                'message' => 'Migration rolled back successfully',
                'auth_mode' => 'dual',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to rollback migration',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get migration statistics
     */
    public function getStats(): JsonResponse
    {
        try {
            $stats = $this->tokenMigrationService->getMigrationStats();
            $featureFlags = AuthMigrationService::getFeatureFlags();

            return response()->json([
                'success' => true,
                'stats' => $stats,
                'feature_flags' => $featureFlags,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get migration statistics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cleanup migration data
     */
    public function cleanup(): JsonResponse
    {
        try {
            $cleaned = $this->tokenMigrationService->cleanupMigrationData();

            return response()->json([
                'success' => true,
                'message' => 'Migration data cleaned up',
                'cleaned_items' => $cleaned,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to cleanup migration data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update environment file
     */
    protected function updateEnvFile(string $key, string $value): void
    {
        $envFile = base_path('.env');
        $content = file_get_contents($envFile);
        
        if (strpos($content, $key) !== false) {
            $content = preg_replace("/^{$key}=.*/m", "{$key}={$value}", $content);
        } else {
            $content .= "\n{$key}={$value}";
        }
        
        file_put_contents($envFile, $content);
    }
}
```

## Background Job for Migration

### app/Jobs/TokenMigrationJob.php
```php
<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\TokenMigrationService;
use App\Services\AuthMigrationService;
use Illuminate\Support\Facades\Log;

class TokenMigrationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $tokenMigrationService;

    public function __construct()
    {
        $this->tokenMigrationService = app(TokenMigrationService::class);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info('Starting token migration job');
            
            // Run migration
            $stats = $this->tokenMigrationService->migrateAllUsers();
            
            Log::info('Token migration completed', $stats);
            
            // Update final stats
            AuthMigrationService::updateMigrationStats($stats);
            
            // Complete migration
            $this->tokenMigrationService->completeMigration();
            
        } catch (\Exception $e) {
            Log::error('Token migration job failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Set migration as failed
            AuthMigrationService::setMigrationInProgress(false);
            
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Token migration job failed permanently', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
        
        // Set migration as failed
        AuthMigrationService::setMigrationInProgress(false);
    }
}
```

## Migration Routes

### routes/api.php (Add migration routes)
```php
// Token migration routes
Route::prefix('auth/migration')->middleware(['auth:sanctum'])->group(function () {
    Route::post('/start', [TokenMigrationController::class, 'startMigration']);
    Route::get('/progress', [TokenMigrationController::class, 'getProgress']);
    Route::post('/complete', [TokenMigrationController::class, 'completeMigration']);
    Route::post('/rollback', [TokenMigrationController::class, 'rollbackMigration']);
    Route::get('/stats', [TokenMigrationController::class, 'getStats']);
    Route::post('/cleanup', [TokenMigrationController::class, 'cleanup']);
    
    // User-specific migration
    Route::post('/user/{userId}', [TokenMigrationController::class, 'migrateUser']);
    Route::post('/user/{userId}/rollback', [TokenMigrationController::class, 'rollbackUser']);
});
```

## Database Migration for User Migration Tracking

### database/migrations/add_migration_fields_to_users_table.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('migrated_to_sanctum_at')->nullable();
            $table->unsignedBigInteger('migration_token_id')->nullable();
            $table->index(['migrated_to_sanctum_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['migrated_to_sanctum_at']);
            $table->dropColumn(['migrated_to_sanctum_at', 'migration_token_id']);
        });
    }
};
```
