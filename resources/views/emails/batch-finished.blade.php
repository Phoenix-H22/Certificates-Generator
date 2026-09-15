<x-mail::message>
# انتهت معالجة الدفعة «{{ $batch->name }}»

**الحالة:** {{ $batch->status->label() }}

| البند | العدد |
|:--|--:|
| إجمالي الصفوف | {{ $batch->total_rows }} |
| شهادات تم توليدها | {{ $batch->rendered_count }} |
| فشل التوليد / بيانات غير صالحة | {{ $batch->render_failed_count }} |
| بريد إلكتروني مُرسل | {{ $batch->email_sent_count }} |
| بريد إلكتروني فشل | {{ $batch->email_failed_count }} |
| واتساب مُرسل | {{ $batch->whatsapp_sent_count }} |
| واتساب فشل | {{ $batch->whatsapp_failed_count }} |

@if ($batch->error_report_path)
تقرير الأخطاء مرفق بهذه الرسالة، ويمكن تنزيله من لوحة التحكم مع ملف ZIP الذي يضم كل الشهادات.
@else
لم تُسجَّل أي أخطاء. يمكن تنزيل ملف ZIP الذي يضم كل الشهادات من لوحة التحكم.
@endif

<x-mail::button :url="$panelUrl">
فتح الدفعة في لوحة التحكم
</x-mail::button>

{{ config('app.name') }}
</x-mail::message>
