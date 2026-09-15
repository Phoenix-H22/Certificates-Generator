# OPERATIONS — التشغيل والسيرفر والنشر

للنشر الأولي التفصيلي راجع [DEPLOYMENT.md](DEPLOYMENT.md). هذا الملف مرجع تشغيلي سريع + حقائق السيرفر + حل المشاكل.

## حقائق سيرفر الإنتاج (كما هو فعليًا)

- **الدومين:** asfeccert.phoenixtechs.tech — Ubuntu على FastPanel، خلف **nginx → PHP-FPM 8.2** مباشرة (مش Apache).
- **مجلد التطبيق:** `/var/www/phoenixtechs-asfeccert/data/www/asfeccert.phoenixtechs.tech`
- **مستخدم الموقع:** `phoenixtechs-asfeccert`
- **PHP CLI:** `/opt/php82/bin/php` (مفيش `/opt/php83` على السيرفر؛ سكربت النشر يفضّل 83 ويسقط لـ 82).
- **قاعدة البيانات:** MySQL 8، اسم القاعدة `certgen` (بها بيانات مستخدمين قديمة من v1).
- **Node/npm:** موجودان؛ Chrome for Testing في كاش Puppeteer الخاص بمستخدم الموقع (`~/.cache/puppeteer`).
- **`.env` مهم:** `APP_ENV=production`, `APP_DEBUG=false`, `APP_LOCALE=ar`, `QUEUE_CONNECTION=database`,
  `CERT_NO_SANDBOX=true`, `TRUSTED_PROXIES` غير مضبوط (nginx مباشر)، `CERT_CHROME_PATH` فارغ (يستخدم Chrome بتاع Puppeteer).
- **الأدمن:** `alkadydrmohamed@gmail.com`. (كان فيه حسابات v1 كتير؛ `is_admin` مفعّل لحسابين حقيقيين فقط.)
- **⚠️ FastPanel "Settings → Save"** ممكن يعيد توليد إعدادات nginx ويمسح السطرين المخصصين
  (`client_max_body_size 32m` ومنع `/storage/certificates`). أعِدهما من `deploy/nginx/certificates-generator.conf.snippet`.

## النشر التلقائي

- الملف: `.github/workflows/deploy.yml`. يعمل على **كل push إلى `main`** عبر SSH.
- ما يفعله: `git reset --hard origin/main` → `composer install` (لو تغيّر lock) → `npm ci` + تنزيل Chrome (لو تغيّر lock) →
  `migrate --force` (لو فيه migration جديد) → `storage:link` → `optimize` → إصلاح الملكية لو شغّال كـ root →
  إعادة تشغيل عمّال Supervisor فقط → **فحص رندر حقيقي** (يرسم شهادة عيّنة؛ فشلها يخلّي الـ Action أحمر).
- **فشل تنزيل drone-ssh أحيانًا** (شبكة GitHub) = خطأ عابر قبل الوصول للسيرفر؛ الحل: Re-run failed jobs. مفيش أثر على السيرفر.
- CI (اختبارات): `.github/workflows/ci.yml` معطّل تلقائيًا (يدوي عبر `workflow_dispatch`) لتوفير الدقائق.

## عمّال الطابور (Supervisor) — ميزانية الموارد

السيرفر **مشترك مع مواقع أهم**، فالإعداد مقصود إنه خفيف:

- الملف: `deploy/supervisor/certificates-generator.conf` → يُنسخ إلى `/etc/supervisor/conf.d/asfeccert-queue.conf`.
- مجموعة `asfeccert`: **عامل رندر واحد** (`asfeccert-render`) + **عامل توصيل واحد** (`asfeccert-deliver`).
- الاتنين تحت `nice -n 10` + `ionice -c2 -n7`، مع `--memory=256` و`--max-jobs`/`--max-time` لإعادة التدوير.
- **لا ترفع عدد عمّال الرندر** — كل عامل يفتح Chromium (~300MB). التكلفة الثابتة ~100MB خامل، ~450MB وقت الرندر.
- أعلام Chromium مقلّصة في `BrowsershotFactory` (بدون إضافات/خدمات خلفية، renderer واحد، heap 128MB).

أوامر:
```bash
supervisorctl status asfeccert:*
supervisorctl restart asfeccert:*
```

## المهام المجدولة (cron)

- `/etc/cron.d/phoenixtechs-asfeccert` ينادي `schedule:run` كل دقيقة.
- المجدول (`routes/console.php`):
  - **`certificates:cleanup`** — أول كل شهر 04:00.
  - `queue:prune-batches` و`queue:prune-failed` — يوميًا.

