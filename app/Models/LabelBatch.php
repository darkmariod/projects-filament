<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LabelBatch extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (LabelBatch $batch) {
            // Generar internal_batch_code automático
            if (empty($batch->internal_batch_code) && $batch->product_id) {
                $batch->internal_batch_code = $batch->generateInternalCode();
            }

            // Auto-generar customer_batch_number si está vacío
            if (empty($batch->customer_batch_number)) {
                $prefix = 'LOTE-' . now()->format('Ym');
                $last = static::where('customer_batch_number', 'like', $prefix . '-%')
                    ->orderBy('customer_batch_number', 'desc')
                    ->first();
                $nextSequence = $last ? ((int) substr($last->customer_batch_number, -3)) + 1 : 1;
                $batch->customer_batch_number = $prefix . '-' . str_pad($nextSequence, 3, '0', STR_PAD_LEFT);
            }

            // Auto-fechas: ninguna editable por el usuario
            if (empty($batch->customer_batch_date)) {
                $batch->customer_batch_date = now();
            }

            if (empty($batch->generated_at)) {
                $batch->generated_at = now();
            }

            if (empty($batch->operator)) {
                $batch->operator = auth()->user()?->name;
            }

            if (empty($batch->generated_by_user_id)) {
                $batch->generated_by_user_id = auth()->id() ?? 1;
            }

            if (empty($batch->status)) {
                $batch->status = 'active';
            }
        });

        static::updating(function (LabelBatch $batch) {
            if ($batch->isDirty(['product_id', 'customer_batch_date'])) {
                $batch->internal_batch_code = $batch->generateInternalCode();
            }
        });
    }

    protected $fillable = [
        'product_id',
        'internal_batch_code',
        'customer_batch_number',
        'customer_batch_date',
        'quantity',
        'operator',
        'observations',
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

    /**
     * Genera el código interno con formato: {product_code_slug}-{MMYYYY}
     * Ejemplo: CR SE 080 + junio 2026 → CR-SE-080-062026
     *
     * Evita duplicados: si ya existe un lote con ese código, agrega
     * un sufijo -2, -3, etc.
     */
    public function generateInternalCode(): string
    {
        $product = $this->product;

        if (!$product) {
            return 'LOTE-' . now()->format('YmdHis');
        }

        $slug = str_replace(' ', '-', $product->product_code);
        $date = $this->customer_batch_date
            ? \Carbon\Carbon::parse($this->customer_batch_date)
            : now();

        $base = $slug . '-' . $date->format('mY');

        // Si no hay colisión, usar el código base
        if (!static::where('internal_batch_code', $base)->exists()) {
            return $base;
        }

        // Buscar el sufijo más alto existente para este código base
        $latest = static::where('internal_batch_code', 'like', $base . '-%')
            ->orderByRaw('CAST(SUBSTRING_INDEX(internal_batch_code, \'-\', -1) AS UNSIGNED) DESC')
            ->first();

        if ($latest) {
            $parts = explode('-', $latest->internal_batch_code);
            $lastSuffix = (int) end($parts);
            return $base . '-' . ($lastSuffix + 1);
        }

        return $base . '-2';
    }

    // ── Relaciones ────────────────────────────────────────────────────────

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
