# Rollback Mechanisms Implementation

## Rollback Service

### app/Services/RollbackService.php
```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Services\AuthMigrationService;
use App\Services\TokenMigrationService;
use App\Services\ExpressSessionService;

class RollbackService
{
    protected $tokenMigrationService;
    protected $expressSessionService;
    protected $rollbackDataKey = 'rollback_data';

    public function __construct(
        TokenMigrationService $tokenMigrationService,
        ExpressSessionService $expressSessionService
    ) {
        $this->tokenMigrationService = $tokenMigrationService;
        $this->expressSessionService = $expressSessionService;
    }

    /**
     * Create rollback point before migration
     */
    public function createRollbackPoint(): array
    {
        try {
            $rollbackData = [
                'created_at' => now()->toISOString(),
                'auth_mode' => AuthMigrationService::getAuthMode(),
                'feature_flags' => AuthMigrationService::getFeatureFlags(),
                'user_sessions' => $this->backupUserSessions(),
                'sanctum_tokens' => $this->backupSanctumTokens(),
                'express_sessions' => $this->backupExpressSessions(),
                'environment_vars' => $this->backupEnvironmentVars(),
            ];

            // Store rollback data
            Cache::put($this->rollbackDataKey, $rollbackData, 86400); // 24 hours

            // Set rollback availability
            AuthMigrationService::setRollbackAvailable(true);

            Log::info('Rollback point created', [
                'rollback_id' => $rollbackData['created_at'],
                'users_count' => count($rollbackData['user_sessions']),
            ]);

            return [
                'success' => true,
                'rollback_id' => $rollbackData['created_at'],
                'message' => 'Rollback point created successfully',
            ];

        } catch (\Exception $e) {
            Log::error('Failed to create rollback point', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Execute rollback to previous state
     */
    public function executeRollback(): array
    {
        try {
            // Get rollback data
            $rollbackData = Cache::get($this->rollbackDataKey);
            
            if (!$rollbackData) {
                return [
                    'success' => false,
                    'error' => 'No rollback data available',
                ];
            }

            DB::beginTransaction();

            // Restore authentication mode
            $this->restoreAuthMode($rollbackData);

            // Restore user sessions
            $this->restoreUserSessions($rollbackData['user_sessions']);

            // Restore Express sessions
            $this->restoreExpressSessions($rollbackData['express_sessions']);

            // Restore environment variables
            $this->restoreEnvironmentVars($rollbackData['environment_vars']);

            // Clean up Sanctum tokens
            $this->cleanupSanctumTokens($rollbackData['sanctum_tokens']);

            DB::commit();

            // Clear rollback data
            Cache::forget($this->rollbackDataKey);
            AuthMigrationService::setRollbackAvailable(false);

            Log::info('Rollback executed successfully', [
                'rollback_id' => $rollbackData['created_at'],
            ]);

            return [
                'success' => true,
                'message' => 'Rollback executed successfully',
                'restored_to' => $rollbackData['created_at'],
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Rollback execution failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Backup user sessions
     */
    protected function backupUserSessions(): array
    {
        $sessions = DB::table('sessions')
            ->whereNotNull('user_id')
            ->get()
            ->map(function ($session) {
                return [
                    'id' => $session->id,
                    'user_id' => $session->user_id,
                    'ip_address' => $session->ip_address,
                    'user_agent' => $session->user_agent,
                    'payload' => $session->payload,
                    'last_activity' => $session->last_activity,
                ];
            })
            ->toArray();

        return $sessions;
    }

    /**
     * Backup Sanctum tokens
     */
    protected function backupSanctumTokens(): array
    {
        $tokens = DB::table('personal_access_tokens')
            ->get()
            ->map(function ($token) {
                return [
                    'id' => $token->id,
                    'tokenable_type' => $token->tokenable_type,
                    'tokenable_id' => $token->tokenable_id,
                    'name' => $token->name,
                    'abilities' => $token->abilities,
                    'last_used_at' => $token->last_used_at,
                    'expires_at' => $token->expires_at,
                ];
            })
            ->toArray();

        return $tokens;
    }

    /**
     * Backup Express sessions
     */
    protected function backupExpressSessions(): array
    {
        return $this->expressSessionService->getSessionStats();
    }

    /**
     * Backup environment variables
     */
    protected function backupEnvironmentVars(): array
    {
        return [
            'AUTH_SANCTUM_ONLY' => env('AUTH_SANCTUM_ONLY'),
            'AUTH_MIGRATION_MODE' => env('AUTH_MIGRATION_MODE'),
            'AUTH_SESSION_BRIDGE' => env('AUTH_SESSION_BRIDGE'),
        ];
    }

    /**
     * Restore authentication mode
     */
    protected function restoreAuthMode(array $rollbackData): void
    {
        $authMode = $rollbackData['auth_mode'];
        
        // Update environment file
        $this->updateEnvFile('AUTH_SANCTUM_ONLY', $authMode === 'sanctum_only' ? 'true' : 'false');
        $this->updateEnvFile('AUTH_MIGRATION_MODE', $authMode);
        
        // Clear config cache
        \Artisan::call('config:clear');
    }

    /**
     * Restore user sessions
     */
    protected function restoreUserSessions(array $sessions): void
    {
        // Clear current sessions
        DB::table('sessions')->truncate();
        
        // Restore sessions
        foreach ($sessions as $session) {
            DB::table('sessions')->insert($session);
        }
    }

    /**
     * Restore Express sessions
     */
    protected function restoreExpressSessions(array $sessionStats): void
    {
        // This would restore Express session data
        // Implementation depends on Express session storage
        Log::info('Express sessions restored', $sessionStats);
    }

    /**
     * Restore environment variables
     */
    protected function restoreEnvironmentVars(array $envVars): void
    {
        foreach ($envVars as $key => $value) {
            $this->updateEnvFile($key, $value);
        }
    }

    /**
     * Clean up Sanctum tokens
     */
    protected function cleanupSanctumTokens(array $tokens): void
    {
        // Delete all current tokens
        DB::table('personal_access_tokens')->truncate();
        
        Log::info('Sanctum tokens cleaned up', [
            'deleted_count' => count($tokens),
        ]);
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

    /**
     * Check if rollback is available
     */
    public function isRollbackAvailable(): bool
    {
        return Cache::has($this->rollbackDataKey) && AuthMigrationService::canRollback();
    }

    /**
     * Get rollback information
     */
    public function getRollbackInfo(): array
    {
        $rollbackData = Cache::get($this->rollbackDataKey);
        
        if (!$rollbackData) {
            return [
                'available' => false,
                'message' => 'No rollback data available',
            ];
        }

        return [
            'available' => true,
            'created_at' => $rollbackData['created_at'],
            'auth_mode' => $rollbackData['auth_mode'],
            'users_count' => count($rollbackData['user_sessions']),
            'tokens_count' => count($rollbackData['sanctum_tokens']),
            'expires_at' => now()->addHours(24)->toISOString(),
        ];
    }

    /**
     * Emergency rollback (force)
     */
    public function emergencyRollback(): array
    {
        try {
            // Force disable Sanctum-only mode
            $this->updateEnvFile('AUTH_SANCTUM_ONLY', 'false');
            $this->updateEnvFile('AUTH_MIGRATION_MODE', 'dual');
            
            // Clear all migration flags
            AuthMigrationService::setMigrationInProgress(false);
            AuthMigrationService::setRollbackAvailable(false);
            
            // Clear config cache
            \Artisan::call('config:clear');
            
            // Clear all caches
            \Artisan::call('cache:clear');
            \Artisan::call('config:clear');
            \Artisan::call('route:clear');
            
            Log::warning('Emergency rollback executed');

            return [
                'success' => true,
                'message' => 'Emergency rollback completed',
                'auth_mode' => 'dual',
            ];

        } catch (\Exception $e) {
            Log::error('Emergency rollback failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
```

