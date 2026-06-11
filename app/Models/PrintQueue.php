<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrintQueue extends Model
{
    protected $table = 'print_queue';

    protected $fillable = [
        'label_batch_id',
        'user_id',
        'zebra_ip',
        'zebra_port',
        'connection_type',
        'printer_name',
        'status',
        'total_labels',
        'printed_labels',
        'failed_labels',
        'pause_reason',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'zebra_port'     => 'integer',
            'total_labels'   => 'integer',
            'printed_labels' => 'integer',
            'failed_labels'  => 'integer',
            'started_at'     => 'datetime',
            'finished_at'    => 'datetime',
        ];
    }

    // ── Relaciones ────────────────────────────────────────────────────────

    public function labelBatch(): BelongsTo
    {
        return $this->belongsTo(LabelBatch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PrintQueueItem::class)->orderBy('sequence');
    }

    // ── Helpers de conexión ───────────────────────────────────────────────

    public function isUsbConnection(): bool
    {
        return $this->connection_type === 'usb';
    }

    public function isNetworkConnection(): bool
    {
        return $this->connection_type !== 'usb';
    }

    // ── Scopes ────────────────────────────────────────────────────────────

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopePaused($query)
    {
        return $query->where('status', 'paused');
    }

    public function scopeProcessable($query)
    {
        return $query->whereIn('status', ['pending', 'partial']);
    }

    // ── Helpers de estado ─────────────────────────────────────────────────

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isPartial(): bool
    {
        return $this->status === 'partial';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isPaused(): bool
    {
        return $this->status === 'paused';
    }

    /**
     * Recalcular contadores desde los items.
     */
    public function recalcCounters(): void
    {
        $this->loadCount([
            'items as printed_labels' => fn($q) => $q->where('status', 'printed'),
            'items as failed_labels'  => fn($q) => $q->where('status', 'failed'),
        ]);

        $this->saveQuietly();
    }

    /**
     * Determinar el estado final según los items.
     */
    public function determineFinalStatus(): string
    {
        $total     = $this->items()->count();
        $printed   = $this->items()->where('status', 'printed')->count();
        $failed    = $this->items()->where('status', 'failed')->count();
        $cancelled = $this->items()->where('status', 'cancelled')->count();
        $pending   = $total - $printed - $failed - $cancelled;

        if ($printed === $total) {
            return 'completed';
        }

        if ($printed > 0 && ($pending > 0 || $failed > 0)) {
            return 'partial';
        }

        if ($failed === $total || $cancelled === $total) {
            return 'failed';
        }

        return 'pending';
    }
}
