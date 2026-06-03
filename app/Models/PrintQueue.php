<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrintQueue extends Model
{
    protected $table = 'print_queue';

    protected $fillable = [
        'label_batch_id',
        'label_id',
        'user_id',
        'zpl_content',
        'zebra_ip',
        'zebra_port',
        'status',
        'total_labels',
        'sent_labels',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at'  => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function labelBatch(): BelongsTo
    {
        return $this->belongsTo(LabelBatch::class);
    }

    public function label(): BelongsTo
    {
        return $this->belongsTo(Label::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
