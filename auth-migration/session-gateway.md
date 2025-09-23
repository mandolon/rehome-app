# Laravel Session Gateway Implementation

## Session Gateway Middleware

### app/Http/Middleware/SessionGatewayMiddleware.php
```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\ExpressSessionService;
use App\Services\AuthMigrationService;
use Symfony\Component\HttpFoundation\Response;

class SessionGatewayMiddleware
{
    protected $expressSessionService;

    public function __construct(ExpressSessionService $expressSessionService)
    {
        $this->expressSessionService = $expressSessionService;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only process if session bridge is enabled
        if (!AuthMigrationService::isSessionBridgeEnabled()) {
            return $next($request);
        }

        // Check if user is already authenticated
        if (Auth::check()) {
            return $next($request);
        }

        // Try to authenticate via Express session
        $this->authenticateViaExpressSession($request);

        return $next($request);
    }

    /**
     * Authenticate user via Express session
     */
    protected function authenticateViaExpressSession(Request $request): void
    {
        $sessionId = $this->getExpressSessionId($request);
        
        if (!$sessionId) {
            return;
        }

        $sessionData = $this->expressSessionService->getSession($sessionId);
        
        if (!$sessionData || !isset($sessionData['user_id'])) {
            return;
        }

        $user = \App\Models\User::find($sessionData['user_id']);
        
        if ($user) {
            // Log in user to Laravel session
            Auth::login($user, false);
            
            // Update session data
            $sessionData['laravel_authenticated_at'] = now()->toISOString();
            $this->expressSessionService->updateSession($sessionId, $sessionData);
        }
    }

    /**
     * Get Express session ID from request
     */
    protected function getExpressSessionId(Request $request): ?string
    {
        // Try different cookie names
        $cookieNames = [
            'connect.sid', // Express default
            'express_session',
            'session',
            'laravel_session',
        ];

        foreach ($cookieNames as $cookieName) {
            $sessionId = $request->cookie($cookieName);
            if ($sessionId) {
                return $sessionId;
            }
        }

        // Try Authorization header
        $authHeader = $request->header('Authorization');
        if ($authHeader && str_starts_with($authHeader, 'Session ')) {
            return substr($authHeader, 8);
        }

        return null;
    }
}
```

## API Gateway Controller

### app/Http/Controllers/ApiGatewayController.php
```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use App\Services\AuthMigrationService;
use App\Services\ExpressSessionService;

class ApiGatewayController extends Controller
{
    protected $expressSessionService;
    protected $expressBaseUrl;

    public function __construct(ExpressSessionService $expressSessionService)
    {
        $this->expressSessionService = $expressSessionService;
        $this->expressBaseUrl = env('EXPRESS_API_URL', 'http://localhost:5000');
    }

    /**
     * Proxy requests to Express API
     */
    public function proxy(Request $request, string $path): JsonResponse
    {
        $authMode = AuthMigrationService::getAuthMode();
        
        // If in Sanctum-only mode, don't proxy
        if ($authMode === 'sanctum_only') {
            return response()->json([
                'error' => 'Endpoint not available in Sanctum-only mode',
            ], 404);
        }

        // Prepare Express request
        $expressRequest = $this->prepareExpressRequest($request, $path);
        
        // Make request to Express API
        $response = Http::withHeaders($expressRequest['headers'])
            ->withCookies($expressRequest['cookies'])
            ->send($request->method(), $expressRequest['url'], $expressRequest['data']);

        // Process response
        return $this->processExpressResponse($response, $request);
    }

    /**
     * Prepare request for Express API
     */
    protected function prepareExpressRequest(Request $request, string $path): array
    {
        $url = rtrim($this->expressBaseUrl, '/') . '/api/' . ltrim($path, '/');
        
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'User-Agent' => $request->userAgent(),
        ];

        // Add Express session cookie
        $sessionId = $this->getExpressSessionId($request);
        if ($sessionId) {
            $headers['Cookie'] = 'connect.sid=' . $sessionId;
        }

        // Add CORS headers
        $headers['Origin'] = $request->header('Origin');
        $headers['Referer'] = $request->header('Referer');

        $cookies = [];
        foreach ($request->cookies->all() as $name => $value) {
            $cookies[$name] = $value;
        }

        $data = [];
        if ($request->isMethod('POST') || $request->isMethod('PUT') || $request->isMethod('PATCH')) {
            $data = $request->all();
        }

        return [
            'url' => $url,
            'headers' => $headers,
            'cookies' => $cookies,
            'data' => $data,
        ];
    }

    /**
     * Process response from Express API
     */
    protected function processExpressResponse($response, Request $request): JsonResponse
    {
        $statusCode = $response->status();
        $data = $response->json();
        $headers = $response->headers();

        // Handle session updates
        if ($headers->has('Set-Cookie')) {
            $this->updateLaravelSession($headers->get('Set-Cookie'), $request);
        }

        // Add migration headers
        $laravelResponse = response()->json($data, $statusCode);
        $laravelResponse->headers->set('X-Auth-Mode', AuthMigrationService::getAuthMode());
        $laravelResponse->headers->set('X-Gateway-Proxy', 'express');

        return $laravelResponse;
    }

    /**
     * Get Express session ID
     */
    protected function getExpressSessionId(Request $request): ?string
    {
        $sessionId = $request->cookie('connect.sid');
        
        if (!$sessionId) {
            // Try to get from Laravel session
            $sessionId = session('express_session_id');
        }

        return $sessionId;
    }

    /**
     * Update Laravel session with Express session data
     */
    protected function updateLaravelSession(array $setCookies, Request $request): void
    {
        foreach ($setCookies as $cookie) {
            if (str_contains($cookie, 'connect.sid=')) {
                preg_match('/connect\.sid=([^;]+)/', $cookie, $matches);
                if (isset($matches[1])) {
                    session(['express_session_id' => $matches[1]]);
                }
            }
        }
    }

    /**
     * Health check for Express API
     */
    public function healthCheck(): JsonResponse
    {
        try {
            $response = Http::timeout(5)->get($this->expressBaseUrl . '/health');
            
            return response()->json([
                'express_api' => [
                    'status' => $response->successful() ? 'healthy' : 'unhealthy',
                    'response_time' => $response->transferStats->getHandlerStat('total_time'),
                    'status_code' => $response->status(),
                ],
                'laravel_api' => [
                    'status' => 'healthy',
                    'auth_mode' => AuthMigrationService::getAuthMode(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'express_api' => [
                    'status' => 'unhealthy',
                    'error' => $e->getMessage(),
                ],
                'laravel_api' => [
                    'status' => 'healthy',
                    'auth_mode' => AuthMigrationService::getAuthMode(),
                ],
            ], 503);
        }
    }
}
```