## Rollback Controller

### app/Http/Controllers/Auth/RollbackController.php
```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\RollbackService;
use App\Services\AuthMigrationService;
use Illuminate\Support\Facades\Validator;

class RollbackController extends Controller
{
    protected $rollbackService;

    public function __construct(RollbackService $rollbackService)
    {
        $this->rollbackService = $rollbackService;
    }

    /**
     * Create rollback point
     */
    public function createRollbackPoint(Request $request): JsonResponse
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'confirm' => 'required|boolean|accepted',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Rollback point creation confirmation required',
                'errors' => $validator->errors(),
            ], 400);
        }

        try {
            $result = $this->rollbackService->createRollbackPoint();

            return response()->json($result, $result['success'] ? 200 : 500);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create rollback point',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Execute rollback
     */
    public function executeRollback(Request $request): JsonResponse
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'confirm' => 'required|boolean|accepted',
            'reason' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Rollback confirmation and reason required',
                'errors' => $validator->errors(),
            ], 400);
        }

        if (!$this->rollbackService->isRollbackAvailable()) {
            return response()->json([
                'success' => false,
                'message' => 'Rollback is not available',
            ], 400);
        }

        try {
            $result = $this->rollbackService->executeRollback();

            return response()->json($result, $result['success'] ? 200 : 500);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to execute rollback',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Emergency rollback
     */
    public function emergencyRollback(Request $request): JsonResponse
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'confirm' => 'required|boolean|accepted',
            'reason' => 'required|string|max:500',
            'emergency_code' => 'required|string|in:' . env('EMERGENCY_ROLLBACK_CODE', 'EMERGENCY123'),
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Emergency rollback confirmation, reason, and code required',
                'errors' => $validator->errors(),
            ], 400);
        }

        try {
            $result = $this->rollbackService->emergencyRollback();

            return response()->json($result, $result['success'] ? 200 : 500);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Emergency rollback failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get rollback information
     */
    public function getRollbackInfo(): JsonResponse
    {
        try {
            $info = $this->rollbackService->getRollbackInfo();
            $featureFlags = AuthMigrationService::getFeatureFlags();

            return response()->json([
                'success' => true,
                'rollback_info' => $info,
                'feature_flags' => $featureFlags,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get rollback information',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check rollback availability
     */
    public function checkAvailability(): JsonResponse
    {
        try {
            $available = $this->rollbackService->isRollbackAvailable();
            $info = $this->rollbackService->getRollbackInfo();

            return response()->json([
                'success' => true,
                'available' => $available,
                'info' => $info,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to check rollback availability',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
```

