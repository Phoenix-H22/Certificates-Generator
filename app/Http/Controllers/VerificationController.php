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
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Public certificate verification: /verify?code=… and /verify/{identifier}.
 *
 * Only two shapes are ever looked up: a UUID or a 10-symbol short code.
 * Everything else is rejected before touching the database, and all
 * queries go through Eloquent bindings (no raw SQL).
 */
class VerificationController extends Controller
{
    public function search(Request $request): View|RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['nullable', 'string', 'max:64'],
        ]);

        $code = trim((string) ($validated['code'] ?? ''));
        $organisation = OrganisationSettings::current();

        if ($code === '') {
            return view('verify.search', ['organisation' => $organisation]);
        }

        $identifier = self::canonicalIdentifier($code);

        if ($identifier === null) {
            return view('verify.search', [
                'organisation' => $organisation,
                'error' => 'الصيغة غير صحيحة. أدخل كود التحقق المكوّن من 10 رموز (مثل ABCDE-FGH23) أو رقم الشهادة الكامل.',
                'previous' => Str::limit($code, 64, ''),
            ]);
        }

        return redirect()->route('verify.show', ['identifier' => $identifier]);
    }

    public function show(Request $request, string $identifier): View|Response
    {
        $organisation = OrganisationSettings::current();
        $canonical = self::canonicalIdentifier($identifier);

        $certificate = $canonical === null
            ? null
            : Certificate::byIdentifier($canonical)->with('template', 'batch')->first();

        if ($certificate === null) {
            return response()->view('verify.show', [
                'state' => 'not_found',
                'identifier' => Str::limit($identifier, 64, ''),
                'organisation' => $organisation,
                'certificate' => null,
                'details' => [],
            ], 404);
        }

        $this->countVerification($request, $certificate);

        return view('verify.show', [
            'state' => $certificate->isRevoked() ? 'revoked' : ($certificate->isRendered() ? 'valid' : 'pending'),
            'identifier' => $canonical,
            'organisation' => $organisation,
            'certificate' => $certificate,
            'details' => $this->details($certificate),
        ]);
    }

    /** Lower-case UUID or formatted short code, or null when the input is neither. */
    public static function canonicalIdentifier(string $input): ?string
    {
        $input = trim($input);

        if (Str::isUuid($input)) {
            return strtolower($input);
        }

        return VerificationCode::canonical($input);
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
