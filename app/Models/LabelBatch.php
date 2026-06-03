<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LabelBatch extends Model
{
    use HasFactory;
    protected $fillable = [
        'product_id',
        'internal_batch_code',
        'customer_batch_number',
        'customer_batch_date',
        'quantity',
        'operator',
        'observations',
        'serial_from',
        'serial_to',
        'generated_by_user_id',
        'generated_at',
        'printed_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'customer_batch_date' => 'date',
            'generated_at'        => 'datetime',
            'printed_at'          => 'datetime',
            'quantity'            => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by_user_id');
    }

    public function labels(): HasMany
    {
        return $this->hasMany(Label::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(LabelLog::class);
    }

    public function printQueue(): HasMany
    {
        return $this->hasMany(PrintQueue::class, 'label_batch_id');
    }
}