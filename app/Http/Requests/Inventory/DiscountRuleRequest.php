<?php

namespace App\Http\Requests\Inventory;

use App\Enums\DiscountScope;
use App\Enums\DiscountType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class DiscountRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'scope' => ['required', 'string', Rule::in(DiscountScope::values())],
            'scope_id' => ['nullable', 'string'],
            'discount_type' => ['required', 'string', Rule::in(DiscountType::values())],
            'value' => ['required', 'numeric', 'min:0'],
            'customer_group_id' => ['nullable', 'string', 'exists:customer_groups,id'],
            'min_quantity' => ['nullable', 'numeric', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'priority' => ['nullable', 'integer'],
            'is_active' => ['boolean'],
            'show_on_invoice' => ['boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $scope = DiscountScope::tryFrom((string) $this->input('scope'));

            // ALL needs no target; every other scope is meaningless without one.
            if ($scope && $scope->requiresTarget() && blank($this->input('scope_id'))) {
                $validator->errors()->add('scope_id', __('validation.required', ['attribute' => 'target']));
            }

            if ($scope && $scope->requiresTarget() && filled($this->input('scope_id'))) {
                $table = match ($scope) {
                    DiscountScope::ITEM => 'items',
                    DiscountScope::CATEGORY => 'categories',
                    DiscountScope::BRAND => 'brands',
                    default => null,
                };

                if ($table && ! \DB::table($table)->where('id', $this->input('scope_id'))->exists()) {
                    $validator->errors()->add('scope_id', __('validation.exists', ['attribute' => 'target']));
                }
            }

            // A percentage over 100 would hand money back to the customer.
            if ($this->input('discount_type') === DiscountType::PERCENTAGE->value && (float) $this->input('value') > 100) {
                $validator->errors()->add('value', __('validation.max.numeric', ['attribute' => 'value', 'max' => 100]));
            }

            $starts = $this->input('starts_at');
            $ends = $this->input('ends_at');

            if (filled($starts) && filled($ends) && strtotime($ends) < strtotime($starts)) {
                $validator->errors()->add('ends_at', __('validation.after_or_equal', ['attribute' => 'end date', 'date' => 'start date']));
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'show_on_invoice' => $this->boolean('show_on_invoice'),
            // The target picker is cleared when the scope switches to ALL.
            'scope_id' => $this->input('scope') === DiscountScope::ALL->value ? null : $this->input('scope_id'),
        ]);
    }
}
