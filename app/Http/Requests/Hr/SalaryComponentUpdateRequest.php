<?php

namespace App\Http\Requests\Hr;

class SalaryComponentUpdateRequest extends SalaryComponentStoreRequest
{
    // Identical rules; the parent already excludes the record being edited
    // from its uniqueness check via componentId().
}
