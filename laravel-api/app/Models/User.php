<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'username',
        'email',
        'password',
        'role',
        'first_name',
        'last_name',
        'avatar',
        'status',
        'bio',
        'company',
        'last_active',
        'notifications_muted',
        'show_online_status',
        'show_last_active',
        'avatar_color'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_active' => 'datetime',
        'notifications_muted' => 'boolean',
        'show_online_status' => 'boolean',
        'show_last_active' => 'boolean',
        'password' => 'hashed',
    ];

    // Relationships
    public function createdTasks()
    {
        return $this->hasMany(Task::class, 'created_by');
    }

    public function taskMessages()
    {
        return $this->hasMany(TaskMessage::class, 'user_id');
    }

    // Accessors
    public function getFullNameAttribute()
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    public function getInitialsAttribute()
    {
        $firstName = $this->first_name ?? '';
        $lastName = $this->last_name ?? '';
        return strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1));
    }

    public function getIsOnlineAttribute()
    {
        return $this->last_active && $this->last_active->diffInMinutes(now()) <= 5;
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByRole($query, $role)
    {
        return $query->where('role', $role);
    }

    // Methods
    public function updateLastActive()
    {
        $this->update(['last_active' => now()]);
    }

    public function hasRole($role)
    {
        return $this->role === $role;
    }

    public function isAdmin()
    {
        return $this->hasRole('admin');
    }

    public function canManageTasks()
    {
        return in_array($this->role, ['admin', 'manager']);
    }

    public function hasProjectAccess($projectId)
    {
        // For now, implement basic project access logic
        // This should be expanded based on your project access requirements
        if ($this->isAdmin()) {
            return true;
        }
        
        // Team members have access to all projects for now
        if ($this->role === 'team') {
            return true;
        }
        
        // Clients can access projects they're assigned to
        // This would typically involve a project_users pivot table
        // For now, we'll allow access to tasks they created
        return true; // Placeholder - implement proper project access logic
    }
}