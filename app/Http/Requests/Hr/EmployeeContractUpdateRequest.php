<?php

namespace App\Http\Requests\Hr;

/**
 * Identical rules to the store request — contractId() already resolves the
 * route model, so uniqueness and the overlap check both exclude this record.
 */
class EmployeeContractUpdateRequest extends EmployeeContractStoreRequest
{
}
