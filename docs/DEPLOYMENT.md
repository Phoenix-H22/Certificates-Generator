# النشر على Ubuntu (FastPanel / VPS)

المشروع يحتاج، بالإضافة إلى PHP: **Node.js + Chromium** (لتوليد PDF عبر Puppeteer) و**عامل طابور** دائم (Supervisor). كل الخطوات أدناه لخادم Ubuntu 22.04 / 24.04. المسارات المذكورة هي مسارات FastPanel الحالية للموقع `asfeccert.phoenixtechs.tech`؛ عدّلها حسب خادمك.

## 1. PHP

- PHP **8.2 أو أحدث** (8.3 موصى به). في FastPanel اختر إصدار PHP للموقع من لوحة الموقع، ثم تأكد أن مسار الـ CLI صحيح (`/opt/php83/bin/php`) وحدّثه في:
  - `.github/workflows/deploy.yml` (المتغيّر `PHP`)
  - `deploy/supervisor/certificates-generator.conf`
- الامتدادات المطلوبة: `mbstring intl gd zip fileinfo curl pdo_mysql xml dom` (و`pdo_sqlite` للاختبارات فقط).
- `proc_open` و`exec` غير معطّلين في `disable_functions` (Browsershot يشغّل Node كعملية فرعية).
- `memory_limit=512M` للـ CLI، `upload_max_filesize=32M` و`post_max_size=32M`.

## 2. Node.js و Chromium

```bash
# Node 20 LTS
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt-get install -y nodejs

# مكتبات النظام التي يحتاجها Chromium
sudo apt-get install -y --no-install-recommends \
  libnss3 libatk-bridge2.0-0 libxkbcommon0 libgbm1 libasound2 libxdamage1 \
  libxrandr2 libpango-1.0-0 libcups2 libxcomposite1 libxfixes3 libdrm2 fonts-noto-core
```

ثم داخل مجلد المشروع **بمستخدم الموقع** (وليس root):

```bash
npm ci --omit=dev
npx puppeteer browsers install chrome
```

يُحمَّل Chrome إلى `~/.cache/puppeteer` الخاص بمستخدم الموقع؛ عامل الطابور يجب أن يعمل بنفس المستخدم. لو أردت استخدام Chrome/Chromium مثبّت من apt بدلاً من ذلك، اضبط `CERT_CHROME_PATH=/usr/bin/chromium` في `.env`.

> لا تشغّل عامل الطابور كـ root. إن اضطررت (Docker مثلاً) فاضبط `CERT_NO_SANDBOX=true`.

## 3. متغيّرات البيئة

انسخ `.env.example` إلى `.env` واضبط على الأقل:

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://asfeccert.phoenixtechs.tech      # يُستخدم في روابط التحقق ورمز QR
APP_LOCALE=ar
DB_*                                              # MySQL
QUEUE_CONNECTION=database                         # لا تستخدم sync أبداً
MAIL_*                                            # SMTP الفعلي
ADMIN_EMAIL=...                                   # يستقبل تقارير الدفعات
WHATSAPP_APP_URL / WHATSAPP_APP_KEY / WHATSAPP_APP_SECRET
```

## 4. أول تثبيت

```bash
cd /var/www/phoenixtechs-asfeccert/data/www/asfeccert.phoenixtechs.tech
composer install --no-dev --optimize-autoloader --no-interaction   # ينشر أصول Filament تلقائياً
php artisan key:generate            # في أول مرة فقط
php artisan migrate --force         # الترحيلات محمية: لا تعيد إنشاء جداول v1 الموجودة
php artisan storage:link
php artisan db:seed --force         # الإعدادات + الشعارات النموذجية + القوالب الأربعة
php artisan make:filament-user      # أول حساب أدمن (أو ADMIN_EMAIL/ADMIN_PASSWORD قبل الـ seed)
php artisan certificates:thumbnails # صور القوالب في اللوحة (يحتاج Chromium)
php artisan optimize
```

اختبر أن Chromium يعمل بمستخدم الموقع:

```bash
php artisan certificates:render-sample classic-gold --png
ls storage/app/render-tests/
```

## 5. عامل الطابور (Supervisor)

```bash
sudo apt-get install -y supervisor
sudo cp deploy/supervisor/certificates-generator.conf /etc/supervisor/conf.d/
# عدّل المسارات ومستخدم الموقع داخل الملف
sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl status
```

- طابور `render`: عامل واحد (Chromium ≈ 300MB RAM لكل عملية). ارفعه إلى 2 فقط إذا كانت الذاكرة ≥ 4GB.
- طابور `deliver,default`: عاملان للإرسال والتجميع.
- بعد كل نشر: `php artisan queue:restart` (موجود في سكربت النشر).

## 6. المهام المجدولة (cron)

```
* * * * * cd /var/www/.../asfeccert.phoenixtechs.tech && /opt/php83/bin/php artisan schedule:run >> /dev/null 2>&1
```

تشغّل يومياً: `certificates:prune` (حذف ملفات الدفعات الأقدم من `CERT_RETENTION_DAYS`، الصفوف تبقى للتحقق) و`queue:prune-batches` و`queue:prune-failed`.

## 7. nginx

أضف محتوى `deploy/nginx/certificates-generator.conf.snippet` إلى إعدادات الموقع (رفع ملفات حتى 32MB، منع الوصول لمخزن الشهادات الخاص، كاش للخطوط).

## 8. النشر التلقائي

`.github/workflows/deploy.yml` ينشر تلقائياً عند كل push على `main`:
سحب الكود → `composer install` عند تغيّر `composer.lock` → `npm ci` عند تغيّر `package-lock.json` → `migrate --force` → `optimize` → `queue:restart`.

**تحذير:** دمج فرع `feat/laravel-12` في `main` سينشر النسخة الجديدة فوراً. قبل الدمج تأكد من الخطوات 1–5 على الخادم (خاصة Node/Chromium وSupervisor) وإلا ستفشل مهام التوليد.

## 9. استكشاف الأخطاء

| العَرَض | السبب المحتمل | الحل |
|---|---|---|
| الدفعة تبقى "في الانتظار" | لا يوجد عامل طابور | `supervisorctl status`, راجع ودجت "مهام في الانتظار" في اللوحة |
| فشل التوليد: `Could not find Chrome` | Chrome غير محمّل لمستخدم الطابور | `npx puppeteer browsers install chrome` بنفس المستخدم، أو `CERT_CHROME_PATH` |
| فشل التوليد: `Running as root without --no-sandbox` | العامل يعمل كـ root | شغّله بمستخدم الموقع أو `CERT_NO_SANDBOX=true` |
| فشل التوليد: مهلة | خادم بطيء | ارفع `CERT_RENDER_TIMEOUT` |
| الشهادة بخط غير عربي | لم تُنسخ `public/fonts` | تأكد من نشر المجلد كاملاً |
| الواتساب "غير مضبوط" | مفاتيح `WHATSAPP_*` فارغة | اضبطها ثم `php artisan config:cache` |
