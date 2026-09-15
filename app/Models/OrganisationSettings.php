<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

/**
 * Singleton row (id = 1) describing the issuing organisation and the
 * default wording of delivery messages.
 */
class OrganisationSettings extends Model
{
    use HasFactory;

    public const SINGLETON_ID = 1;

    public const CACHE_KEY = 'organisation_settings';

    protected $table = 'organisation_settings';

    protected $fillable = [
        'name_ar',
        'name_en',
        'parent_name_ar',
        'website',
        'email_subject',
        'email_body',
        'whatsapp_message',
        'default_template_id',
        'verification_footer',
    ];

    public const DEFAULT_EMAIL_SUBJECT = 'شهادة {event_name}';

    public const DEFAULT_EMAIL_BODY = <<<'TXT'
معالي {title} / {name}

تحية طيبة وبعد،

يسعدنا في {organisation} مشاركتكم في {event_name}، ويشرفنا أن نرفق لكم شهادتكم.

يمكنكم التحقق من صحة الشهادة في أي وقت عبر الرابط:
{verify_url}

مع خالص التقدير،
{organisation}
TXT;

    public const DEFAULT_WHATSAPP_MESSAGE = <<<'TXT'
معالي {title} / {name}
تحية طيبة وبعد،
يسعدنا في {organisation} مشاركتكم في {event_name}، ويشرفنا إرسال شهادتكم.
للتحقق من الشهادة: {verify_url}
TXT;

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    /** The one and only settings row, created with sensible defaults if missing. */
    public static function current(): self
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => self::firstOrCreate(
            ['id' => self::SINGLETON_ID],
            self::defaults(),
        ));
    }

    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        return [
            'name_ar' => 'المركز الإقليمي لتعليم الكبار – أسفك',
            'name_en' => 'ASFEC – Regional Centre for Adult Education',
            'parent_name_ar' => 'الهيئة العامة لتعليم الكبار',
            'website' => null,
            'email_subject' => self::DEFAULT_EMAIL_SUBJECT,
            'email_body' => self::DEFAULT_EMAIL_BODY,
            'whatsapp_message' => self::DEFAULT_WHATSAPP_MESSAGE,
            'verification_footer' => null,
        ];
    }

    public function defaultTemplate(): BelongsTo
    {
        return $this->belongsTo(Template::class, 'default_template_id');
    }
}
