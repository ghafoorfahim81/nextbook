<?php

namespace App\Http\Requests\Administration;

/**
 * Same rules as the store request — designationId() resolves the route model,
 * so uniqueness already excludes this record.
 */
class DesignationUpdateRequest extends DesignationStoreRequest
{
}
