<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
// use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    protected $guarded = [];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function cohort(): BelongsTo
    {
        return $this->belongsTo(Cohort::class);
    }

    // Relasi ke kelas
    public function classroom(): BelongsTo
    {
        return $this->belongsTo(ClassRoom::class, 'class_id');
    }

    // Pengguna yang dibuat oleh bendahara
    public function createdUsers(): HasMany
    {
        return $this->hasMany(User::class, 'created_by');
    }

    // Bendahara yang membuat akun
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // public function classMemberships(): HasMany
    // {
    //     return $this->hasMany(ClassMember::class);
    // }

    public function billsCreated(): HasMany
    {
        return $this->hasMany(Bill::class, 'created_by');
    }

    public function paymentsMade(): HasMany
    {
        return $this->hasMany(Payment::class, 'payer_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(UserNotification::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function reportExports(): HasMany
    {
        return $this->hasMany(ReportExport::class);
    }
    // public function classrooms(): BelongsToMany
    // {
    //     return $this->belongsToMany(
    //         ClassRoom::class,
    //         'class_members',
    //         'user_id',
    //         'class_id'
    //     )->withPivot(['joined_at', 'is_active'])
    //         ->withTimestamps();
    // }

}
