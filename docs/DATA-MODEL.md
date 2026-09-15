# DATA-MODEL — قاعدة البيانات والـ Enums

الترحيلات في `database/migrations/` (ملفات `2026_09_15_*`). ملفات `0001_01_01_*` هي جداول Laravel 12 القياسية،
لكنها **محمية** (`if (! Schema::hasTable(...))`) عشان ما تتصادمش مع جداول v1 الموجودة على قاعدة بيانات الإنتاج.

## الجداول الأساسية

### `organisation_settings` (صف واحد، id = 1)
بيانات الجهة المُصدِرة ونصوص الرسائل. يُقرأ عبر `OrganisationSettings::current()` (مع cache).

| عمود | ملاحظة |
|---|---|
| `name_ar`, `name_en?`, `parent_name_ar?` | أسماء الجهة والهيئة الأم |
| `website?` | |
| `email_subject`, `email_body` | نص الإيميل (يقبل placeholders) |
| `whatsapp_message` | نص الواتساب |
| `default_template_id?` | FK → templates |
| `verification_footer?` | نص إضافي في صفحة التحقق |

### `brand_assets`
مكتبة الصور (شعارات، توقيعات، أختام، خلفيات).

| عمود | ملاحظة |
|---|---|
| `type` | enum `BrandAssetType` (نص) |
| `name` | اسم/اسم الموقّع |
| `role?` | صفة الموقّع (للتوقيعات) |
| `path` | على disk `public` تحت `brand/` |
| `path_light?` | نسخة فاتحة للتصميم الداكن |
| `width_mm?`, `sort`, `is_active` | |

الموديل: scopes `active()`/`ofType()`/`ordered()`، و`dataUri()` (base64 للرندر) و`url()`.

### `templates`
القالب = تصميم + سكيما حقول + إعدادات تخطيط. `softDeletes`.

| عمود | ملاحظة |
|---|---|
| `name`, `slug` (unique) | |
| `design` | enum `TemplateDesign` |
| `fields_schema` | JSON → `FieldSchema` value object عبر `FieldSchemaCast` |
| `layout_config` | JSON (يُطبّع في `Template::normalizeLayout`) |
| `is_active`, `preview_path?`, `created_by?` | |

`fields_schema` عناصرها: `{key, label, type, scope, required, max_length?, default?, show_on_verify}`.
الحقول الأساسية `name/title/email/phone` ضمنية دائمًا (ثابتة في `FieldSchema::CORE_FIELDS`).

`layout_config`: `{logos:[ids], signatures:[ids≤3], stamp:{asset_id,position,width_mm,opacity,rotate}, background_asset_id?, accent, qr_position, qr_size_mm, title_text, body_text, closing_text, name_font, numerals}`.
النصوص تقبل placeholders. **الـ QR إلزامي** (يتحكم بالموضع/الحجم فقط).

### `batches`
رفعة واحدة لملف مشاركين مقابل قالب.

| عمود | ملاحظة |
|---|---|
| `template_id`, `name`, `status` (enum, index) | |
| `deliver_via` (JSON) | قنوات الإرسال |
| `fixed_values` (JSON) | القيم الثابتة للفعالية |
| `column_map` (JSON) | ربط الحقل ← عمود Excel |
| `source_path?`, `source_original_name?` | ملف المشاركين على disk `certificates` |
| `total_rows`, `rendered_count`, `render_failed_count`, `email_sent_count`, `email_failed_count`, `whatsapp_sent_count`, `whatsapp_failed_count` | عدادات |
| `zip_path?`, `error_report_path?` | ناتج FinalizeBatch |
| `bus_batch_id?` (index) | ربط بـ Bus batch (للإلغاء والتقدّم) |
| `error_message?`, `created_by?`, `started_at?`, `finished_at?` | |

الموديل: `progressPercent()`, `busBatch()`, `markStatus()`, `deliversVia()`, `directory()` (= `batches/{id}` على disk certificates).

