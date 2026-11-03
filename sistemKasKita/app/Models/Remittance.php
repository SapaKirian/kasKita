<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Remittance extends Model
{
    use HasFactory;

    protected $guarded = [];
    protected $fillable = [
        'class_id',
        'cohort_id',
        'amount',
        'period_month',
        'period_year',
        'status',
        'submitted_by',
        'verified_by',
        'verified_at',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'period_month' => 'integer',
        'period_year' => 'integer',
        'verified_at' => 'datetime',
    ];

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(ClassRoom::class, 'class_id');
    }

    public function cohort(): BelongsTo
    {
        return $this->belongsTo(Cohort::class, 'cohort_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
    public function items(): HasMany
    {
        return $this->hasMany(RemittanceItem::class);
    }
}
