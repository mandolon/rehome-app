# Integration Tests for Authentication Migration

## Test Configuration

### tests/Feature/AuthMigrationTest.php
```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Services\AuthMigrationService;
use App\Services\TokenMigrationService;
use App\Services\RollbackService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

class AuthMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Set up test environment
        config(['auth.defaults.guard' => 'web']);
        putenv('AUTH_SANCTUM_ONLY=false');
        putenv('AUTH_MIGRATION_MODE=dual');
        putenv('AUTH_SESSION_BRIDGE=true');
    }

    /** @test */
    public function it_can_detect_auth_mode_correctly()
    {
        // Test session only mode
        putenv('AUTH_SANCTUM_ONLY=false');
        putenv('AUTH_MIGRATION_MODE=session');
        
        $this->assertEquals('session_only', AuthMigrationService::getAuthMode());
        $this->assertEquals('web', AuthMigrationService::getAuthGuard());
        
        // Test dual mode
        putenv('AUTH_MIGRATION_MODE=dual');
        
        $this->assertEquals('dual', AuthMigrationService::getAuthMode());
        $this->assertEquals('session_bridge', AuthMigrationService::getAuthGuard());
        
        // Test sanctum only mode
        putenv('AUTH_SANCTUM_ONLY=true');
        putenv('AUTH_MIGRATION_MODE=sanctum');
        
        $this->assertEquals('sanctum_only', AuthMigrationService::getAuthMode());
        $this->assertEquals('sanctum', AuthMigrationService::getAuthGuard());
    }

    /** @test */
    public function it_can_login_with_session_auth()
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'username' => $user->username,
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'user' => [
                    'id' => $user->id,
                    'username' => $user->username,
                ],
            ]);

        $this->assertAuthenticated();
    }

    /** @test */
    public function it_can_login_with_dual_auth_mode()
    {
        putenv('AUTH_MIGRATION_MODE=dual');
        
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'username' => $user->username,
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'user' => [
                    'id' => $user->id,
                    'username' => $user->username,
                ],
            ]);

        $this->assertAuthenticated();
    }

    /** @test */
    public function it_can_migrate_user_to_sanctum()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->postJson('/api/auth/migrate-to-sanctum');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertTrue($user->tokens()->exists());
    }

    /** @test */
    public function it_can_create_rollback_point()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->postJson('/api/auth/rollback/create-point', [
            'confirm' => true,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertTrue(AuthMigrationService::canRollback());
    }

    /** @test */
    public function it_can_execute_rollback()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create rollback point
        $this->postJson('/api/auth/rollback/create-point', [
            'confirm' => true,
        ]);

        // Execute rollback
        $response = $this->postJson('/api/auth/rollback/execute', [
            'confirm' => true,
            'reason' => 'Test rollback',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }

    /** @test */
    public function it_can_handle_emergency_rollback()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->postJson('/api/auth/rollback/emergency', [
            'confirm' => true,
            'reason' => 'Emergency test',
            'emergency_code' => 'EMERGENCY123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }

    /** @test */
    public function it_can_get_migration_progress()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->getJson('/api/auth/migration/progress');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'progress' => [
                    'total_users',
                    'migrated_users',
                    'failed_migrations',
                    'percentage',
                ],
                'stats' => [
                    'total_users',
                    'migrated_users',
                    'failed_migrations',
                    'migration_percentage',
                ],
            ]);
    }

    /** @test */
    public function it_can_get_rollback_info()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->getJson('/api/auth/rollback/info');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'rollback_info' => [
                    'available',
                ],
                'feature_flags' => [
                    'sanctum_only',
                    'dual_mode',
                    'session_bridge',
                    'auth_mode',
                    'auth_guard',
                ],
            ]);
    }
}
```

## Token Migration Tests

