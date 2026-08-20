<?php

namespace App\Http\Requests\Hr;

use App\Enums\TaxPeriod;
use App\Services\DateConversionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * A wage-tax bracket table.
 *
 * The bracket validation here is the point of the class. A table with a GAP
 * silently taxes nobody whose income falls inside it, and a table with an
 * OVERLAP taxes them twice — both produce plausible-looking payslips that are
 * wrong for a subset of staff, and nobody notices for a year. So gaps and
 * overlaps are rejected outright rather than tolerated.
 */
class TaxBracketSetStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $dates = app(DateConversionService::class);

        $this->merge(array_filter([
            'effective_from' => $this->filled('effective_from')
                ? $dates->toGregorian((string) $this->input('effective_from'))
                : null,
            'effective_to' => $this->filled('effective_to')
                ? $dates->toGregorian((string) $this->input('effective_to'))
                : null,
        ], fn ($value) => $value !== null));
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'jurisdiction' => ['nullable', 'string', 'max:10'],
            'period' => ['required', Rule::in(TaxPeriod::values())],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'currency_id' => ['nullable', 'string', 'exists:currencies,id'],
            'is_active' => ['nullable', 'boolean'],
            'remark' => ['nullable', 'string'],

            'brackets' => ['required', 'array', 'min:1'],
            'brackets.*.sequence' => ['required', 'integer', 'min:1'],
            'brackets.*.from_amount' => ['required', 'numeric', 'min:0'],
            'brackets.*.to_amount' => ['nullable', 'numeric', 'gt:brackets.*.from_amount'],
            'brackets.*.fixed_amount' => ['required', 'numeric', 'min:0'],
            'brackets.*.rate' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $brackets = collect((array) $this->input('brackets', []))
                ->sortBy(fn ($bracket) => (int) ($bracket['sequence'] ?? 0))
                ->values();

            if ($brackets->isEmpty()) {
                return;
            }

            $sequences = $brackets->pluck('sequence');

            if ($sequences->count() !== $sequences->unique()->count()) {
                $validator->errors()->add('brackets', __('hr.duplicate_bracket_sequence'));
            }

            // The first band must start at zero, or income below it is taxed
            // by no band at all.
            if ((float) ($brackets->first()['from_amount'] ?? 0) !== 0.0) {
                $validator->errors()->add('brackets.0.from_amount', __('hr.first_bracket_starts_at_zero'));
            }

            // The last band must be open-ended. A ceiling on the top band means
            // the highest earners fall outside the table entirely.
            if (($brackets->last()['to_amount'] ?? null) !== null
                && $brackets->last()['to_amount'] !== '') {
                $validator->errors()->add(
                    'brackets.'.($brackets->count() - 1).'.to_amount',
                    __('hr.last_bracket_is_open_ended')
                );
            }

            // Contiguity: each band starts exactly where the previous one
            // ended. Anything else is a gap or an overlap.
            foreach ($brackets as $index => $bracket) {
                if ($index === 0) {
                    continue;
                }

                $previousTo = $brackets[$index - 1]['to_amount'] ?? null;

                if ($previousTo === null || $previousTo === '') {
                    // An open-ended band before the last one swallows
                    // everything after it.
                    $validator->errors()->add(
                        'brackets.'.($index - 1).'.to_amount',
                        __('hr.only_last_bracket_is_open_ended')
                    );

                    continue;
                }

                if ((float) $bracket['from_amount'] !== (float) $previousTo) {
                    $validator->errors()->add(
                        'brackets.'.$index.'.from_amount',
                        __('hr.brackets_must_be_contiguous')
                    );
                }
            }
        });
    }
}
