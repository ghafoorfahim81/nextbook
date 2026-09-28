<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * The human name of the document behind a journal entry.
 *
 * `transactions.reference_type` is written inconsistently across the codebase:
 * most writers store a FQCN, a few store a bare slug like `reversal`, and a
 * transaction with no reference at all is a manual journal. Every screen that
 * showed the type ran its own `class_basename()` and printed the result, so a
 * Persian statement said "Contra Settlement" and a Persian report said "Sale".
 *
 * Translating here rather than in the front end covers the Excel and PDF
 * exports too — they are rendered server-side and never see a Vue component —
 * and keeps one set of translations instead of one per rendering path.
 *
 * A reference type nobody has translated yet falls back to its English
 * headline, so a new document shows "Salary Payment" rather than a raw
 * `document_type.salary_payment` on screen.
 */
final class DocumentType
{
    /**
     * The label to show, in the request's language.
     *
     * `$isOpening` is passed by callers that already know which transactions
     * are ledger openings — that fact lives in `ledger_openings`, not in the
     * reference type, and looking it up per row would be a query per line.
     */
    public static function label(?string $referenceType, bool $isOpening = false): string
    {
        $key = self::key($referenceType, $isOpening);
        $translation = __('document_type.'.$key);

        // __() hands back the key itself when there is no entry for it.
        return $translation === 'document_type.'.$key
            ? Str::of($key)->headline()->toString()
            : $translation;
    }

    /** A stable snake_case slug: `sale`, `purchase_return`, `contra_settlement`. */
    public static function key(?string $referenceType, bool $isOpening = false): string
    {
        if ($isOpening) {
            return 'opening_balance';
        }

        if (! $referenceType) {
            return 'journal';
        }

        $base = str_contains($referenceType, '\\')
            ? class_basename($referenceType)
            : $referenceType;

        return Str::snake($base);
    }
}
