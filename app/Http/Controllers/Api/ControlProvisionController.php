<?php

namespace App\Http\Controllers\Api;

use App\Enums\BusinessType;
use App\Enums\CalendarType;
use App\Enums\Locale;
use App\Enums\WorkingStyle;
use App\Http\Controllers\Controller;
use App\Services\CompanySetupService;
use App\Services\Control\CompanyProvisioner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * The control center asks for a company to be made for one of its customers.
 * Reached only through VerifyControlSignature, so the request is known to come
 * from the control center; this still validates every field as if it did not.
 */
class ControlProvisionController extends Controller
{
    public function __invoke(Request $request, CompanyProvisioner $provisioner): JsonResponse
    {
        $enum = fn (string $class) => Rule::in(array_column($class::cases(), 'value'));

        $validator = Validator::make($request->json()->all(), [
            'customer_id' => ['required', 'string', 'size:26'],
            'token' => ['required', 'string', 'min:20', 'max:255'],

            'company' => ['required', 'array'],
            'company.name_en' => ['required', 'string', 'max:255'],
            'company.name_fa' => ['nullable', 'string', 'max:255'],
            'company.name_pa' => ['nullable', 'string', 'max:255'],
            'company.abbreviation' => ['nullable', 'string', 'max:50'],
            'company.address' => ['nullable', 'string', 'max:1000'],
            'company.phone' => ['nullable', 'string', 'max:20'],
            'company.country' => ['nullable', 'string', 'max:100'],
            'company.city' => ['nullable', 'string', 'max:100'],
            'company.email' => ['nullable', 'email', 'max:255'],
            'company.website' => ['nullable', 'url', 'max:255'],
            'company.calendar_type' => ['required', $enum(CalendarType::class)],
            'company.working_style' => ['sometimes', $enum(WorkingStyle::class)],
            'company.business_type' => ['required', $enum(BusinessType::class)],
            'company.locale' => ['required', $enum(Locale::class)],
            'company.base_currency' => ['required', 'string', Rule::in(CompanySetupService::baseCurrencyCodes())],

            'admin' => ['required', 'array'],
            'admin.name' => ['required', 'string', 'max:255'],
            'admin.email' => ['required', 'email', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'The request is not valid.', 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        // The administrator's e-mail must be free, unless this is a repeat of a
        // request that already made the company (the e-mail is then its own).
        if (! $provisioner->existing($data['customer_id'])
            && \App\Models\User::query()->where('email', $data['admin']['email'])->exists()) {
            return response()->json([
                'message' => 'That e-mail already belongs to a user.',
                'errors' => ['admin.email' => ['That e-mail already belongs to a user.']],
            ], 422);
        }

        $result = $provisioner->provision($data);

        return response()->json([
            'created' => $result['created'],
            'company_id' => $result['company']->id,
            'branch_id' => $result['branch_id'],
            'license_ok' => $result['license']['ok'],
            'license_message' => $result['license']['message'],
        ], $result['created'] ? 201 : 200);
    }
}
