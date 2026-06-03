<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory;
    protected $fillable = [
        'first_name',
        'second_name',
        'last_name',
        'second_last_name',
        'document_type',
        'document_number',
        'birth_date',
        'gender',
        'email',
        'phone',
        'address',
        'province',
        'city',
        'sector',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
        ];
    }

    public function warranties(): HasMany
    {
        return $this->hasMany(Warranty::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim(preg_replace('/\s+/', ' ', $this->first_name . ' ' . $this->second_name . ' ' . $this->last_name . ' ' . $this->second_last_name));
    }
}