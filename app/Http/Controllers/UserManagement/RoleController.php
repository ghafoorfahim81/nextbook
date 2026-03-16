<?php

namespace App\Http\Controllers\UserManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserManagement\RoleStoreRequest;
use App\Http\Requests\UserManagement\RoleUpdateRequest;
use App\Http\Resources\UserManagement\RoleResource;
use App\Models\Role;
use App\Models\Permission;
use App\Support\Inertia\CacheKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class RoleController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Role::class, 'role');
    }

    public function index(Request $request)
    {
        $perPage = $request->input('perPage', recordsPerPage());
        $sortField = $request->input('sortField', 'created_at');
        $sortDirection = $request->input('sortDirection', 'desc');

        $roles = Role::with('permissions')
            ->when($request->query('search'), function ($query, $search) {
                return $query->where('name', 'like', "%{$search}%");
            })
            ->orderBy($sortField, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();

        return inertia('UserManagement/Roles/Index', [
            'roles' => RoleResource::collection($roles),
        ]);
    }

    public function create()
    {
        return inertia('UserManagement/Roles/Create', [
            'permissions' => Permission::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(RoleStoreRequest $request)
    {
        $data = $request->validated();

        $role = Role::create([
            'name' => $data['name'],
            'guard_name' => 'web',
        ]);

        if (isset($data['permissions'])) {
            $role->givePermissionTo($data['permissions']);

            // $role->syncPermissions($data['permissions']);
        }

        if ($request->input('create_and_new')) {
            return redirect()->route('roles.create')->with('success', __('general.created_successfully', ['resource' => __('general.resource.role')]));
        }
        Cache::forget(CacheKey::forCompanyBranchLocale($request, 'roles'));
        return redirect()->route('roles.index')->with('success', __('general.created_successfully', ['resource' => __('general.resource.role')]));
    }

    public function show(Request $request, Role $role)
    {
        $role->load('permissions');
        return new RoleResource($role);
    }

    public function edit(Role $role)
    {
        $role->load('permissions');
        return inertia('UserManagement/Roles/Edit', [
            'role' => new RoleResource($role),
            'permissions' => Permission::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(RoleUpdateRequest $request, Role $role)
    {
        $data = $request->validated();

        $role->update([
            'name' => $data['name'],
        ]);

        if (array_key_exists('permissions', $data)) {
            $role->syncPermissions($data['permissions'] ?? []);
        }

        Cache::forget(CacheKey::forCompanyBranchLocale($request, 'roles'));

        return redirect()->route('roles.index')->with('success', __('general.updated_successfully', ['resource' => __('general.resource.role')]));
    }

    public function destroy(Request $request, Role $role)
    {
        // Prevent deleting roles that have users
        if ($role->users()->count() > 0) {
            return redirect()->route('roles.index')->with('error', __('general.cannot_delete_role_has_users'));
        }

        if ($role->slug === 'super-admin' || $role->slug === 'admin' || $role->slug === 'accountant' || $role->slug === 'clerk') {
            return redirect()->route('roles.index')->with('error', __('general.cannot_delete_protected_role'));
        }

        $role->delete();
        Cache::forget(CacheKey::forCompanyBranchLocale($request, 'roles'));
        return redirect()->route('roles.index')->with('success', __('general.deleted_successfully', ['resource' => __('general.resource.role')]));
    }

    public function restore(Request $request, $id)
    {
        $role = Role::withTrashed()->findOrFail($id);
        $role->restore();
        Cache::forget(CacheKey::forCompanyBranchLocale($request, 'roles'));
        return redirect()->route('roles.index')->with('success', __('general.restored_successfully', ['resource' => __('general.resource.role')]));
    }
}
