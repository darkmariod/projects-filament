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