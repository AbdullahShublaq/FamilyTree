<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FamilyMember extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'gender',
        'birth_date',
        'death_date',
        'wife_name',
        'description',
        'parent_id',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'death_date' => 'date',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(FamilyMember::class, 'parent_id')->withTrashed();
    }

    public function children(): HasMany
    {
        return $this->hasMany(FamilyMember::class, 'parent_id')->withTrashed();
    }

    public function getArabicGenderAttribute(): string
    {
        return $this->gender === 'male' ? 'ذكر' : 'أنثى';
    }

    public function getAgeAttribute(): ?int
    {
        if (!$this->birth_date) {
            return null;
        }

        $endDate = $this->death_date ?? now();
        return $this->birth_date->diffInYears($endDate);
    }
}
