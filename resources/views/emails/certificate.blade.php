<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $organisation }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f1ea;font-family:'Segoe UI',Tahoma,Arial,sans-serif;color:#1f2937;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f1ea;padding:24px 12px;">
<tr><td align="center">
<table role="presentation" width="600" cellspacing="0" cellpadding="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:12px;border-top:6px solid #b08d57;">
<tr><td style="padding:28px 28px 8px;text-align:right;direction:rtl;font-size:16px;line-height:1.9;white-space:pre-line;">{{ $body }}</td></tr>
<tr><td style="padding:8px 28px 24px;text-align:right;direction:rtl;font-size:14px;line-height:1.8;">
    <p style="margin:0 0 6px;">الشهادة مرفقة بهذه الرسالة بصيغة PDF.</p>
    <p style="margin:0 0 6px;">رابط بديل لتنزيل الشهادة (صالح لفترة محدودة):<br><a href="{{ $downloadUrl }}" style="color:#8a6a3c;word-break:break-all;">{{ $downloadUrl }}</a></p>
    <p style="margin:0;">للتحقق من صحة الشهادة: <a href="{{ $verifyUrl }}" style="color:#8a6a3c;">{{ $verifyUrl }}</a><br>كود التحقق: <bdi style="font-family:monospace;font-weight:bold;">{{ $code }}</bdi></p>
</td></tr>
<tr><td style="padding:12px 28px 20px;text-align:center;font-size:12px;color:#6b7280;border-top:1px solid #eee;">{{ $organisation }}</td></tr>
</table>
</td></tr>
</table>
</body>
</html>
