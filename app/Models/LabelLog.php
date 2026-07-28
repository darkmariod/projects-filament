<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LabelLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'label_id',
        'label_batch_id',
        'user_id',
        'action',
        'description',
        'old_data',
        'new_data',
        'ip',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'old_data'   => 'array',
            'new_data'   => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function label(): BelongsTo
    {
        return $this->belongsTo(Label::class);
    }

    public function labelBatch(): BelongsTo
    {
        return $this->belongsTo(LabelBatch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}