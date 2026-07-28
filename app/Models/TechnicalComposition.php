<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TechnicalComposition extends Model
{
    protected $fillable = [
        'product_id',
        'commercial_name',
        'product_family',
        'cover_material',
        'springs',
        'foam_description',
        'support_material',
        'general_composition',
        'conservation_instructions',
        'legal_text',
        'inen_standard',
        'manufacturing_country',
        'manufacturer',
        'manufacturer_ruc',
        'manufacturer_address',
        'website',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}