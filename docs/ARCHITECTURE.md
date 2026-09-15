# ARCHITECTURE — بنية المشروع التقنية

نظرة عميقة على كيف يشتغل الكود. للجداول والأعمدة راجع [DATA-MODEL.md](DATA-MODEL.md)، وللسيرفر راجع [OPERATIONS.md](OPERATIONS.md).

## 1. الستاك

- **Laravel 12** / **PHP 8.2+** (8.3 موصى به).
- **Filament v4** — لوحة تحكم الأدمن على `/admin` (تسجيل الدخول الوحيد في النظام).
- **spatie/browsershot ^5** — يشغّل Chromium (Puppeteer) لتحويل HTML → PDF/PNG.
- **puppeteer (npm ^25)** — يوفّر Chrome for Testing؛ رُقّي إلى 25 لإزالة ثغرة `extract-zip`.
- **rap2hpoutre/fast-excel** + **openspout** — قراءة/كتابة xlsx.
- **chillerlan/php-qrcode** — رموز QR كـ SVG inline (بدون GD).
- **propaganistas/laravel-phone** — تحقق أرقام الهاتف.
- الطابور: `database`. الجلسات/الكاش: database. الميل: SMTP.
- **مفيش Vite build** — Filament يجيب أصوله؛ الصفحات العامة CSS يدوي (`public/css/public.css`).

## 2. المعمارية العامة

```
رفع Excel من اللوحة
        │
        ▼
  ProcessBatch (job)                     ← يقرأ الشيت، يتحقق، يربط الأعمدة، ينشئ صفوف Certificate
        │  Bus::batch([ [RenderCertificate → DeliverCertificate] × N ])
        │  ->finally(FinalizeBatch)
        ▼
  RenderCertificate (job، طابور render)  ← CertificateRenderer::pdf() عبر Chromium → PDF على disk خاص
        ▼
  DeliverCertificate (job، طابور deliver)← إيميل و/أو واتساب، لكل قناة حالة مستقلة
        ▼
  FinalizeBatch (job)                    ← ZIP + تقرير أخطاء xlsx + إيميل ملخص للأدمن
```

- كل شهادة صف في جدول `certificates` **يُنشأ قبل الرندر**، فلها UUID وكود تحقق من البداية → قابلة للتحقق دائمًا.
- الشهادة الفردية (`SingleCertificateIssuer`) تعمل نفس المسار كدفعة من صف واحد.

## 3. طبقات الدومين (`app/Certificates/`)

الهدف: `app/Http` يفضل خفيف، والمنطق كله هنا وقابل للاختبار.

### Rendering — تحويل البيانات إلى صورة
| كلاس | الدور |
|---|---|
| `CertificateRenderer` | الواجهة الرئيسية: `html()`, `pdf()`, `thumbnail()`. يكتب الـ HTML لملف مؤقت ثم يشغّل Chromium (Browsershot يرفض `file://` داخل HTML inline، فنستخدم `setHtmlFromFilePath`). |
| `BrowsershotFactory` | يبني Browsershot من `config('certificates.browsershot')` (مسارات node/chrome، sandbox، أعلام تقليل الموارد). |
| `CertificateData` | DTO immutable فيه كل ما يحتاجه التصميم: الاسم، الحقول، اللوجوهات/التوقيعات/الختم (كـ data URI)، الـ QR SVG، الكود، رابط التحقق. |
| `CertificateDataFactory` | يبني `CertificateData` من `Certificate` حقيقي (`fromCertificate`) أو ببيانات وهمية (`sample` — للمعاينة والـ thumbnails، باسم طويل لاختبار التصغير). |
| `DataMerger` | يدمج: defaults القالب ← القيم الثابتة للدفعة ← قيم الصف ← الحقول الأساسية. يطبّع الهيدر، يحوّل التواريخ عربي، يتحقق ويرمي `InvalidRowException`. |
| `QrCodeGenerator` | QR كـ SVG (ECC M). |
| `NameSizer` | تخمين حجم خط أولي حسب طول الاسم؛ سكربت `fit-text` في الـ view يظبطه نهائيًا. |
| `AssetUrl` | يحوّل مسار public لـ `file:///` (رندر) أو `asset()` (معاينة) — للخطوط والزخارف. |
| `RenderMode` | enum: `pdf | preview | thumbnail`. |

