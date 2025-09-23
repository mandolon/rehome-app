# Authentication Migration Feature Flag Configuration

## Environment Configuration

### .env Configuration
```bash
# Authentication Migration Feature Flags
AUTH_SANCTUM_ONLY=false
AUTH_MIGRATION_MODE=dual
AUTH_SESSION_BRIDGE=true

# Session Configuration
SESSION_DRIVER=database
SESSION_LIFETIME=1440
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=false
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax

# Sanctum Configuration
SANCTUM_STATEFUL_DOMAINS=localhost,127.0.0.1
SANCTUM_GUARD=web
SANCTUM_EXPIRATION=null
SANCTUM_TOKEN_PREFIX=

# CORS Configuration
CORS_ALLOWED_ORIGINS=http://localhost:3000,http://127.0.0.1:3000
CORS_ALLOWED_METHODS=GET,POST,PUT,DELETE,PATCH,OPTIONS
CORS_ALLOWED_HEADERS=Content-Type,Authorization,X-Requested-With,X-CSRF-TOKEN
CORS_SUPPORTS_CREDENTIALS=true

# CSRF Configuration
CSRF_COOKIE_NAME=XSRF-TOKEN
CSRF_COOKIE_PATH=/
CSRF_COOKIE_DOMAIN=null
CSRF_COOKIE_SECURE=false
CSRF_COOKIE_HTTP_ONLY=false
CSRF_COOKIE_SAME_SITE=lax
```

### Laravel Config Files

#### config/auth.php
```php
<?php

return [
    'defaults' => [
        'guard' => env('AUTH_SANCTUM_ONLY', false) ? 'sanctum' : 'web',
        'passwords' => 'users',
    ],

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
        'sanctum' => [
            'driver' => 'sanctum',
            'provider' => 'users',
        ],
        'session_bridge' => [
            'driver' => 'session_bridge',
            'provider' => 'users',
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => App\Models\User::class,
        ],
    ],

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],
];
```

#### config/sanctum.php
```php
<?php

use Laravel\Sanctum\Sanctum;

return [
    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', sprintf(
        '%s%s',
        'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1',
        Sanctum::currentApplicationUrlWithPort()
    ))),

    'guard' => ['web'],

    'expiration' => env('SANCTUM_EXPIRATION', null),

    'token_retrieval' => [
        'header' => env('SANCTUM_TOKEN_RETRIEVAL_HEADER', 'Authorization'),
        'query' => env('SANCTUM_TOKEN_RETRIEVAL_QUERY', 'token'),
    ],

    'middleware' => [
        'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies' => Illuminate\Cookie\Middleware\EncryptCookies::class,
        'validate_csrf_token' => Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ],
];
```

#### config/session.php
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
    'secure' => env('SESSION_SECURE_COOKIE'),
    'http_only' => true,
    'same_site' => 'lax',
];
```

## Feature Flag Service

### app/Services/AuthMigrationService.php
```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Cache;

class AuthMigrationService
{
    const CACHE_KEY = 'auth_migration_state';
    const CACHE_TTL = 3600; // 1 hour

    /**
     * Check if Sanctum-only authentication is enabled
     */
    public static function isSanctumOnly(): bool
    {
        return env('AUTH_SANCTUM_ONLY', false) === true;
    }

    /**
     * Check if dual authentication mode is enabled
     */
    public static function isDualMode(): bool
    {
        return env('AUTH_MIGRATION_MODE', 'dual') === 'dual';
    }

    /**
     * Check if session bridge is enabled
     */
    public static function isSessionBridgeEnabled(): bool
    {
        return env('AUTH_SESSION_BRIDGE', true) === true;
    }

    /**
     * Get current authentication mode
     */
    public static function getAuthMode(): string
    {
        if (self::isSanctumOnly()) {
            return 'sanctum_only';
        }
        
        if (self::isDualMode()) {
            return 'dual';
        }
        
        return 'session_only';
    }

    /**
     * Get authentication guard to use
     */
    public static function getAuthGuard(): string
    {
        if (self::isSanctumOnly()) {
            return 'sanctum';
        }
        
        if (self::isDualMode() && self::isSessionBridgeEnabled()) {
            return 'session_bridge';
        }
        
        return 'web';
    }

    /**
     * Check if migration is in progress
     */
    public static function isMigrationInProgress(): bool
    {
        return Cache::get(self::CACHE_KEY . '_in_progress', false);
    }

    /**
     * Set migration in progress flag
     */
    public static function setMigrationInProgress(bool $inProgress = true): void
    {
        Cache::put(self::CACHE_KEY . '_in_progress', $inProgress, self::CACHE_TTL);
    }

    /**
     * Get migration statistics
     */
    public static function getMigrationStats(): array
    {
        return Cache::get(self::CACHE_KEY . '_stats', [
            'total_users' => 0,
            'migrated_users' => 0,
            'failed_migrations' => 0,
            'last_migration_at' => null,
        ]);
    }

    /**
     * Update migration statistics
     */
    public static function updateMigrationStats(array $stats): void
    {
        Cache::put(self::CACHE_KEY . '_stats', $stats, self::CACHE_TTL);
    }

    /**
     * Check if rollback is available
     */
    public static function canRollback(): bool
    {
        return Cache::get(self::CACHE_KEY . '_can_rollback', false);
    }

