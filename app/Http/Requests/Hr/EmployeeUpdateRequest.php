<?php

namespace App\Http\Requests\Hr;

use App\Models\Hr\Employee;
use Illuminate\Validation\Validator;

/**
 * Same shape as the store request, with uniqueness ignoring this record and the
 * reporting line checked for loops (only reachable on update — a brand new
 * employee has no id to point at).
 */
class EmployeeUpdateRequest extends EmployeeStoreRequest
{
    public function rules(): array
    {
        $employee = $this->route('employee');
        $id = $employee instanceof Employee ? $employee->id : $employee;

        return array_merge(parent::rules(), [
            // Each of these matches its unique index, and the three indexes are
            // not all at the same level on purpose:
            //
            //  - `code` is the branch's own series, so Kabul and Herat may both
            //    hand out E-001. This rule used to say company, which let a
            //    code be accepted on create and then refused on the very next
            //    save of the same record.
            //  - `national_id` identifies the person, and a person is one
            //    employee per company however many branches there are.
            //  - `user_id` follows the same reasoning: one account belongs to
            //    one person, so two employee records sharing it inside a
            //    company would be the duplicate `national_id` already forbids.
            'code' => ['required', 'string', 'max:50', $this->uniqueInBranch('employees', $id)],
            'national_id' => ['nullable', 'string', 'max:50', $this->uniqueInCompany('employees', $id)],
            'user_id' => ['nullable', 'exists:users,id', $this->uniqueInCompany('employees', $id)],
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        parent::withValidator($validator);

        $validator->after(function (Validator $validator) {
            $this->assertReportingLineIsAcyclic($validator);
        });
    }

    /**
     * A reporting line that loops back on itself makes the org chart infinite
     * and hangs any recursive walk of it. Walking upward from the proposed
     * manager is cheap and catches both self-reference and longer cycles.
     */
    protected function assertReportingLineIsAcyclic(Validator $validator): void
    {
        $employee = $this->route('employee');
        $selfId = $employee instanceof Employee ? (string) $employee->id : (string) $employee;
        $managerId = $this->input('reports_to_id');

        if (! $managerId || ! $selfId) {
            return;
        }

        if ($managerId === $selfId) {
            $validator->errors()->add('reports_to_id', __('hr.validation.manager_cannot_be_self'));

            return;
        }

        $seen = [];
        $cursor = $managerId;

        while ($cursor) {
            if ($cursor === $selfId) {
                $validator->errors()->add('reports_to_id', __('hr.validation.manager_cycle'));

                return;
            }

            // Guards against a pre-existing loop elsewhere in the chain, which
            // would otherwise spin here forever.
            if (isset($seen[$cursor])) {
                return;
            }

            $seen[$cursor] = true;
            $cursor = Employee::where('id', $cursor)->value('reports_to_id');
        }
    }
}
