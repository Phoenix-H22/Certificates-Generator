# AGENTS.md — دليل الوكيل الذكي (اقرأني أولاً)

> هذا الملف نقطة البداية لأي مساعد ذكي (AI) أو مطوّر جديد يشتغل على المشروع.
> اقرأه بالكامل قبل أي تعديل، ثم افتح الملف المتخصص حسب مهمتك.

## المشروع في سطر واحد

منصة **Laravel 12 + Filament v4** لإصدار شهادات عربية بالجملة من ملف Excel، تُرسم كـ PDF عبر
**Chromium (spatie/browsershot)**، وتُرسل بالبريد/الواتساب، ولكل شهادة **QR + كود تحقق** وصفحة تحقق عامة.

- **الإنتاج:** https://asfeccert.phoenixtechs.tech (لوحة التحكم `/admin`، التحقق `/verify`).
- **المستودع:** `Phoenix-H22/Certificates-Generator`. النسخة الحالية v2.0 على الفرع `main`.
- **GitHub org/repo تُؤخذ من الريموت دائمًا** — لا تفترض مستودعًا افتراضيًا.

## الوثائق المتخصصة

| الملف | متى تفتحه |
|---|---|
| [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) | فهم بنية الكود: خط أنابيب التوليد، الرندر، التوصيل، الطبقات والكلاسات |
| [`docs/DATA-MODEL.md`](docs/DATA-MODEL.md) | الجداول والأعمدة والـ enums والعلاقات |
| [`docs/OPERATIONS.md`](docs/OPERATIONS.md) | السيرفر، النشر، Supervisor، الكرون، التنظيف، ميزانية الموارد، حل المشاكل |
| [`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md) | خطوات النشر الأولى على Ubuntu (عربي) |
| [`docs/user-guide.html`](docs/user-guide.html) | دليل المستخدم النهائي بالعامية (صفحة HTML بلقطات) |
| [`CHANGELOG.md`](CHANGELOG.md) | ملخص إصدار 2.0 |

## قواعد ذهبية (لا تكسرها)

1. **`main` = إنتاج مباشر.** أي `push` على `main` يشغّل النشر التلقائي (`.github/workflows/deploy.yml`) على السيرفر فورًا. اشتغل على فرع feature وادمج في `main` فقط لما تكون جاهز للنشر.
2. **الفرع الأساسي للتطوير:** `feat/laravel-12` (التاريخ الكامل موجود عليه). النمط المتبع: commit على الفرع → push → merge `--no-ff` في `main` → push.
3. **لا تشغّل عمّال طابور بأكثر من العدد المحدد.** السيرفر مشترك مع مواقع أهم؛ عامل رندر واحد فقط (Chromium ثقيل). التفاصيل في OPERATIONS.
4. **`env()` تُستخدم داخل `config/` فقط** — أي `env()` في `app/` أو `routes/` أو `resources/` يكسر `config:cache`. الإعدادات المخصصة في `config/certificates.php` و`config/services.php`. يوجد فحص CI يمنع ذلك.
5. **الملفات المولّدة لا تُوضع في `public/`.** كل شهادة/ZIP/تقرير على disk خاص `certificates` (`storage/app/certificates`) وتُقدَّم عبر روابط موقّعة أو مصرّح بها فقط.
6. **الأصول الثابتة العامة (CSS) تحتاج كسر كاش.** `layouts/public.blade.php` يضيف `?v=filemtime`؛ لا تشِل ده.
7. **التحقق عام ومحمي.** أي تعديل على `/verify` يحافظ على: تقييد الإدخال (UUID أو كود فقط)، الـ rate limits، ورؤوس الحماية.
8. **الإلغاء وليس الحذف.** الشهادات تُلغى (`revoke`) عشان صفحة التحقق تقول "ملغاة"؛ لا تُحذف الصفوف.

## التشغيل محليًا (Windows / Laragon) — باختصار

البيئة عند المالك: Windows + Laragon. تفاصيل كاملة في OPERATIONS، وأهم النقاط:

```bash
# PHP 8.3 هو المطلوب (الافتراضي على PATH قد يكون 8.2)
D:/laragon/bin/php/php-8.3/php.exe artisan serve --port=8765
# في طرفية تانية — لازم عامل طابور شغّال وإلا الشهادات تفضل "قيد الانتظار"
D:/laragon/bin/php/php-8.3/php.exe artisan queue:work --queue=render,deliver,default
```

- قاعدة البيانات المحلية: MySQL `certgen` على المنفذ **33060** (منفذ Laragon)، أو SQLite للاختبارات.
- Chromium محليًا: `CERT_CHROME_PATH="C:/Program Files/Google/Chrome/Application/chrome.exe"` في `.env`.
- الأدمن المحلي: `admin@asfec.com`.
- **heredocs في الـ bash tool بتفسد الـ backslashes** — استخدم أداة كتابة الملفات لأي PHP فيه `\`.

## الجودة قبل أي دمج

```bash
D:/laragon/bin/php/php-8.3/php.exe vendor/bin/pint          # تنسيق
D:/laragon/bin/php/php-8.3/php.exe vendor/bin/pest          # الاختبارات (يتخطى اختبارات Chromium)
RENDER_TESTS=1 ... vendor/bin/pest --group=render          # رندر حقيقي (اختياري، يحتاج Chromium)
```

- الاختبارات: Pest، ~93 اختبار. ملفات CI: `.github/workflows/ci.yml` (يدوي فقط الآن عبر `workflow_dispatch`)
  و`.github/workflows/deploy.yml` (نشر تلقائي على `main`).
- **صيغة الرسائل:** Conventional Commits (`.github/copilot-instructions.md`).

## البنية العامة للمجلدات

```
app/
  Certificates/            ← منطق الدومين (خارج app/Http)
    Rendering/             ← تحويل البيانات إلى HTML/PDF/PNG عبر Chromium
    Pipeline/              ← قراءة Excel، ربط الأعمدة، ZIP، تقارير، إصدار فردي
    Delivery/              ← WhatsApp + قوالب الرسائل
    Data/                  ← FieldSchema / FieldDefinition (value objects)
    Support/               ← أكواد التحقق، تطبيع الهاتف/الإيميل، التواريخ العربية، Excel
    Exceptions/
  Enums/                   ← 8 enums string-backed
  Jobs/                    ← ProcessBatch → RenderCertificate → DeliverCertificate → FinalizeBatch
  Mail/                    ← CertificateMail, BatchFinishedMail
  Models/                  ← Template, Batch, Certificate, BrandAsset, OrganisationSettings, User
  Filament/                ← لوحة التحكم (Resources, Pages, Widgets)
  Http/Controllers/        ← Verification, PublicDownload, AdminFile, TemplatePreview
  Http/Middleware/         ← SecurityHeaders, TrustProxies
resources/views/certificates/  ← تصاميم الشهادات (4) + layout + partials
public/fonts/             ← خطوط عربية OFL محلية
deploy/                   ← Supervisor + nginx snippets
docs/                     ← هذه الوثائق
```
