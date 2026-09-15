@component('mail::message')
# Certificate needs manual sending

A certificate was generated but could not be emailed to the recipient.

- **Row:** {{ $cert['Row'] ?? 'N/A' }}
- **Name:** {{ $cert['Name'] ?? 'N/A' }}
- **Title:** {{ $cert['Title'] ?? 'N/A' }}
- **Email:** {{ $cert['Email'] ?? 'N/A' }}
- **Reason:** {{ $cert['Error'] ?? 'Unknown' }}

The certificate PDF is attached. Please send it manually to the recipient.

{{ config('app.name') }}
@endcomponent