### `certificates`
شهادة واحدة. تُنشأ قبل الرندر.

| عمود | ملاحظة |
|---|---|
| `uuid` (unique) | **المعرّف الرسمي**؛ مطبوع على الشهادة ومشفّر في الـ QR. route key. |
| `code` (unique, 16) | كود قصير للإدخال اليدوي |
| `batch_id` (cascade), `template_id`, `row_number` | |
| `recipient_name`, `recipient_title?`, `email?`, `phone?` (E.164) | |
| `data` (JSON) | الحقول المدموجة النهائية |
| `status` | enum `CertificateStatus` |
| `pdf_path?`, `render_error?`, `rendered_at?` | |
| `email_status`, `email_sent_at?`, `email_error?` | enum `DeliveryStatus` |
| `whatsapp_status`, `whatsapp_sent_at?`, `whatsapp_error?` | enum `DeliveryStatus` |
| `verified_count`, `last_verified_at?` | عدّاد التحقق |
| `revoked_at?`, `revoke_reason?` | الإلغاء |

الموديل: boot يولّد `uuid` و`code`؛ `isValid()` (مرندرة وغير ملغاة)، `isRevoked()`, `revoke()`/`unrevoke()`,
`verifyUrl()`, `pdfPath()`, `downloadName()`، وscope `byIdentifier()` (UUID أو كود).

### `users`
مضاف عليها `phone` و`is_admin` (bool). المستخدمون الموجودون من v1 صاروا أدمن افتراضيًا (default true في migration الإضافة، لكن على الإنتاج ضُبطت الصلاحية يدويًا لحسابين فقط). `canAccessPanel()` = `is_admin`.

### أخرى
- `notifications` — إشعارات لوحة Filament.
- جداول قياسية: `cache`, `jobs`, `job_batches`, `failed_jobs`, `password_reset_tokens`, `sessions`.
- **حُذفت في `drop_legacy_tables`:** `settings`, `subscriped_users`, `password_resets`, `personal_access_tokens` (من v1).

## الـ Enums (`app/Enums/`, كلها string-backed)

| enum | القيم | ملاحظات |
|---|---|---|
| `TemplateDesign` | classic_gold, modern_minimal, formal_blue, elegant_dark | `view()`, `defaultAccent()`, `prefersLightAssets()`, `sampleImage()` |
| `BrandAssetType` | logo, signature, stamp, background | |
| `FieldScope` | fixed (لكل الدفعة), row (لكل صف) | |
| `FieldType` | text, date, email, phone, number | `rules()` |
| `BatchStatus` | draft, queued, parsing, rendering, delivering, completed, completed_with_errors, failed, cancelled | `isTerminal()`, `isActive()`, `color()` |
| `CertificateStatus` | pending, rendered, render_failed, revoked | `color()` |
| `DeliveryStatus` | not_requested, pending, sent, failed, skipped | `color()` |
| `DeliveryChannel` | email, whatsapp | **`normalize()`** لتوحيد enums/نصوص من Filament |

كل الـ enums المعروضة في Filament تنفّذ `HasLabel`/`HasColor` بالعربي.

## العلاقات

```
OrganisationSettings ──(default_template)──▶ Template
Template ──has many──▶ Batch, Certificate
Template ──(layout_config ids)──▶ BrandAsset (logos, signatures, stamp, background)
Batch ──belongs to──▶ Template, User(creator)
Batch ──has many──▶ Certificate
Certificate ──belongs to──▶ Batch, Template
User ──has many──▶ Batch, Template
```

## Factories & Seeders

- Factories لكل الموديلز (`database/factories/`).
- `DatabaseSeeder` ينادي: `AdminUserSeeder` (من `ADMIN_EMAIL`/`ADMIN_PASSWORD` لو مفيش أدمن)،
  `OrganisationSettingsSeeder`، `BrandAssetsSeeder` (ينسخ عيّنات من `resources/brand-samples/`)،
  `TemplatesSeeder` (4 قوالب، واحد لكل تصميم).