### tests/Feature/TokenMigrationTest.php
```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Services\TokenMigrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

class TokenMigrationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_migrate_single_user()
    {
        $user = User::factory()->create();
        $tokenMigrationService = app(TokenMigrationService::class);

        $result = $tokenMigrationService->migrateUser($user);

        $this->assertTrue($result['success']);
        $this->assertTrue($user->tokens()->exists());
        $this->assertNotNull($user->fresh()->migrated_to_sanctum_at);
    }

    /** @test */
    public function it_can_migrate_all_users()
    {
        $users = User::factory()->count(5)->create();
        $tokenMigrationService = app(TokenMigrationService::class);

        $stats = $tokenMigrationService->migrateAllUsers();

        $this->assertEquals(5, $stats['total_users']);
        $this->assertEquals(5, $stats['migrated_users']);
        $this->assertEquals(0, $stats['failed_migrations']);

        foreach ($users as $user) {
            $this->assertTrue($user->fresh()->tokens()->exists());
        }
    }

    /** @test */
    public function it_can_rollback_user_migration()
    {
        $user = User::factory()->create();
        $tokenMigrationService = app(TokenMigrationService::class);

        // Migrate user first
        $tokenMigrationService->migrateUser($user);
        $this->assertTrue($user->fresh()->tokens()->exists());

        // Rollback migration
        $result = $tokenMigrationService->rollbackUser($user);

        $this->assertTrue($result['success']);
        $this->assertFalse($user->fresh()->tokens()->exists());
        $this->assertNull($user->fresh()->migrated_to_sanctum_at);
    }

    /** @test */
    public function it_can_get_migration_stats()
    {
        $users = User::factory()->count(3)->create();
        $tokenMigrationService = app(TokenMigrationService::class);

        // Migrate 2 users
        $tokenMigrationService->migrateUser($users[0]);
        $tokenMigrationService->migrateUser($users[1]);

        $stats = $tokenMigrationService->getMigrationStats();

        $this->assertEquals(3, $stats['total_users']);
        $this->assertEquals(2, $stats['migrated_users']);
        $this->assertEquals(0, $stats['failed_migrations']);
        $this->assertEquals(66.67, $stats['migration_percentage']);
    }

    /** @test */
    public function it_can_cleanup_migration_data()
    {
        $user = User::factory()->create();
        $tokenMigrationService = app(TokenMigrationService::class);

        // Migrate user
        $tokenMigrationService->migrateUser($user);

        // Cleanup
        $cleaned = $tokenMigrationService->cleanupMigrationData();

        $this->assertGreaterThan(0, $cleaned);
    }
}
```

## Session Gateway Tests

### tests/Feature/SessionGatewayTest.php
```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Services\ExpressSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

class SessionGatewayTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_authenticate_via_express_session()
    {
        $user = User::factory()->create();
        $expressSessionService = app(ExpressSessionService::class);

        // Create Express session
        $sessionData = [
            'user_id' => $user->id,
            'username' => $user->username,
        ];
        $sessionId = $expressSessionService->createSession($sessionData);

        // Set cookie
        $this->withCookie('connect.sid', $sessionId);

        // Make request
        $response = $this->getJson('/api/auth/me');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'user' => [
                    'id' => $user->id,
                    'username' => $user->username,
                ],
            ]);
    }

    /** @test */
    public function it_can_proxy_requests_to_express()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Mock Express API response
        $this->mock(\Illuminate\Support\Facades\Http::class, function ($mock) {
            $mock->shouldReceive('withHeaders')
                ->andReturnSelf();
            $mock->shouldReceive('withCookies')
                ->andReturnSelf();
            $mock->shouldReceive('send')
                ->andReturn(new \Illuminate\Http\Client\Response(
                    new \GuzzleHttp\Psr7\Response(200, [], json_encode(['success' => true]))
                ));
        });

        $response = $this->getJson('/api/tasks');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    /** @test */
    public function it_can_handle_express_api_failure()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Mock Express API failure
        $this->mock(\Illuminate\Support\Facades\Http::class, function ($mock) {
            $mock->shouldReceive('withHeaders')
                ->andReturnSelf();
            $mock->shouldReceive('withCookies')
                ->andReturnSelf();
            $mock->shouldReceive('send')
                ->andThrow(new \Exception('Express API unavailable'));
        });

        $response = $this->getJson('/api/tasks');

        $response->assertStatus(500);
    }
}
```

## Frontend Integration Tests

### tests/Feature/FrontendIntegrationTest.php
```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

class FrontendIntegrationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_handle_auth_mode_switching()
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);

        // Test session mode
        putenv('AUTH_SANCTUM_ONLY=false');
        putenv('AUTH_MIGRATION_MODE=session');

        $response = $this->postJson('/api/auth/login', [
            'username' => $user->username,
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'auth_mode' => 'session',
            ]);

        // Test dual mode
        putenv('AUTH_MIGRATION_MODE=dual');

        $response = $this->postJson('/api/auth/login', [
            'username' => $user->username,
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }

    /** @test */
    public function it_can_handle_cors_headers()
    {
        $response = $this->optionsJson('/api/auth/login', [], [
            'Origin' => 'http://localhost:3000',
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'Content-Type',
        ]);

        $response->assertStatus(200)
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:3000')
            ->assertHeader('Access-Control-Allow-Methods', '*')
            ->assertHeader('Access-Control-Allow-Headers', '*');
    }

    /** @test */
    public function it_can_handle_csrf_protection()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Test CSRF protection
        $response = $this->postJson('/api/auth/logout');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }
}
```

## Test Configuration Files

