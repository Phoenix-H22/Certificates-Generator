# Changelog

## 2.0.0 — 2026-09-15 (branch `feat/laravel-12`)

إعادة بناء كاملة على Laravel 12 / PHP 8.2+ مع Filament v4.

### أُضيف
- أربعة تصاميم شهادات احترافية (كلاسيكي ذهبي، حديث بسيط، رسمي مؤسسي، أنيق داكن) تُرندر بـ Chromium من HTML/CSS بخطوط عربية مضمّنة.
- رمز QR + UUID + كود قصير على كل شهادة، وصفحة تحقق عامة `/verify`.
- مكتبة الشعارات والتوقيعات والأختام، مع تحكم في موضع الختم وميله وشفافيته.
- حقول ديناميكية لكل قالب (ثابتة للدفعة أو لكل صف في Excel) مع ربط تلقائي لأعمدة الملف.
- دفعات بحالة وتقدّم لحظي، ملف ZIP لكل الشهادات، تقرير أخطاء xlsx، إعادة إرسال/إعادة توليد/إلغاء من اللوحة.
- إرسال بريد إلكتروني وواتساب فعلي لكل شهادة مع روابط تنزيل موقّعة منتهية الصلاحية.
- لوحة تحكم Filament عربية RTL: القوالب، الأصول، الدفعات، الشهادات، بيانات الجهة، ودجت صحة الطابور.
- أوامر Artisan: `certificates:batch`, `certificates:render-sample`, `certificates:thumbnails`, `certificates:prune`.
- 80+ اختبار Pest، Pint، وGitHub Actions (فحص، اختبارات، رندر حقيقي بـ Chromium، cache smoke).

### تغيّر
- المكتبة الوحيدة للـ PDF أصبحت `spatie/browsershot`؛ أزيلت dompdf/mpdf/snappy/html2pdf/tcpdf/phpword وباينريات wkhtmltopdf.
- الملفات المولّدة لم تعد داخل `public/`؛ تُخزَّن في disk خاص وتُقدَّم عبر مسارات مصرّح بها أو موقّعة.
- تسجيل الدخول عبر لوحة Filament فقط (`/admin/login`)؛ أُزيل `laravel/ui` والتسجيل الذاتي؛ الأدمن يُحدَّد بعمود `users.is_admin`.
- `env()` لم يعد يُستدعى خارج `config/`؛ الإعدادات الجديدة في `config/certificates.php` و`config/services.php`.

### أُزيل
- رفع قوالب Word (.docx) والتحويل عبر LibreOffice.
- صفحة الهبوط القديمة والنشرة البريدية (`subscriped_users`) وجدول `settings`.