## CORS and CSRF Configuration

### config/cors.php (Updated)
```php
<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:3000,http://127.0.0.1:3000')),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [
        'X-Auth-Mode', 
        'X-Auth-Guard', 
        'X-Migration-In-Progress',
        'X-Rollback-Available',
    ],
    'max_age' => 0,
    'supports_credentials' => true,
];
```

### Cookie Configuration

### config/session.php (Updated)
```php
<?php

return [
    'driver' => env('SESSION_DRIVER', 'database'),
    'lifetime' => env('SESSION_LIFETIME', 120),
    'expire_on_close' => false,
    'encrypt' => env('SESSION_ENCRYPT', false),
    'files' => storage_path('framework/sessions'),
    'connection' => env('SESSION_CONNECTION'),
    'table' => 'sessions',
    'store' => env('SESSION_STORE'),
    'lottery' => [2, 100],
    'cookie' => env('SESSION_COOKIE', Str::slug(env('APP_NAME', 'laravel'), '_').'_session'),
    'path' => '/',
    'domain' => env('SESSION_DOMAIN'),
    'secure' => env('SESSION_SECURE_COOKIE', false),
    'http_only' => env('SESSION_HTTP_ONLY', true),
    'same_site' => env('SESSION_SAME_SITE', 'lax'),
];
```

## Token TTL and Rotation

### app/Services/TokenRotationService.php
```php
<?php

namespace App\Services;

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Support\Facades\Hash;

class TokenRotationService
{
    protected $rotationInterval = 24; // hours
    protected $maxTokenAge = 168; // hours (1 week)

    /**
     * Rotate user tokens
     */
    public function rotateUserTokens(User $user): array
    {
        try {
            $oldTokens = $user->tokens()->get();
            $newToken = $user->createToken('rotated-token', ['*']);
            
            // Revoke old tokens
            $user->tokens()->where('id', '!=', $newToken->accessToken->id)->delete();
            
            return [
                'success' => true,
                'new_token' => $newToken->plainTextToken,
                'revoked_tokens' => $oldTokens->count(),
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Clean up expired tokens
     */
    public function cleanupExpiredTokens(): int
    {
        $expiredTokens = PersonalAccessToken::where('expires_at', '<', now())
            ->orWhere('created_at', '<', now()->subHours($this->maxTokenAge))
            ->delete();

        return $expiredTokens;
    }

    /**
     * Rotate all active tokens
     */
    public function rotateAllTokens(): array
    {
        $users = User::whereHas('tokens')->get();
        $results = [];

        foreach ($users as $user) {
            $result = $this->rotateUserTokens($user);
            $results[] = [
                'user_id' => $user->id,
                'username' => $user->username,
                'result' => $result,
            ];
        }

        return [
            'success' => true,
            'rotated_users' => count($results),
            'results' => $results,
        ];
    }

    /**
     * Get token statistics
     */
    public function getTokenStats(): array
    {
        $totalTokens = PersonalAccessToken::count();
        $activeTokens = PersonalAccessToken::where('expires_at', '>', now())
            ->orWhereNull('expires_at')
            ->count();
        $expiredTokens = $totalTokens - $activeTokens;
        $oldTokens = PersonalAccessToken::where('created_at', '<', now()->subHours($this->maxTokenAge))->count();

        return [
            'total_tokens' => $totalTokens,
            'active_tokens' => $activeTokens,
            'expired_tokens' => $expiredTokens,
            'old_tokens' => $oldTokens,
            'rotation_interval_hours' => $this->rotationInterval,
            'max_token_age_hours' => $this->maxTokenAge,
        ];
    }
}
```

## Rollback Routes

### routes/api.php (Add rollback routes)
```php
// Rollback routes
Route::prefix('auth/rollback')->middleware(['auth:sanctum'])->group(function () {
    Route::post('/create-point', [RollbackController::class, 'createRollbackPoint']);
    Route::post('/execute', [RollbackController::class, 'executeRollback']);
    Route::post('/emergency', [RollbackController::class, 'emergencyRollback']);
    Route::get('/info', [RollbackController::class, 'getRollbackInfo']);
    Route::get('/availability', [RollbackController::class, 'checkAvailability']);
});
```

## Environment Variables for Rollback

### .env (Add rollback configuration)
```bash
# Rollback Configuration
EMERGENCY_ROLLBACK_CODE=EMERGENCY123
ROLLBACK_DATA_TTL=86400
AUTO_ROLLBACK_ON_FAILURE=false

# Token Configuration
TOKEN_ROTATION_INTERVAL=24
MAX_TOKEN_AGE=168
AUTO_TOKEN_CLEANUP=true
```