### phpunit.xml (Updated)
```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="./vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true">
    <testsuites>
        <testsuite name="Unit">
            <directory suffix="Test.php">./tests/Unit</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory suffix="Test.php">./tests/Feature</directory>
        </testsuite>
        <testsuite name="Integration">
            <directory suffix="Test.php">./tests/Integration</directory>
        </testsuite>
    </testsuites>
    <source>
        <include>
            <directory suffix=".php">./app</directory>
        </include>
    </source>
    <php>
        <env name="APP_ENV" value="testing"/>
        <env name="BCRYPT_ROUNDS" value="4"/>
        <env name="CACHE_DRIVER" value="array"/>
        <env name="DB_CONNECTION" value="sqlite"/>
        <env name="DB_DATABASE" value=":memory:"/>
        <env name="MAIL_MAILER" value="array"/>
        <env name="QUEUE_CONNECTION" value="sync"/>
        <env name="SESSION_DRIVER" value="array"/>
        <env name="TELESCOPE_ENABLED" value="false"/>
        <env name="AUTH_SANCTUM_ONLY" value="false"/>
        <env name="AUTH_MIGRATION_MODE" value="dual"/>
        <env name="AUTH_SESSION_BRIDGE" value="true"/>
        <env name="EMERGENCY_ROLLBACK_CODE" value="EMERGENCY123"/>
    </php>
</phpunit>
```

### tests/TestCase.php (Updated)
```php
<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use App\Services\AuthMigrationService;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Reset auth migration state
        AuthMigrationService::setMigrationInProgress(false);
        AuthMigrationService::setRollbackAvailable(false);
    }

    protected function tearDown(): void
    {
        // Clean up after tests
        AuthMigrationService::setMigrationInProgress(false);
        AuthMigrationService::setRollbackAvailable(false);
        
        parent::tearDown();
    }
}
```

## Test Database Factories

### database/factories/UserFactory.php
```php
<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'username' => $this->faker->unique()->userName(),
            'email' => $this->faker->unique()->safeEmail(),
            'password' => Hash::make('password123'),
            'remember_token' => Str::random(10),
        ];
    }

    public function withSanctumToken(): static
    {
        return $this->afterCreating(function ($user) {
            $user->createToken('test-token');
        });
    }

    public function migrated(): static
    {
        return $this->afterCreating(function ($user) {
            $user->update([
                'migrated_to_sanctum_at' => now(),
            ]);
        });
    }
}
```

## Test Commands

### tests/Console/TestCommands.php
```php
<?php

namespace Tests\Console;

use Tests\TestCase;
use Illuminate\Support\Facades\Artisan;

class TestCommands extends TestCase
{
    /** @test */
    public function it_can_run_auth_migration_command()
    {
        $this->artisan('auth:migrate', ['action' => 'status'])
            ->assertExitCode(0);
    }

    /** @test */
    public function it_can_enable_sanctum_only_mode()
    {
        $this->artisan('auth:migrate', ['action' => 'enable'])
            ->assertExitCode(0);
    }

    /** @test */
    public function it_can_disable_sanctum_only_mode()
    {
        $this->artisan('auth:migrate', ['action' => 'disable'])
            ->assertExitCode(0);
    }

    /** @test */
    public function it_can_rollback_auth_migration()
    {
        $this->artisan('auth:migrate', ['action' => 'rollback'])
            ->assertExitCode(0);
    }
}
```

## Test Documentation

### tests/README.md
```markdown
# Authentication Migration Tests

## Test Structure

### Feature Tests
- `AuthMigrationTest.php` - Core authentication migration functionality
- `TokenMigrationTest.php` - Token migration and rollback
- `SessionGatewayTest.php` - Session gateway and Express integration
- `FrontendIntegrationTest.php` - Frontend API integration

### Unit Tests
- `AuthMigrationServiceTest.php` - Service layer testing
- `TokenMigrationServiceTest.php` - Token migration service
- `RollbackServiceTest.php` - Rollback functionality

### Integration Tests
- `FullMigrationFlowTest.php` - End-to-end migration testing
- `RollbackFlowTest.php` - Complete rollback testing

## Running Tests

### All Tests
```bash
php artisan test
```

### Specific Test Suite
```bash
php artisan test --testsuite=Feature
php artisan test --testsuite=Unit
php artisan test --testsuite=Integration
```

### Specific Test
```bash
php artisan test tests/Feature/AuthMigrationTest.php
```

### With Coverage
```bash
php artisan test --coverage
```

## Test Environment

Tests run in a clean environment with:
- In-memory SQLite database
- Array cache driver
- Array session driver
- Mocked external services

## Test Data

Tests use factories to create test data:
- `UserFactory` - User creation with various states
- `SessionFactory` - Session data creation
- `TokenFactory` - Sanctum token creation

## Mocking

External services are mocked:
- Express API calls
- HTTP requests
- File system operations
- Cache operations
```