### أمر التنظيف `certificates:cleanup`
- يحذف ملفات الدفعات الأقدم من `CERT_RETENTION_DAYS` (عبر `certificates:prune`؛ **الصفوف تبقى للتحقق**).
- يحذف الملفات المؤقتة (`storage/app/tmp`, `render-tmp`, `render-tests`, livewire uploads).
- يحذف السجلات المدوَّرة الأقدم من 30 يوم، ويفرّغ أي سجل > 50MB.
- تجربة: `php artisan certificates:cleanup --dry-run`. تقرير التشغيل في `storage/logs/cleanup.log`.
- ينفع تخلي `LOG_CHANNEL=daily` في `.env` عشان السجلات تتدوّر تلقائيًا.

## أوامر Artisan المخصصة

| أمر | الغرض |
|---|---|
| `certificates:batch {template} {sheet.xlsx} --fixed=k=v --via=email --now` | إنشاء دفعة من الطرفية |
| `certificates:render-sample [template] --png --html` | رندر عيّنة عبر Chromium (فحص) |
| `certificates:thumbnails --force` | إعادة توليد صور القوالب |
| `certificates:prune --days=180 --dry-run` | حذف ملفات الدفعات القديمة |
| `certificates:cleanup [--dry-run]` | التنظيف الشهري الشامل |

## التشغيل محليًا (Windows / Laragon)

- **PHP:** الافتراضي على PATH قد يكون 8.2؛ استخدم `D:/laragon/bin/php/php-8.3/php.exe` لكل artisan/pest/composer.
  (فعّلنا `pdo_sqlite`/`sqlite3` في php.ini بتاع 8.3؛ نسخة احتياطية `php.ini.bak-before-sqlite`.)
- **Composer:** `D:/laragon/bin/php/php-8.3/php.exe C:/ProgramData/ComposerSetup/bin/composer.phar ...`.
  (أُزيل توكن GitHub غير الصالح من إعدادات composer العامة لأنه كان يجبر الاستنساخ من المصدر ويكسر على Windows.)
- **قاعدة البيانات:** MySQL `certgen` على المنفذ **33060** (Laragon)، أو SQLite للاختبارات
  (`DB_CONNECTION=sqlite`, `DB_DATABASE=<مسار مطلق>/database/database.sqlite`).
- **Chromium:** `CERT_CHROME_PATH="C:/Program Files/Google/Chrome/Application/chrome.exe"`، وتثبيت puppeteer بـ `PUPPETEER_SKIP_DOWNLOAD=1`.
- **سيرفر التطوير:** `.claude/launch.json` فيه إعداد `certgen` على المنفذ 8765 (غير متتبّع في git).
- **عامل الطابور لازم يشتغل** في طرفية منفصلة وإلا الشهادات تفضل "قيد الانتظار".
- **⚠️ heredocs في الـ bash tool تفسد الـ backslashes** — استخدم أداة كتابة الملفات لأي PHP فيه `\`.
- **لقطات اللوحة:** كانت تُلتقط عبر Browsershot مع كوكي جلسة من مسار محلي مؤقت `/__dev-login` (يُضاف ويُزال قبل الـ commit).

## أخطاء الميل الشائعة

- **535 Username and Password not accepted:** كلمة سر SMTP غلط (Gmail يحتاج App Password).
- **الإيميل "sent" لكن ما وصلش:** غالبًا `MAIL_FROM_ADDRESS` من دومين مختلف عن `MAIL_USERNAME` → DMARC. اجعلهما متطابقين.

## جدول حل المشاكل

| العَرَض | السبب | الحل |
|---|---|---|
| الدفعة "قيد الانتظار" ثابتة | العامل واقف | `supervisorctl status asfeccert:*`؛ ودجت "مهام في الانتظار" |
| فشل التوليد `Could not find Chrome` | Chrome غير محمّل لمستخدم العامل | `npx puppeteer browsers install chrome` بنفس المستخدم، أو `CERT_CHROME_PATH` |
| فشل التوليد `Running as root without --no-sandbox` | العامل root | شغّله بمستخدم الموقع أو `CERT_NO_SANDBOX=true` |
| `C:\Windows\Temp is not a writable folder` | OpenSpout يستخدم temp النظام | تأكد أن كل Excel يمر عبر `Spreadsheet::excel()` (يوجّه لـ storage/app/tmp) |
| صفحة عامة بشكل قديم بعد النشر | كاش CSS | `?v=filemtime` موجود؛ Ctrl+F5 مرة |
| البريد "تم التخطي" | الصف بلا إيميل صالح | عادي؛ الشهادة في الـ ZIP |
| 403 على `/admin` | الحساب مش أدمن | فعّل `is_admin` |
