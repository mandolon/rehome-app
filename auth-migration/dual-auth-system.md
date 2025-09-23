# Dual Authentication System Implementation

## Session Bridge Guard

### app/Auth/SessionBridgeGuard.php
```php
<?php

namespace App\Auth;

use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use App\Services\ExpressSessionService;

class SessionBridgeGuard extends SessionGuard
{
    protected $expressSessionService;

    public function __construct(
        string $name,
        UserProvider $provider,
        Request $request,
        ExpressSessionService $expressSessionService
    ) {
        parent::__construct($name, $provider, $request);
        $this->expressSessionService = $expressSessionService;
    }

    /**
     * Get the currently authenticated user.
     */
    public function user()
    {
        // First try Laravel session
        $user = parent::user();
        
        if ($user) {
            return $user;
        }

        // Fallback to Express session
        return $this->getUserFromExpressSession();
    }

    /**
     * Get user from Express session
     */
    protected function getUserFromExpressSession()
    {
        $sessionId = $this->getExpressSessionId();
        
        if (!$sessionId) {
            return null;
        }

        $sessionData = $this->expressSessionService->getSession($sessionId);
        
        if (!$sessionData || !isset($sessionData['user_id'])) {
            return null;
        }

        $user = $this->provider->retrieveById($sessionData['user_id']);
        
        if ($user) {
            // Store in Laravel session for future requests
            $this->login($user, false);
        }

        return $user;
    }

    /**
     * Get Express session ID from cookie
     */
    protected function getExpressSessionId()
    {
        $cookieName = config('session.cookie', 'laravel_session');
        return $this->request->cookie($cookieName);
    }

    /**
     * Validate user credentials
     */
    public function validate(array $credentials = [])
    {
        // Try Laravel validation first
        if (parent::validate($credentials)) {
            return true;
        }

        // Fallback to Express session validation
        return $this->expressSessionService->validateCredentials($credentials);
    }

    /**
     * Attempt to authenticate a user using the given credentials.
     */
    public function attempt(array $credentials = [], $remember = false)
    {
        // Try Laravel authentication first
        if ($this->attemptWithLaravel($credentials, $remember)) {
            return true;
        }

        // Try Express session authentication
        return $this->attemptWithExpressSession($credentials, $remember);
    }

    /**
     * Attempt authentication with Laravel
     */
    protected function attemptWithLaravel(array $credentials, $remember)
    {
        if ($this->provider->validateCredentials($this->user(), $credentials)) {
            $this->login($this->user(), $remember);
            return true;
        }
        return false;
    }

    /**
     * Attempt authentication with Express session
     */
    protected function attemptWithExpressSession(array $credentials, $remember)
    {
        $user = $this->expressSessionService->authenticate($credentials);
        
        if ($user) {
            $this->login($user, $remember);
            return true;
        }
        
        return false;
    }

    /**
     * Log the user out of the application.
     */
    public function logout()
    {
        $user = $this->user();
        
        // Logout from Laravel session
        parent::logout();
        
        // Also logout from Express session
        if ($user) {
            $this->expressSessionService->logout($user->id);
        }
    }
}
```

## Express Session Service

