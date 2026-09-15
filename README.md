# مولّد الشهادات — ASFEC Certificates Generator

نظام لإصدار شهادات شكر وحضور عربية احترافية بالجملة: يرفع الأدمن ملف Excel بأسماء المشاركين، يختار تصميم القالب، فيتولّى النظام توليد ملف PDF لكل شهادة عبر Chromium، ويرسلها بالبريد الإلكتروني و/أو واتساب، ويتيح التحقق من صحتها عبر رمز QR.

*English summary at the bottom.*

## المميزات

- **4 تصاميم جاهزة** (كلاسيكي ذهبي، حديث بسيط، رسمي مؤسسي، أنيق داكن) تُرندر من HTML/CSS بخطوط عربية مضمّنة (Amiri, Cairo, Tajawal, Noto Naskh) — لا اعتماد على Word أو LibreOffice.
- **رمز QR + UUID + كود قصير على كل شهادة**، وصفحة تحقق عامة `/verify/{uuid|code}` تعرض حالة الشهادة (صحيحة / ملغاة / غير موجودة).
- **مكتبة أصول**: شعارات متعددة، حتى 3 توقيعات (اسم + صفة + صورة)، ختم بموضع وميل وشفافية قابلة للضبط، خلفية اختيارية.
- **حقول ديناميكية** لكل قالب: قيم ثابتة للدفعة (اسم الفعالية، التاريخ…) أو لكل صف من Excel، مع ربط تلقائي للأعمدة.
- **دفعات** بحالة وتقدّم لحظي، ملف ZIP لكل الشهادات، تقرير أخطاء xlsx، وإجراءات إعادة الإرسال/إعادة التوليد/الإلغاء.
- **لوحة تحكم Filament v4** عربية RTL بالكامل.
- الأسماء الطويلة تصغر تلقائياً لتناسب المساحة بدل قصّها.

## المتطلبات

| المكوّن | الإصدار |
|---|---|
| PHP | 8.2+ (8.3 موصى به) مع `intl gd zip mbstring fileinfo` |
| Composer | 2.x |
| Node.js | 18+ (لـ Puppeteer/Chromium) |
| قاعدة بيانات | MySQL 8 (أو SQLite للتطوير) |
| طابور | `QUEUE_CONNECTION=database` + عامل `queue:work` دائم |

## التشغيل محلياً

```bash
git clone https://github.com/Phoenix-H22/Certificates-Generator.git
cd Certificates-Generator
composer install
cp .env.example .env && php artisan key:generate
# اضبط DB_* أو استخدم SQLite: DB_CONNECTION=sqlite و DB_DATABASE=<مسار مطلق>/database/database.sqlite
php artisan migrate --seed          # الإعدادات + شعارات نموذجية + 4 قوالب
php artisan storage:link
npm ci                              # يحمّل Chrome for Testing (~150MB) — أو اضبط CERT_CHROME_PATH لكروم مثبّت
php artisan make:filament-user      # أول أدمن (أو ADMIN_EMAIL/ADMIN_PASSWORD قبل الـ seed)
php artisan certificates:thumbnails # صور القوالب في اللوحة
php artisan serve
php artisan queue:work --queue=render,deliver,default   # في طرفية أخرى
```

ثم افتح `http://localhost:8000/admin`.

على Windows مع Laragon: اضبط في `.env`
`CERT_CHROME_PATH="C:/Program Files/Google/Chrome/Application/chrome.exe"` ولا حاجة لتحميل Chrome عبر npm (`PUPPETEER_SKIP_DOWNLOAD=1 npm install`).

## كيف يعمل

```
Excel ──► ProcessBatch ──► صف لكل شهادة (UUID + كود) ──► Bus::batch[ RenderCertificate → DeliverCertificate ]×N ──► FinalizeBatch
                                                                 │                                                       │
                                                        Blade design + Browsershot/Chromium ──► PDF              ZIP + تقرير + بريد للأدمن
```

- `app/Certificates/Rendering` — `CertificateRenderer` (HTML/PDF/PNG)، `CertificateDataFactory`، `DataMerger`، `QrCodeGenerator`.
- `app/Certificates/Pipeline` — قراءة الملف، ربط الأعمدة، ZIP، تقرير الأخطاء، ملف النموذج.
- `app/Certificates/Delivery` — `WhatsAppSender`, `MessageTemplate` (placeholders).
- `app/Jobs` — `ProcessBatch`, `RenderCertificate`, `DeliverCertificate`, `FinalizeBatch`, `GenerateTemplateThumbnail`.
- `resources/views/certificates/designs/*.blade.php` — التصاميم الأربعة؛ `layout.blade.php` والـ partials مشتركة.
- الملفات المولّدة على disk خاص `storage/app/certificates` وتُقدَّم عبر `/files/...` (أدمن) أو `/c/{uuid}` (رابط موقّع منتهي الصلاحية).

## إضافة تصميم جديد

1. أضف حالة في `App\Enums\TemplateDesign` (اسم، وصف، لون افتراضي).
2. أنشئ `resources/views/certificates/designs/<key-بشرطات>.blade.php` يمتد من `certificates.layout` ويستخدم partials `logos / signatures / stamp / qr`.
3. ضع صورة نموذج في `public/images/designs/<key>.png` (`php artisan certificates:render-sample --design=<key> --png`).
4. القوالب الموجودة تختار التصميم الجديد من اللوحة.

## أوامر Artisan

| الأمر | الغرض |
|---|---|
| `certificates:batch {template} {sheet.xlsx} --fixed=k=v --via=email --now` | إنشاء دفعة من الطرفية (نفس مسار اللوحة) |
| `certificates:render-sample [template] --png --html` | رندر عيّنة عبر Chromium الحقيقي (للفحص) |
| `certificates:thumbnails --force` | إعادة توليد صور القوالب |
| `certificates:prune --days=180 --dry-run` | حذف ملفات الدفعات القديمة (الصفوف تبقى للتحقق) |

## الاختبارات والجودة

```bash
composer lint          # Pint
php artisan test       # Pest (يتخطى اختبارات Chromium)
RENDER_TESTS=1 php artisan test --group=render   # رندر حقيقي
```

GitHub Actions يشغّل: Pint، الاختبارات على PHP 8.2 و8.3، رندر حقيقي بـ Chromium (يرفع PDF/PNG كـ artifact)، وفحص `config:cache` + منع `env()` خارج `config/`.

## النشر

راجع [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md): Node/Chromium، Supervisor للطوابير، cron، nginx، وسكربت النشر التلقائي `.github/workflows/deploy.yml` (ينشر عند كل push على `main`).

## الخطوط والتراخيص

الخطوط في `public/fonts` مرخّصة SIL OFL 1.1 (انظر `public/fonts/LICENSES.md`). الكود مرخّص MIT.

---

## English

Bulk Arabic certificate generator built on Laravel 12 + Filament v4. Admins upload an Excel sheet, pick one of four HTML/CSS designs, and the system renders a PDF per certificate through headless Chromium (spatie/browsershot), delivers it by e-mail and/or WhatsApp, and lets anyone verify it via the QR code (UUID + short code) printed on every certificate. Generated files live on a private disk; certificates are database records created before rendering, so every PDF is verifiable at `/verify/{uuid}`. See `docs/DEPLOYMENT.md` for the Ubuntu setup (Node + Chromium, Supervisor queues, cron).
