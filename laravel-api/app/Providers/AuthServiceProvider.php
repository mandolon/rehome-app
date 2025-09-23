<?php

namespace App\Providers;

use App\Models\Task;
use App\Models\TaskMessage;
use App\Policies\TaskPolicy;
use App\Policies\TaskMessagePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Task::class => TaskPolicy::class,
        TaskMessage::class => TaskMessagePolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Define any additional gates here if needed
        Gate::define('view-all-tasks', function ($user) {
            return $user->role === 'admin';
        });

        Gate::define('force-delete-tasks', function ($user) {
            return $user->role === 'admin';
        });
    }
}