### app/Services/ExpressSessionService.php
```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Illuminate\Support\Facades\Cookie;

class ExpressSessionService
{
    protected $sessionTable = 'sessions';
    protected $sessionLifetime = 1440; // 24 hours

    /**
     * Get session data from Express session table
     */
    public function getSession(string $sessionId): ?array
    {
        $session = DB::table($this->sessionTable)
            ->where('id', $sessionId)
            ->where('expires_at', '>', now())
            ->first();

        if (!$session) {
            return null;
        }

        return json_decode($session->data, true);
    }

    /**
     * Create session in Express session table
     */
    public function createSession(array $sessionData): string
    {
        $sessionId = $this->generateSessionId();
        $expiresAt = now()->addMinutes($this->sessionLifetime);

        DB::table($this->sessionTable)->insert([
            'id' => $sessionId,
            'data' => json_encode($sessionData),
            'expires_at' => $expiresAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $sessionId;
    }

    /**
     * Update session data
     */
    public function updateSession(string $sessionId, array $sessionData): bool
    {
        return DB::table($this->sessionTable)
            ->where('id', $sessionId)
            ->update([
                'data' => json_encode($sessionData),
                'updated_at' => now(),
            ]) > 0;
    }

    /**
     * Delete session
     */
    public function deleteSession(string $sessionId): bool
    {
        return DB::table($this->sessionTable)
            ->where('id', $sessionId)
            ->delete() > 0;
    }

    /**
     * Authenticate user with Express credentials
     */
    public function authenticate(array $credentials): ?User
    {
        $user = User::where('username', $credentials['username'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return null;
        }

        return $user;
    }

    /**
     * Validate credentials against Express system
     */
    public function validateCredentials(array $credentials): bool
    {
        $user = $this->authenticate($credentials);
        return $user !== null;
    }

    /**
     * Logout user from Express session
     */
    public function logout(int $userId): void
    {
        // Find and delete all sessions for this user
        $sessions = DB::table($this->sessionTable)->get();
        
        foreach ($sessions as $session) {
            $data = json_decode($session->data, true);
            if (isset($data['user_id']) && $data['user_id'] == $userId) {
                $this->deleteSession($session->id);
            }
        }
    }

    /**
     * Migrate user session to Laravel
     */
    public function migrateUserSession(int $userId): bool
    {
        $user = User::find($userId);
        if (!$user) {
            return false;
        }

        // Create Laravel session
        $sessionData = [
            'user_id' => $userId,
            'username' => $user->username,
            'migrated_at' => now()->toISOString(),
        ];

        $sessionId = $this->createSession($sessionData);
        
        // Set cookie for frontend
        Cookie::queue('laravel_session', $sessionId, $this->sessionLifetime);
        
        return true;
    }

    /**
     * Generate secure session ID
     */
    protected function generateSessionId(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Clean expired sessions
     */
    public function cleanExpiredSessions(): int
    {
        return DB::table($this->sessionTable)
            ->where('expires_at', '<', now())
            ->delete();
    }

    /**
     * Get session statistics
     */
    public function getSessionStats(): array
    {
        $totalSessions = DB::table($this->sessionTable)->count();
        $activeSessions = DB::table($this->sessionTable)
            ->where('expires_at', '>', now())
            ->count();
        $expiredSessions = $totalSessions - $activeSessions;

        return [
            'total_sessions' => $totalSessions,
            'active_sessions' => $activeSessions,
            'expired_sessions' => $expiredSessions,
        ];
    }
}
```

## Custom Guard Provider

### app/Auth/SessionBridgeGuardProvider.php
```php
<?php

namespace App\Auth;

use Illuminate\Auth\GuardManager;
use Illuminate\Contracts\Auth\Guard;
use App\Services\ExpressSessionService;

class SessionBridgeGuardProvider extends GuardManager
{
    /**
     * Create a new guard instance.
     */
    protected function createSessionBridgeDriver($name, $config): Guard
    {
        $provider = $this->createUserProvider($config['provider'] ?? null);
        $request = $this->app['request'];
        $expressSessionService = $this->app[ExpressSessionService::class];

        return new SessionBridgeGuard(
            $name,
            $provider,
            $request,
            $expressSessionService
        );
    }

    /**
     * Get the default authentication driver name.
     */
    public function getDefaultDriver()
    {
        return $this->app['config']['auth.defaults.guard'];
    }
}
```

## Authentication Controller

