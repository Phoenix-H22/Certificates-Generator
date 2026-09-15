<?php

namespace App\Http\Controllers;

use App\Certificates\Support\ArabicText;
use App\Certificates\Support\VerificationCode;
use App\Enums\FieldType;
use App\Models\Certificate;
use App\Models\OrganisationSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/** Public certificate verification: /verify?code=… and /verify/{identifier}. */
class VerificationController extends Controller
{
    public function search(Request $request): View|RedirectResponse
    {
        $code = trim((string) $request->query('code', ''));

        if ($code === '') {
            return view('verify.search', ['organisation' => OrganisationSettings::current()]);
        }

        $identifier = Str::isUuid($code) ? strtolower($code) : (VerificationCode::canonical($code) ?? $code);

        return redirect()->route('verify.show', ['identifier' => $identifier]);
    }

    public function show(Request $request, string $identifier): View
    {
        $organisation = OrganisationSettings::current();
        $certificate = Certificate::byIdentifier($identifier)->with('template', 'batch')->first();

        if ($certificate === null) {
            return response()->view('verify.show', [
                'state' => 'not_found',
                'identifier' => $identifier,
                'organisation' => $organisation,
                'certificate' => null,
                'details' => [],
            ], 404);
        }

        $this->countVerification($request, $certificate);

        return view('verify.show', [
            'state' => $certificate->isRevoked() ? 'revoked' : ($certificate->isRendered() ? 'valid' : 'pending'),
            'identifier' => $identifier,
            'organisation' => $organisation,
            'certificate' => $certificate,
            'details' => $this->details($certificate),
        ]);
    }

    /** Count at most one verification per code + IP per hour. */
    private function countVerification(Request $request, Certificate $certificate): void
    {
        $key = 'verify:'.$certificate->id.':'.sha1((string) $request->ip());

        if (Cache::add($key, true, now()->addHour())) {
            $certificate->increment('verified_count', 1, ['last_verified_at' => now()]);
        }
    }

    /** @return list<array{label: string, value: string}> */
    private function details(Certificate $certificate): array
    {
        $details = [];
        $template = $certificate->template;

        if ($template === null) {
            return $details;
        }

        foreach ($template->fields_schema as $field) {
            if (! $field->showOnVerify) {
                continue;
            }

            $raw = $certificate->data[$field->key] ?? null;

            if ($raw === null || $raw === '') {
                continue;
            }

            $details[] = [
                'label' => $field->label,
                'value' => $field->type === FieldType::Date ? ArabicText::date($raw) : (string) $raw,
            ];
        }

        return $details;
    }
}
