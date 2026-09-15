@component('mail::message')
# Certificate Job Finished

The certificate-generation batch completed.

- **Total rows:** {{ $totalRows }}
- **Sent successfully:** {{ $successCount }}
- **Failed / need attention:** {{ $errorCount }}

@if($errorCount > 0)
> The attached **certificate_errors.xlsx** lists each failed row and the reason.
@if(($failedPdfCount ?? 0) > 0)
> **{{ $failedPdfCount }} certificate PDF(s)** for failed rows are also attached for manual sending.
@endif
@else
All certificates were generated and queued for delivery.
@endif

Thanks for keeping an eye on things!

{{ config('app.name') }}
@endcomponent
