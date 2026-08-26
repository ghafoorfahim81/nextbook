<?php

namespace App\Http\Requests\Administration;

/**
 * Same rules as the store request — landedCostCategoryId() resolves the route
 * model, so uniqueness already excludes this record.
 */
class LandedCostCategoryUpdateRequest extends LandedCostCategoryStoreRequest
{
}
