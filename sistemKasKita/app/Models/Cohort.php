<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Cohort extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function treasurer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'treasurer_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function classes(): HasMany
    {
        return $this->hasMany(ClassRoom::class);
    }

    public function dues(): HasMany
    {
        return $this->hasMany(Due::class);
    }

    public function remittances(): HasMany
    {
        return $this->hasMany(Remittance::class);
    }

    public function cashTransactions(): HasMany
    {
        return $this->hasMany(CashTransaction::class);
    }
}
