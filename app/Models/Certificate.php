<?php

namespace App\Models;

use App\Certificates\Support\VerificationCode;
use App\Enums\CertificateStatus;
use App\Enums\DeliveryChannel;
use App\Enums\DeliveryStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * One issued certificate. Created before rendering so that every PDF that
 * ever leaves the system has a UUID, a short code and a verification URL.
 *
 * @property string $uuid
 * @property string $code
 * @property CertificateStatus $status
 * @property DeliveryStatus $email_status
 * @property DeliveryStatus $whatsapp_status
 * @property array<string, mixed> $data
 */
class Certificate extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_id',
        'template_id',
        'row_number',
        'recipient_name',
        'recipient_title',
        'email',
        'phone',
        'data',
        'status',
        'pdf_path',
        'render_error',
        'rendered_at',
        'email_status',
        'email_sent_at',
        'email_error',
        'whatsapp_status',
        'whatsapp_sent_at',
        'whatsapp_error',
        'revoked_at',
        'revoke_reason',
    ];

    protected $attributes = [
        'status' => CertificateStatus::Pending->value,
        'email_status' => DeliveryStatus::NotRequested->value,
        'whatsapp_status' => DeliveryStatus::NotRequested->value,
        'data' => '{}',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'row_number' => 'integer',
            'status' => CertificateStatus::class,
            'email_status' => DeliveryStatus::class,
            'whatsapp_status' => DeliveryStatus::class,
            'rendered_at' => 'datetime',
            'email_sent_at' => 'datetime',
            'whatsapp_sent_at' => 'datetime',
            'verified_count' => 'integer',
            'last_verified_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $certificate) {
            $certificate->uuid ??= (string) Str::uuid();
            $certificate->code ??= VerificationCode::generate();
        });
    }

    /** Public identifiers use the UUID, never the auto-increment id. */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /* ----------------------------------------------------------------- */
    /*  Relationships */
    /* ----------------------------------------------------------------- */

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    /* ----------------------------------------------------------------- */
    /*  Scopes */
    /* ----------------------------------------------------------------- */

    public function scopeRendered(Builder $query): Builder
    {
        return $query->where('status', CertificateStatus::Rendered->value);
    }

    public function scopeRenderFailed(Builder $query): Builder
    {
        return $query->where('status', CertificateStatus::RenderFailed->value);
    }

    /** Find by UUID or by (normalised) short code. */
    public function scopeByIdentifier(Builder $query, string $identifier): Builder
    {
        $identifier = trim($identifier);

        if (Str::isUuid($identifier)) {
            return $query->where('uuid', strtolower($identifier));
        }

        $code = VerificationCode::canonical($identifier);

        return $code === null ? $query->whereRaw('1 = 0') : $query->where('code', $code);
    }

    /* ----------------------------------------------------------------- */
    /*  State */
    /* ----------------------------------------------------------------- */

    public function isRendered(): bool
    {
        return $this->status === CertificateStatus::Rendered && $this->pdf_path !== null;
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null || $this->status === CertificateStatus::Revoked;
    }

    /** A certificate the verification page should call "valid". */
    public function isValid(): bool
    {
        return $this->isRendered() && ! $this->isRevoked();
    }

    public function verifyUrl(): string
    {
        return route('verify.show', ['identifier' => $this->uuid]);
    }

    public function deliveryStatus(DeliveryChannel $channel): DeliveryStatus
    {
        return match ($channel) {
            DeliveryChannel::Email => $this->email_status,
            DeliveryChannel::WhatsApp => $this->whatsapp_status,
        };
    }

    public function revoke(?string $reason = null): void
    {
        $this->forceFill([
            'status' => CertificateStatus::Revoked,
            'revoked_at' => now(),
            'revoke_reason' => $reason,
        ])->save();
    }

    public function unrevoke(): void
    {
        $this->forceFill([
            'status' => $this->pdf_path ? CertificateStatus::Rendered : CertificateStatus::Pending,
            'revoked_at' => null,
            'revoke_reason' => null,
        ])->save();
    }

    /** Where this certificate's PDF lives on the certificates disk. */
    public function pdfPath(): string
    {
        return $this->batch->directory().'/pdf/'.$this->code.'.pdf';
    }

    /** Safe, informative file name for downloads and ZIPs. */
    public function downloadName(): string
    {
        $name = preg_replace('/[\\\\\/:*?"<>|]+/u', ' ', $this->recipient_name) ?? $this->recipient_name;
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? $name);

        return sprintf('%03d-%s-%s.pdf', $this->row_number, Str::limit($name, 60, ''), $this->code);
    }
}