    /**
     * Set rollback availability
     */
    public static function setRollbackAvailable(bool $available = true): void
    {
        Cache::put(self::CACHE_KEY . '_can_rollback', $available, self::CACHE_TTL);
    }

    /**
     * Get feature flag configuration
     */
    public static function getFeatureFlags(): array
    {
        return [
            'sanctum_only' => self::isSanctumOnly(),
            'dual_mode' => self::isDualMode(),
            'session_bridge' => self::isSessionBridgeEnabled(),
            'auth_mode' => self::getAuthMode(),
            'auth_guard' => self::getAuthGuard(),
            'migration_in_progress' => self::isMigrationInProgress(),
            'can_rollback' => self::canRollback(),
        ];
    }
}
```

## Middleware for Feature Flags

### app/Http/Middleware/AuthMigrationMiddleware.php
```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\AuthMigrationService;
use Symfony\Component\HttpFoundation\Response;

class AuthMigrationMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Add feature flags to request
        $request->merge([
            'auth_migration_flags' => AuthMigrationService::getFeatureFlags()
        ]);

        // Add headers for frontend
        $response = $next($request);
        
        $response->headers->set('X-Auth-Mode', AuthMigrationService::getAuthMode());
        $response->headers->set('X-Auth-Guard', AuthMigrationService::getAuthGuard());
        $response->headers->set('X-Migration-In-Progress', AuthMigrationService::isMigrationInProgress() ? 'true' : 'false');
        
        return $response;
    }
}
```

## Configuration Commands

### app/Console/Commands/AuthMigrationCommand.php
```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AuthMigrationService;
use Illuminate\Support\Facades\Artisan;

class AuthMigrationCommand extends Command
{
    protected $signature = 'auth:migrate {action : enable|disable|status|rollback}';
    protected $description = 'Manage authentication migration feature flags';

    public function handle()
    {
        $action = $this->argument('action');

        switch ($action) {
            case 'enable':
                $this->enableSanctumOnly();
                break;
            case 'disable':
                $this->disableSanctumOnly();
                break;
            case 'status':
                $this->showStatus();
                break;
            case 'rollback':
                $this->rollback();
                break;
            default:
                $this->error('Invalid action. Use: enable, disable, status, or rollback');
                return 1;
        }

        return 0;
    }

    private function enableSanctumOnly()
    {
        $this->info('Enabling Sanctum-only authentication...');
        
        // Update environment
        $this->updateEnvFile('AUTH_SANCTUM_ONLY', 'true');
        $this->updateEnvFile('AUTH_MIGRATION_MODE', 'sanctum');
        
        // Set migration in progress
        AuthMigrationService::setMigrationInProgress(true);
        AuthMigrationService::setRollbackAvailable(true);
        
        // Clear config cache
        Artisan::call('config:clear');
        
        $this->info('Sanctum-only authentication enabled.');
        $this->warn('Remember to migrate user sessions and update frontend!');
    }

    private function disableSanctumOnly()
    {
        $this->info('Disabling Sanctum-only authentication...');
        
        // Update environment
        $this->updateEnvFile('AUTH_SANCTUM_ONLY', 'false');
        $this->updateEnvFile('AUTH_MIGRATION_MODE', 'dual');
        
        // Clear migration flags
        AuthMigrationService::setMigrationInProgress(false);
        AuthMigrationService::setRollbackAvailable(false);
        
        // Clear config cache
        Artisan::call('config:clear');
        
        $this->info('Sanctum-only authentication disabled. Using dual mode.');
    }

    private function showStatus()
    {
        $flags = AuthMigrationService::getFeatureFlags();
        $stats = AuthMigrationService::getMigrationStats();
        
        $this->info('Authentication Migration Status:');
        $this->table(
            ['Feature', 'Status'],
            [
                ['Sanctum Only', $flags['sanctum_only'] ? 'Enabled' : 'Disabled'],
                ['Dual Mode', $flags['dual_mode'] ? 'Enabled' : 'Disabled'],
                ['Session Bridge', $flags['session_bridge'] ? 'Enabled' : 'Disabled'],
                ['Auth Mode', $flags['auth_mode']],
                ['Auth Guard', $flags['auth_guard']],
                ['Migration In Progress', $flags['migration_in_progress'] ? 'Yes' : 'No'],
                ['Can Rollback', $flags['can_rollback'] ? 'Yes' : 'No'],
            ]
        );
        
        if (!empty($stats['total_users'])) {
            $this->info('Migration Statistics:');
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Total Users', $stats['total_users']],
                    ['Migrated Users', $stats['migrated_users']],
                    ['Failed Migrations', $stats['failed_migrations']],
                    ['Last Migration', $stats['last_migration_at'] ?? 'Never'],
                ]
            );
        }
    }

    private function rollback()
    {
        if (!AuthMigrationService::canRollback()) {
            $this->error('Rollback is not available. Migration may have completed.');
            return 1;
        }
        
        $this->warn('Rolling back authentication migration...');
        
        // Disable Sanctum-only mode
        $this->disableSanctumOnly();
        
        // Clear migration data
        AuthMigrationService::setMigrationInProgress(false);
        AuthMigrationService::setRollbackAvailable(false);
        
        $this->info('Rollback completed. Using dual authentication mode.');
    }

    private function updateEnvFile($key, $value)
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
