<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Warranty extends Model
{
    use HasFactory;
    protected $fillable = [
        'label_id',
        'customer_id',
        'store_name',
        'invoice_number',
        'purchase_date',
        'warranty_start_date',
        'warranty_end_date',
        'pdf_path',
        'status',
        'terms_accepted',
    ];

    protected function casts(): array
    {
        return [
            'purchase_date'       => 'date',
            'warranty_start_date' => 'date',
            'warranty_end_date'   => 'date',
            'terms_accepted'      => 'boolean',
        ];
    }

    public function label(): BelongsTo
    {
        return $this->belongsTo(Label::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}