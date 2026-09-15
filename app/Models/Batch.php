<?php

namespace App\Models;

use App\Enums\BatchStatus;
use App\Enums\DeliveryChannel;
use Illuminate\Bus\Batch as BusBatch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Bus;

/**
 * One upload of a participant spreadsheet against a template.
 *
 * @property BatchStatus $status
 * @property list<string> $deliver_via
 * @property array<string, mixed> $fixed_values
 * @property array<string, string|null> $column_map
 */
class Batch extends Model
{
    use HasFactory;

    protected $fillable = [
        'template_id',
        'name',
        'status',
        'deliver_via',
        'fixed_values',
        'column_map',
        'source_path',
        'source_original_name',
        'total_rows',
        'rendered_count',
        'render_failed_count',
        'email_sent_count',
        'email_failed_count',
        'whatsapp_sent_count',
        'whatsapp_failed_count',
        'zip_path',
        'error_report_path',
        'bus_batch_id',
        'error_message',
        'created_by',
        'started_at',
        'finished_at',
    ];

    protected $attributes = [
        'status' => BatchStatus::Draft->value,
        'deliver_via' => '[]',
        'fixed_values' => '{}',
        'column_map' => '{}',
    ];

    protected function casts(): array
    {
        return [
            'status' => BatchStatus::class,
            'deliver_via' => 'array',
            'fixed_values' => 'array',
            'column_map' => 'array',
            'total_rows' => 'integer',
            'rendered_count' => 'integer',
            'render_failed_count' => 'integer',
            'email_sent_count' => 'integer',
            'email_failed_count' => 'integer',
            'whatsapp_sent_count' => 'integer',
            'whatsapp_failed_count' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /* ----------------------------------------------------------------- */
    /*  Relationships */
    /* ----------------------------------------------------------------- */

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', array_map(fn (BatchStatus $s) => $s->value, BatchStatus::active()));
    }

    /* ----------------------------------------------------------------- */
    /*  Helpers */
    /* ----------------------------------------------------------------- */

    public function markStatus(BatchStatus $status, ?string $error = null): void
    {
        $this->forceFill([
            'status' => $status,
            'error_message' => $error ?? $this->error_message,
            'started_at' => $this->started_at ?? ($status->isActive() ? now() : null),
            'finished_at' => $status->isTerminal() ? now() : null,
        ])->save();
    }

    public function isCancelled(): bool
    {
        return $this->status === BatchStatus::Cancelled;
    }

    public function deliversVia(DeliveryChannel $channel): bool
    {
        return in_array($channel->value, $this->deliver_via ?? [], true);
    }

    /** Rendered + failed as a share of total rows, 0–100. */
    public function progressPercent(): int
    {
        if ($this->total_rows === 0) {
            return $this->status->isTerminal() ? 100 : 0;
        }

        $done = $this->rendered_count + $this->render_failed_count;

        return (int) min(100, round($done / $this->total_rows * 100));
    }

    public function hasFailures(): bool
    {
        return $this->render_failed_count > 0
            || $this->email_failed_count > 0
            || $this->whatsapp_failed_count > 0;
    }

    public function busBatch(): ?BusBatch
    {
        return $this->bus_batch_id ? Bus::findBatch($this->bus_batch_id) : null;
    }

    /** Directory (on the certificates disk) holding this batch's files. */
    public function directory(): string
    {
        return 'batches/'.$this->getKey();
    }
}
