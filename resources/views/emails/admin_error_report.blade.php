@component('mail::message')
	# Certificate Job Finished

	The certificate‑generation batch completed with **{{ $count }} error{{ $count > 1 ? 's' : '' }}**.

	> The attached **certificate_errors.xlsx** lists each failed row and the reason.

	Thanks for keeping an eye on things!
	{{ config('app.name') }}
@endcomponent
