<?php

namespace App\Http\Requests\Hr;

class PayrollUpdateRequest extends PayrollStoreRequest
{
    // The parent's overlap check already excludes the run being edited.
}
