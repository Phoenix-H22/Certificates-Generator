@component('mail::message')
# Failed Email Certificates

The certificate generation job completed, but **{{ $count }} certificate(s)** could not be sent via email automatically.

## Certificates Requiring Manual Sending

The following certificates are attached to this email. Please send them manually to the recipients:

@component('mail::table')
| Name | Email | Title | Error |
|------|-------|-------|-------|
@foreach($failedCertificates as $cert)
| {{ $cert['Name'] ?? 'N/A' }} | {{ $cert['Email'] ?? 'N/A' }} | {{ $cert['Title'] ?? 'N/A' }} | {{ $cert['Error'] ?? 'Unknown error' }} |
@endforeach
@endcomponent

**Note:** All certificate PDFs are attached to this email. Please send each certificate to the corresponding recipient email address.

Thanks for your attention!

{{ config('app.name') }}
@endcomponent

