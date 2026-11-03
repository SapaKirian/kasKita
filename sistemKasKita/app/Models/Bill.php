<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bill extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'period_month' => 'integer',
        'period_year' => 'integer',
        'amount' => 'decimal:2',
        'due_date' => 'date',
    ];

    public function due(): BelongsTo
    {
        return $this->belongsTo(Due::class, 'dues_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(BillRecipient::class, 'bill_id');
    }
}