**كيف يعرف Chromium إن الصفحة جاهزة:** الـ layout فيه سكربت `fit-text` بيصغّر الأسماء الطويلة بعد `document.fonts.ready` ثم يضبط `window.__certReady = true`، والـ renderer ينتظر `waitForFunction('window.__certReady === true')`.

### Pipeline — من Excel إلى شهادات
| كلاس | الدور |
|---|---|
| `SheetReader` | يقرأ العناوين (`headers`) والصفوف (`rows`) من xlsx. حد أقصى `CERT_MAX_ROWS`. |
| `ColumnMapper` | يخمّن أي عمود يقابل أي حقل (يفهم عربي وإنجليزي: "الاسم"/"Name"...)، ويحدد الحقول المطلوبة الناقصة. |
| `SampleSheetBuilder` | يبني ملف xlsx نموذجي بأعمدة القالب. |
| `ZipBuilder` | يجمع كل PDFs الدفعة في ZIP (أسماء UTF-8). |
| `ErrorReportBuilder` | تقرير xlsx بكل صف فيه مشكلة (validation/render/email/whatsapp). |
| `SingleCertificateIssuer` | إصدار شهادة واحدة بدون ملف (دفعة صف واحد). |

### Delivery
| كلاس | الدور |
|---|---|
| `WhatsAppSender` | POST نموذجي للبوابة (`appkey/authkey/to/message/file`). `file` = رابط تنزيل موقّع منتهي الصلاحية. 4xx = فشل بدون إعادة، شبكة = `DeliveryException` (إعادة). |
| `MessageTemplate` | يملأ placeholders في نصوص الإيميل/الواتساب + يبني رابط التنزيل الموقّع. |
| `DeliveryResult` / `DeliveryException` | نتيجة/استثناء التوصيل. |

### Support / Data / Exceptions
- `VerificationCode` — كود 10 رموز بأبجدية بدون حروف/أرقام ملتبسة (`XXXXX-XXXXX`)، مع `normalize`/`canonical`.
- `PhoneNormalizer` — أرقام مصرية `01x`/`1x`/`20…`/`0020…` → E.164.
- `EmailNormalizer` — يشيل محارف bidi/zero-width من Excel ويتحقق.
- `ArabicText` — أرقام هندية + تواريخ عربية (`translatedFormat`).
- `Placeholders` — استبدال `{key}` (يترك المجهول كما هو).
- `Spreadsheet` — **مهم:** يجبر FastExcel/OpenSpout على استخدام `storage/app/tmp` بدل `sys_get_temp_dir()` (اللي بيكون `C:\Windows\Temp` غير قابل للكتابة على السيرفر). أي استخدام لـ Excel لازم يمر عبره.
- `Data/FieldSchema` + `FieldDefinition` — value objects لسكيما حقول القالب.

## 4. الوظائف (`app/Jobs/`)

- **`ProcessBatch`** (طابور default, tries 1): parsing → إنشاء الصفوف (chunks 200) → `Bus::batch` سلاسل [render→deliver] → `finally(FinalizeBatch)`. الصفوف الفاسدة تتسجل `render_failed` مع السبب. idempotent (لا يكرر الصفوف).
- **`RenderCertificate`** (طابور render, tries 2, backoff 15/60, `WithoutOverlapping` + `ThrottlesExceptions`): idempotent (يتخطى لو الـ PDF موجود). فشل نهائي → `render_failed`.
- **`DeliverCertificate`** (طابور deliver, tries 3, backoff 30/120/600, `RateLimited('whatsapp')`): كل قناة حالة مستقلة، فالإعادة لا تكرر قناة نجحت. يقبل قنوات override (لأزرار إعادة الإرسال).
- **`FinalizeBatch`** (default): يعيد العد من الصفوف (دقة)، يبني ZIP والتقرير، الحالة `completed`/`completed_with_errors`، إيميل للأدمن.
- **`GenerateTemplateThumbnail`** (render): صورة PNG للقالب في اللوحة.

