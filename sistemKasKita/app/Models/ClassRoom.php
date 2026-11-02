<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassRoom extends Model
{
    use HasFactory;

    protected $table = 'classes';

    protected $guarded = [];

    // Angkatan pemilik kelas.
    public function cohort(): BelongsTo
    {
        return $this->belongsTo(Cohort::class);
    }

    // Bendahara kelas.
    public function treasurer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'treasurer_id');
    }

    // Semua pengguna yang terdaftar di kelas.
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'class_id');
    }

    // Data iuran kelas.
    public function dues(): HasMany
    {
        return $this->hasMany(Due::class, 'class_id');
    }

    public function billRecipients(): HasMany
    {
        return $this->hasMany(BillRecipient::class, 'class_id');
    }

    public function remittances(): HasMany
    {
        return $this->hasMany(Remittance::class, 'class_id');
    }
}
