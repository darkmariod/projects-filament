<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrintQueueItem extends Model
{
    protected $table = 'print_queue_items';

    protected $fillable = [
        'print_queue_id',
        'label_id',
        'sequence',
        'zpl_content',
        'status',
        'attempts',
        'max_attempts',
        'error_message',
        'printed_at',
    ];

    protected function casts(): array
    {
        return [
            'sequence'     => 'integer',
            'attempts'     => 'integer',
            'max_attempts' => 'integer',
            'printed_at'   => 'datetime',
        ];
    }

    public function printQueue(): BelongsTo
    {
        return $this->belongsTo(PrintQueue::class);
    }

    public function label(): BelongsTo
    {
        return $this->belongsTo(Label::class);
    }

    // ── Scopes ──────────────────────────────────────────────────────────────

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeProcessable($query)
    {
        return $query->whereIn('status', ['pending', 'printing']);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopePrinted($query)
    {
        return $query->where('status', 'printed');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    public function isPrinted(): bool
    {
        return $this->status === 'printed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function canRetry(): bool
    {
        return $this->isFailed() || $this->isPending();
    }

    public function hasReachedMaxAttempts(): bool
    {
        return $this->attempts >= $this->max_attempts;
    }

    /**
     * Incrementar intentos y marcar como failed si llegó al máximo.
     */
    public function incrementAttempt(string $errorMessage = ''): void
    {
        $this->increment('attempts');
        $this->error_message = $errorMessage;

        if ($this->hasReachedMaxAttempts()) {
            $this->status = 'failed';
        } else {
            $this->status = 'pending';
        }

        $this->save();
    }

    /**
     * Marcar como impreso exitosamente.
     */
    public function markAsPrinted(): void
    {
        $this->update([
            'status'     => 'printed',
            'printed_at' => now(),
        ]);
    }

    /**
     * Reset para reintento.
     */
    public function resetForRetry(): void
    {
        $this->update([
            'status'        => 'pending',
            'attempts'      => 0,
            'error_message' => null,
            'printed_at'    => null,
        ]);
    }
}