## 5. الرندر (`resources/views/certificates/`)

- `layout.blade.php` — الغلاف: `<html dir="rtl">`, `@page A4 landscape`, `@font-face` للخطوط الأربعة، متغيرات CSS، سكربت fit-text، partials.
- `partials/` — `logos`, `signatures` (حتى 3)، `stamp` (موضع/ميل/شفافية من الـ config)، `qr` (إلزامي: QR + الكود + UUID)، `fit-text`.
- `designs/` — 4 تصاميم: `classic-gold`, `modern-minimal`, `formal-blue`, `elegant-dark`. كل واحد `@extends` الـ layout.
- الخطوط في `public/fonts/{cairo,amiri,tajawal,noto-naskh-arabic}` (OFL، محلية، مش من Google CDN).
- **إضافة تصميم:** أضف case في `TemplateDesign` + view باسمه (بشرطات) + صورة في `public/images/designs/<key>.png`.

## 6. الـ HTTP والمسارات (`routes/web.php`)

| المسار | الغرض | الحماية |
|---|---|---|
| `/` | صفحة هبوط بسيطة فيها بحث تحقق | `security.headers` |
| `/verify` , `/verify/{identifier}` | التحقق العام (UUID أو كود) | `security.headers` + `throttle:verify` |
| `/c/{certificate:uuid}` | تنزيل PDF عام | `signed` + `throttle:downloads` |
| `/files/certificates/{uuid}/pdf`، `/files/batches/{batch}/{zip,errors,source}` | تنزيلات الأدمن | `auth` + `can:admin` |
| `/admin-preview/templates/{template}` | معاينة قالب (HTML/PDF/PNG) | `auth` + `can:admin` |
| `/admin/*` | لوحة Filament | Filament (`is_admin`) |

- `VerificationController` — يقبل UUID أو كود فقط (يرفض أي شكل تاني قبل الاستعلام)، يعدّ التحقق مرة/ساعة/IP، يعرض حقول `show_on_verify` فقط.
- `Middleware/SecurityHeaders` — CSP صارم (بدون سكربتات)، nosniff، DENY framing، HSTS على TLS، noindex.
- `Middleware/TrustProxies` — من `TRUSTED_PROXIES` عشان الـ rate limit يشوف IP الحقيقي خلف nginx.
- الأدمن gate في `AppServiceProvider` = `$user->is_admin`؛ والـ rate limiters (`verify`, `downloads`, `whatsapp`) معرّفة هناك بقيم من `config/certificates.php`.

## 7. لوحة Filament (`app/Filament/`)

- **Resources:** Templates (تبويبات: تصميم/نصوص/أصول/حقول + معرض تصاميم + معاينة)، BrandAssets، Batches (Wizard 5 خطوات + relation manager للشهادات + أزرار ZIP/تقرير/إعادة/إلغاء)، Certificates (بحث، عرض PDF، إعادة إرسال، إبطال).
- **Pages:** Dashboard، IssueCertificatePage (شهادة فردية)، OrganisationSettingsPage.
- **Widgets:** StatsOverview، QueueHealth (ينبّه لو العامل واقف)، RecentBatches.
- الأدمن panel provider: `app/Providers/Filament/AdminPanelProvider.php` (عربي RTL، خط Cairo محلي، `is_admin`).

## 8. نقاط انتبه لها (gotchas)

- **CheckboxList في Filament يرجّع enum instances مش نصوص** → استخدم `DeliveryChannel::normalize()` دائمًا.
- **تصفير حقول Filament المتداخلة:** صفّرها بـ `null` مش `[]` (الـ `[]` بيتحول JS array وبيضيّع المفاتيح المتداخلة). سبب مشكلة "التاريخ مطلوب" السابقة.
- **الـ Placeholder/TextEntry بدون label:** استخدم `->hiddenLabel()` مش `->label('')`.
- **كسر كاش CSS العام:** `layouts/public.blade.php` يضيف `?v=filemtime`.
- **الاسم الطويل:** يصغر تلقائيًا عبر fit-text بدل ما يتقص.
