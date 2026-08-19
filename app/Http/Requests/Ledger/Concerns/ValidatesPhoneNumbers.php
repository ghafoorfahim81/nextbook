<?php

namespace App\Http\Requests\Ledger\Concerns;

trait ValidatesPhoneNumbers
{
    /**
     * Phone fields are captured as E.164 ("+93773502152") by NextPhoneInput.
     * The leading "+" stays optional so records written before the field went
     * international — bare local digits — still validate when they are edited
     * without touching the phone.
     *
     * The 15-digit ceiling is E.164's, which is what keeps the field from
     * accepting an arbitrarily long run of digits.
     */
    protected function phoneRules(): array
    {
        return ['nullable', 'string', 'regex:/^\+?[0-9]{6,15}$/'];
    }

    /**
     * @return array<string, string>
     */
    protected function phoneMessages(): array
    {
        $message = __('general.invalid_phone_number');

        return [
            'phone_no.regex' => $message,
            'whatsapp_number.regex' => $message,
        ];
    }
}
