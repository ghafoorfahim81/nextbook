<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\DiscountScope;
use App\Enums\DiscountType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\DiscountRuleRequest;
use App\Http\Resources\Inventory\DiscountRuleResource;
use App\Models\Administration\Brand;
use App\Models\Administration\Category;
use App\Models\Administration\CustomerGroup;
use App\Models\Inventory\DiscountRule;
use App\Models\Inventory\Item;
use App\Services\DateConversionService;
use Illuminate\Http\Request;

class DiscountRuleController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(DiscountRule::class, 'discount_rule');
    }

    public function index(Request $request)
    {
        $rules = DiscountRule::query()
            ->with('customerGroup')
            ->search($request->query('search'))
            ->orderByDesc('is_active')
            ->orderByDesc('priority')
            ->orderBy('name')
            ->paginate($request->integer('perPage', recordsPerPage()))
            ->withQueryString();

        return inertia('Inventories/DiscountRules/Index', [
            // A resource collection, not the raw paginator: DataTable reads
            // items.meta.* for its pagination footer.
            'discountRules' => DiscountRuleResource::collection($rules),
            'options' => [
                'scopes' => collect(DiscountScope::cases())
                    ->map(fn ($case) => ['id' => $case->value, 'name' => $case->getLabel()])->values(),
                'discountTypes' => collect(DiscountType::cases())
                    ->map(fn ($case) => ['id' => $case->value, 'name' => $case->getLabel()])->values(),
                'items' => Item::query()->orderBy('name')->get(['id', 'name']),
                'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
                'brands' => Brand::query()->orderBy('name')->get(['id', 'name']),
                'customerGroups' => CustomerGroup::query()->orderBy('name_en')->get(['id', 'name_en as name']),
            ],
            'filters' => [
                'search' => $request->query('search'),
                'perPage' => $request->integer('perPage', recordsPerPage()),
            ],
        ]);
    }

    public function store(DiscountRuleRequest $request)
    {
        DiscountRule::create($this->withGregorianDates($request->validated()));

        return back()->with('success', __('general.created_successfully', ['resource' => __('discount_rule.discount_rule')]));
    }

    public function update(DiscountRuleRequest $request, DiscountRule $discountRule)
    {
        $discountRule->update($this->withGregorianDates($request->validated()));

        return back()->with('success', __('general.updated_successfully', ['resource' => __('discount_rule.discount_rule')]));
    }

    /**
     * The date picker posts in the user's own calendar, so a Jalali user would
     * otherwise store year 1405 as if it were Gregorian.
     */
    private function withGregorianDates(array $validated): array
    {
        $dates = app(DateConversionService::class);

        foreach (['starts_at', 'ends_at'] as $field) {
            $validated[$field] = filled($validated[$field] ?? null)
                ? $dates->toGregorian($validated[$field])
                : null;
        }

        return $validated;
    }

    public function destroy(DiscountRule $discountRule)
    {
        $discountRule->delete();

        return back()->with('success', __('general.deleted_successfully', ['resource' => __('discount_rule.discount_rule')]));
    }
}