## Route Configuration

### routes/api.php (Updated)
```php
<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\DualAuthController;
use App\Http\Controllers\ApiGatewayController;
use App\Services\AuthMigrationService;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Authentication routes
Route::prefix('auth')->group(function () {
    Route::post('/login', [DualAuthController::class, 'login']);
    Route::post('/logout', [DualAuthController::class, 'logout']);
    Route::get('/me', [DualAuthController::class, 'me']);
    Route::post('/migrate-to-sanctum', [DualAuthController::class, 'migrateToSanctum']);
});

// Health check
Route::get('/health', [ApiGatewayController::class, 'healthCheck']);

// Conditional routing based on auth mode
$authMode = AuthMigrationService::getAuthMode();

if ($authMode === 'dual' || $authMode === 'session_only') {
    // Proxy routes to Express API
    Route::any('/{path}', [ApiGatewayController::class, 'proxy'])
        ->where('path', '.*')
        ->middleware(['session.gateway']);
} else {
    // Laravel-only routes
    Route::middleware(['auth:sanctum'])->group(function () {
        // Task routes
        Route::apiResource('tasks', TaskController::class);
        Route::patch('tasks/{task}/status', [TaskController::class, 'updateStatus']);
        Route::post('tasks/{task}/archive', [TaskController::class, 'archive']);
        Route::post('tasks/{task}/restore', [TaskController::class, 'restore']);
        Route::delete('tasks/{task}/permanent', [TaskController::class, 'permanentDelete']);
        
        // Task messages
        Route::apiResource('tasks.messages', TaskMessageController::class);
        
        // Project routes
        Route::apiResource('projects', ProjectController::class);
        Route::get('projects/{project}/tasks', [ProjectController::class, 'tasks']);
        
        // User routes
        Route::apiResource('users', UserController::class);
        Route::get('user', [UserController::class, 'me']);
        
        // Search routes
        Route::get('search', [SearchController::class, 'search']);
        
        // Work records
        Route::apiResource('work-records', WorkRecordController::class);
        
        // Trash routes
        Route::apiResource('trash', TrashController::class);
        Route::post('trash/{item}/restore', [TrashController::class, 'restore']);
        Route::delete('trash', [TrashController::class, 'empty']);
    });
}
```

## Session Bridge Service Provider

### app/Providers/SessionBridgeServiceProvider.php
```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Auth\AuthManager;
use App\Auth\SessionBridgeGuard;
use App\Services\ExpressSessionService;

class SessionBridgeServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(ExpressSessionService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->app->make(AuthManager::class)->extend('session_bridge', function ($app, $name, $config) {
            $provider = $app['auth']->createUserProvider($config['provider'] ?? null);
            $request = $app['request'];
            $expressSessionService = $app[ExpressSessionService::class];

            return new SessionBridgeGuard(
                $name,
                $provider,
                $request,
                $expressSessionService
            );
        });
    }
}
```

## CORS Configuration

### config/cors.php
```php
<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:3000,http://127.0.0.1:3000')),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => ['X-Auth-Mode', 'X-Auth-Guard', 'X-Migration-In-Progress'],
    'max_age' => 0,
    'supports_credentials' => true,
];
```

## CSRF Configuration

### app/Http/Middleware/VerifyCsrfToken.php
```php
<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     */
    protected $except = [
        'api/auth/login',
        'api/auth/logout',
        'api/auth/migrate-to-sanctum',
        'api/health',
        // Add Express API proxy routes
        'api/*',
    ];

    /**
     * Determine if the session and input CSRF tokens match.
     */
    protected function tokensMatch($request): bool
    {
        // Skip CSRF for API routes in dual mode
        if ($request->is('api/*') && app(\App\Services\AuthMigrationService::class)->isDualMode()) {
            return true;
        }

        return parent::tokensMatch($request);
    }
}
```

## Database Migration for Sessions

### database/migrations/create_sessions_table.php
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
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sessions');
    }
};
```

## Configuration Updates

### config/app.php (Add to providers)
```php
'providers' => [
    // ... other providers
    App\Providers\SessionBridgeServiceProvider::class,
],
```

### app/Http/Kernel.php (Add middleware)
```php
protected $middlewareGroups = [
    'web' => [
        // ... existing middleware
        \App\Http\Middleware\SessionGatewayMiddleware::class,
    ],
    'api' => [
        // ... existing middleware
        \App\Http\Middleware\AuthMigrationMiddleware::class,
        \App\Http\Middleware\SessionGatewayMiddleware::class,
    ],
];
```