### app/Http/Controllers/Auth/DualAuthController.php
```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use App\Services\AuthMigrationService;
use App\Services\ExpressSessionService;
use App\Services\SanctumTokenService;

class DualAuthController extends Controller
{
    protected $expressSessionService;
    protected $sanctumTokenService;

    public function __construct(
        ExpressSessionService $expressSessionService,
        SanctumTokenService $sanctumTokenService
    ) {
        $this->expressSessionService = $expressSessionService;
        $this->sanctumTokenService = $sanctumTokenService;
    }

    /**
     * Handle login request
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $authMode = AuthMigrationService::getAuthMode();

        switch ($authMode) {
            case 'sanctum_only':
                return $this->loginWithSanctum($credentials);
            case 'dual':
                return $this->loginWithDualAuth($credentials);
            default:
                return $this->loginWithSession($credentials);
        }
    }

    /**
     * Login with Sanctum tokens only
     */
    protected function loginWithSanctum(array $credentials): JsonResponse
    {
        if (Auth::guard('sanctum')->attempt($credentials)) {
            $user = Auth::guard('sanctum')->user();
            $token = $user->createToken('auth-token')->plainTextToken;

            return response()->json([
                'success' => true,
                'user' => $user,
                'token' => $token,
                'auth_mode' => 'sanctum',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Invalid credentials',
        ], 401);
    }

    /**
     * Login with dual authentication
     */
    protected function loginWithDualAuth(array $credentials): JsonResponse
    {
        // Try Laravel session first
        if (Auth::guard('web')->attempt($credentials)) {
            $user = Auth::guard('web')->user();
            
            return response()->json([
                'success' => true,
                'user' => $user,
                'auth_mode' => 'session',
                'session_id' => session()->getId(),
            ]);
        }

        // Fallback to Express session
        $user = $this->expressSessionService->authenticate($credentials);
        
        if ($user) {
            // Create Express session
            $sessionData = [
                'user_id' => $user->id,
                'username' => $user->username,
                'login_at' => now()->toISOString(),
            ];
            
            $sessionId = $this->expressSessionService->createSession($sessionData);
            
            return response()->json([
                'success' => true,
                'user' => $user,
                'auth_mode' => 'express_session',
                'session_id' => $sessionId,
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Invalid credentials',
        ], 401);
    }

    /**
     * Login with session only
     */
    protected function loginWithSession(array $credentials): JsonResponse
    {
        if (Auth::guard('web')->attempt($credentials)) {
            $user = Auth::guard('web')->user();
            
            return response()->json([
                'success' => true,
                'user' => $user,
                'auth_mode' => 'session',
                'session_id' => session()->getId(),
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Invalid credentials',
        ], 401);
    }

    /**
     * Handle logout request
     */
    public function logout(Request $request): JsonResponse
    {
        $authMode = AuthMigrationService::getAuthMode();
        $user = Auth::user();

        switch ($authMode) {
            case 'sanctum_only':
                if ($user) {
                    $user->currentAccessToken()->delete();
                }
                break;
            case 'dual':
                if ($user) {
                    // Logout from both systems
                    Auth::logout();
                    $this->expressSessionService->logout($user->id);
                }
                break;
            default:
                Auth::logout();
                break;
        }

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * Get current user
     */
    public function me(Request $request): JsonResponse
    {
        $user = Auth::user();
        $authMode = AuthMigrationService::getAuthMode();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Not authenticated',
            ], 401);
        }

        return response()->json([
            'success' => true,
            'user' => $user,
            'auth_mode' => $authMode,
            'migration_flags' => AuthMigrationService::getFeatureFlags(),
        ]);
    }

    /**
     * Migrate user to Sanctum tokens
     */
    public function migrateToSanctum(Request $request): JsonResponse
    {
        if (!AuthMigrationService::isDualMode()) {
            return response()->json([
                'success' => false,
                'message' => 'Not in dual mode',
            ], 400);
        }

        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Not authenticated',
            ], 401);
        }

        // Create Sanctum token
        $token = $this->sanctumTokenService->createToken($user);
        
        // Migrate session
        $this->expressSessionService->migrateUserSession($user->id);
        
        return response()->json([
            'success' => true,
            'token' => $token,
            'message' => 'Migration completed',
        ]);
    }
}
```

## Sanctum Token Service

### app/Services/SanctumTokenService.php
```php
<?php

namespace App\Services;

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Support\Facades\Hash;

class SanctumTokenService
{
    /**
     * Create Sanctum token for user
     */
    public function createToken(User $user, string $name = 'auth-token'): string
    {
        // Revoke existing tokens
        $this->revokeAllTokens($user);
        
        // Create new token
        $token = $user->createToken($name);
        
        return $token->plainTextToken;
    }

    /**
     * Revoke all tokens for user
     */
    public function revokeAllTokens(User $user): void
    {
        $user->tokens()->delete();
    }

    /**
     * Revoke specific token
     */
    public function revokeToken(string $token): bool
    {
        $personalAccessToken = PersonalAccessToken::findToken($token);
        
        if ($personalAccessToken) {
            $personalAccessToken->delete();
            return true;
        }
        
        return false;
    }

    /**
     * Validate token
     */
    public function validateToken(string $token): ?User
    {
        $personalAccessToken = PersonalAccessToken::findToken($token);
        
        if (!$personalAccessToken) {
            return null;
        }

        return $personalAccessToken->tokenable;
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

        return [
            'total_tokens' => $totalTokens,
            'active_tokens' => $activeTokens,
            'expired_tokens' => $expiredTokens,
        ];
    }

    /**
     * Clean expired tokens
     */
    public function cleanExpiredTokens(): int
    {
        return PersonalAccessToken::where('expires_at', '<', now())->delete();
    }
}
```
