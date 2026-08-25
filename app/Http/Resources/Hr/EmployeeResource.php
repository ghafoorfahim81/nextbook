<?php

namespace App\Http\Resources\Hr;

use App\Enums\EmploymentStatus;
use App\Enums\EmploymentType;
use App\Enums\Gender;
use App\Enums\MaritalStatus;
use App\Enums\PaymentMode;
use App\Http\Resources\AttachmentResource;
use App\Services\DateConversionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $dates = app(DateConversionService::class);

        $type = $this->employment_type instanceof EmploymentType
            ? $this->employment_type
            : EmploymentType::tryFrom((string) $this->employment_type);
        $status = $this->employment_status instanceof EmploymentStatus
            ? $this->employment_status
            : EmploymentStatus::tryFrom((string) $this->employment_status);
        $gender = $this->gender instanceof Gender
            ? $this->gender
            : Gender::tryFrom((string) $this->gender);
        $marital = $this->marital_status instanceof MaritalStatus
            ? $this->marital_status
            : MaritalStatus::tryFrom((string) $this->marital_status);
        $payment = $this->payment_method instanceof PaymentMode
            ? $this->payment_method
            : PaymentMode::tryFrom((string) $this->payment_method);

        return [
            'id' => $this->id,
            'code' => $this->code,

            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'father_name' => $this->father_name,
            'grand_father_name' => $this->grand_father_name,
            'full_name' => $this->full_name,

            'gender' => $gender?->value,
            'gender_label' => $gender?->getLabel(),
            'marital_status' => $marital?->value,
            'marital_status_label' => $marital?->getLabel(),
            'date_of_birth' => $dates->toDisplay($this->date_of_birth),

            'national_id' => $this->national_id,
            'passport_number' => $this->passport_number,
            'tin' => $this->tin,
            'blood_group' => $this->blood_group,

            'country_id' => $this->country_id,
            'country_name' => $this->country?->localized_name,
            'province_id' => $this->province_id,
            'province_name' => $this->province?->localized_name,

            'phone_number' => $this->phone_number,
            'alternate_phone' => $this->alternate_phone,
            'whatsapp_number' => $this->whatsapp_number,
            'email' => $this->email,
            'present_address' => $this->present_address,
            'permanent_address' => $this->permanent_address,

            'emergency_contact_name' => $this->emergency_contact_name,
            'emergency_contact_phone' => $this->emergency_contact_phone,
            'emergency_contact_relation' => $this->emergency_contact_relation,

            'photo' => $this->photo,
            'photo_url' => $this->photo_url,

            'user_id' => $this->user_id,
            'user_name' => $this->user?->name,
            'department_id' => $this->department_id,
            'department_name' => $this->department?->name,
            'designation_id' => $this->designation_id,
            'designation_name' => $this->designation?->name,
            'reports_to_id' => $this->reports_to_id,
            'manager_name' => $this->manager?->full_name,

            'employment_type' => $type?->value,
            'employment_type_label' => $type?->getLabel(),
            'employment_status' => $status?->value,
            'employment_status_label' => $status?->getLabel(),

            'joining_date' => $dates->toDisplay($this->joining_date),
            'probation_end_date' => $dates->toDisplay($this->probation_end_date),
            'confirmation_date' => $dates->toDisplay($this->confirmation_date),
            'separation_date' => $dates->toDisplay($this->separation_date),
            'separation_reason' => $this->separation_reason,

            'currency_id' => $this->currency_id,
            'currency_code' => $this->currency?->code,
            'basic_salary' => (float) $this->basic_salary,

            'payment_method' => $payment?->value,
            'payment_method_label' => $payment?->getLabel(),
            'bank_name' => $this->bank_name,
            'bank_account_number' => $this->bank_account_number,
            'bank_account_title' => $this->bank_account_title,
            'iban' => $this->iban,

            'is_tax_exempt' => (bool) $this->is_tax_exempt,
            'self_service_enabled' => (bool) $this->self_service_enabled,
            'is_active' => (bool) $this->is_active,
            'remark' => $this->remark,

            // The financial half. Exposed so the Show page can render the
            // employee's salary-payable position and open a statement.
            'ledger_id' => $this->ledger_id,
            'ledger_statement' => $this->whenLoaded('ledger', fn () => $this->ledger?->statement),

            'contracts' => EmployeeContractResource::collection($this->whenLoaded('contracts')),
            'documents' => EmployeeDocumentResource::collection($this->whenLoaded('documents')),
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),

            'created_by' => $this->createdBy?->name,
            'updated_by' => $this->updatedBy?->name,
        ];
    }
}
