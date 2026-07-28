<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Label extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (self $label) {
            if ($label->zpl_generated === null) {
                $label->zpl_generated = false;
            }
        });

        // Al eliminar una etiqueta, quitar primero su garantía (FK RESTRICT).
        // Los items de cola se borran solos (FK cascade).
        static::deleting(function (self $label) {
            $label->warranty()->delete();
        });
    }

    protected $fillable = [
        'label_batch_id',
        'product_id',
        'serial',
        'sequence_number',
        'barcode',
        'qr_url',
        'zpl_generated',
        'status',
        'printed_at',
        'registered_at',
    ];

    protected function casts(): array
    {
        return [
            'printed_at'      => 'datetime',
            'registered_at'   => 'datetime',
            'sequence_number' => 'integer',
        ];
    }

    public function labelBatch(): BelongsTo
    {
        return $this->belongsTo(LabelBatch::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warranty(): HasOne
    {
        return $this->hasOne(Warranty::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(LabelLog::class);
    }
}