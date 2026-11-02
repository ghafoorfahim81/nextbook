<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Platform\Package;
use App\Support\Modules;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The packages the platform sells (phase 10): which modules, how many users
 * and branches, and the price. Behind EnsurePlatformAdmin.
 */
class PackageController extends Controller
{
    public function index()
    {
        return inertia('Platform/Packages/Index', [
            'packages' => Package::query()
                ->withCount('subscriptions')
                ->orderBy('price')
                ->orderBy('name')
                ->get()
                ->map(fn (Package $package) => [
                    'id' => $package->id,
                    'code' => $package->code,
                    'name' => $package->name,
                    'description' => $package->description,
                    'modules' => $package->modules,
                    'max_users' => $package->max_users,
                    'max_branches' => $package->max_branches,
                    'price' => (float) $package->price,
                    'currency' => $package->currency,
                    'period_months' => $package->period_months,
                    'is_active' => $package->is_active,
                    'subscriptions_count' => $package->subscriptions_count,
                ]),
            'modules' => Modules::available(),
        ]);
    }

    public function store(Request $request)
    {
        Package::create($this->validated($request));

        return back()->with('success', __('general.created_successfully', ['resource' => __('general.resource.package')]));
    }

    public function update(Request $request, string $package)
    {
        $model = Package::query()->findOrFail($package);
        $model->update($this->validated($request, $model));

        return back()->with('success', __('general.updated_successfully', ['resource' => __('general.resource.package')]));
    }

    public function destroy(string $package)
    {
        $model = Package::query()->withCount('subscriptions')->findOrFail($package);

        if ($model->subscriptions_count > 0) {
            return back()->with('error', __('messages.dependency.package_used'));
        }

        $model->delete();

        return back()->with('success', __('general.deleted_successfully', ['resource' => __('general.resource.package')]));
    }

    private function validated(Request $request, ?Package $package = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', 'alpha_dash', Rule::unique('packages', 'code')->ignore($package?->id)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'modules' => ['present', 'array'],
            'modules.*' => ['string', Rule::in(Modules::available())],
            'max_users' => ['nullable', 'integer', 'min:1'],
            'max_branches' => ['nullable', 'integer', 'min:1'],
            'price' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'period_months' => ['required', 'integer', 'min:1', 'max:60'],
            'is_active' => ['boolean'],
        ]);

        $data['modules'] = array_values(array_unique($data['modules']));
        $data['currency'] = strtoupper($data['currency']);

        return $data + ($package ? ['updated_by' => $request->user()->id] : ['created_by' => $request->user()->id]);
    }
}
