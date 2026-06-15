<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    use HasFactory;
    protected $fillable = [
        'product_model_id',
        'name',
        'commercial_name',
        'product_family',
        'product_code',
        'barcode',
        'image',
        'width_cm',
        'length_cm',
        'height_cm',
        'measurements_text',
        'class',
        'plazas',
        'springs',
        'foam_description',
        'description',
        'conservation_instructions',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active'    => 'boolean',
            'width_cm'  => 'decimal:2',
            'length_cm' => 'decimal:2',
            'height_cm' => 'decimal:2',
        ];
    }

    public function productModel(): BelongsTo
    {
        return $this->belongsTo(ProductModel::class);
    }

    public function technicalComposition(): HasOne
    {
        return $this->hasOne(TechnicalComposition::class);
    }

    public function labelBatches(): HasMany
    {
        return $this->hasMany(LabelBatch::class);
    }
}