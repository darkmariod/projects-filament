<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Warranty extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Warranty $warranty) {
            if (empty($warranty->purchase_date)) {
                $warranty->purchase_date = today();
            }
            if (empty($warranty->warranty_start_date)) {
                $warranty->warranty_start_date = today();
            }
            if (empty($warranty->warranty_end_date)) {
                $label = $warranty->label()->with('product.productModel')->first();
                $years = $label?->product?->productModel?->warranty_years ?? 1;
                $warranty->warranty_end_date = ($warranty->purchase_date ?? today())->addYears($years);
            }
        });
    }

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