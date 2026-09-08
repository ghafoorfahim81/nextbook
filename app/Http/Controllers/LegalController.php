<?php

namespace App\Http\Controllers;

use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Serves the public Terms of Service and Privacy Policy.
 *
 * Jetstream ships equivalent routes, but only when
 * Features::termsAndPrivacyPolicy() is enabled — which also makes the
 * registration form require a terms checkbox. That feature stays off in
 * config/jetstream.php, so these routes own the `terms.show` / `policy.show`
 * names and there is no clash.
 */
class LegalController extends Controller
{
    /**
     * Locales that read right-to-left. Used to set the document's own base
     * direction, which is not always the UI direction — see document().
     */
    private const RTL_LOCALES = ['fa', 'ps'];

    /**
     * Effective dates are an editorial decision, not a file timestamp, so they
     * are declared here and must be bumped whenever the markdown changes.
     */
    private const EFFECTIVE_DATES = [
        'en' => '8 September 2026',
        'fa' => '۸ سپتامبر ۲۰۲۶',
        'ps' => '۸ سپټمبر ۲۰۲۶',
    ];

    public function terms(): Response
    {
        return $this->document('terms', 'auth.terms_of_service');
    }

    public function privacy(): Response
    {
        return $this->document('policy', 'auth.privacy_policy');
    }

    /**
     * @param  string  $titleKey  vue-i18n key — the frontend owns translations.
     */
    private function document(string $name, string $titleKey): Response
    {
        $locale = app()->getLocale();
        $path = resource_path("markdown/{$locale}/{$name}.md");
        $translated = is_file($path);

        if (! $translated) {
            // Fall back to the English source when a locale has no translated copy.
            $path = resource_path("markdown/{$name}.md");
        }

        // The document's direction follows the language it is actually written
        // in, not the interface language. Without this, an English fallback
        // rendered inside an RTL page gets an RTL base direction and its
        // punctuation jumps to the wrong end of every line.
        $bodyLocale = $translated ? $locale : 'en';

        return Inertia::render('Legal/LegalDocument', [
            'titleKey' => $titleKey,
            'body' => Str::markdown(file_get_contents($path)),
            'bodyLocale' => $bodyLocale,
            'bodyDir' => in_array($bodyLocale, self::RTL_LOCALES, true) ? 'rtl' : 'ltr',
            'updatedAt' => self::EFFECTIVE_DATES[$bodyLocale] ?? self::EFFECTIVE_DATES['en'],
        ]);
    }
}
