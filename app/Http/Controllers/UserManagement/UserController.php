<?php

namespace App\Http\Controllers\UserManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserManagement\UserStoreRequest;
use App\Http\Requests\UserManagement\UserUpdateRequest;
use App\Http\Resources\UserManagement\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->input('perPage', 10);
        $sortField = $request->input('sortField', 'created_at');
        $sortDirection = $request->input('sortDirection', 'desc');

        $users = User::with(['company', 'roles'])
            ->when($request->query('search'), function ($query, $search) {
                return $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy($sortField, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();
            
        return inertia('UserManagement/Users/Index', [
            'users' => UserResource::collection($users),
        ]);
    }

    public function create()
    {
        $permissions = Permission::orderBy('name')->get(['id', 'name']);

        return inertia('UserManagement/Users/Create', [
            'permissions' => $permissions,
        ]);
    }

    public function edit(User $user)
    {     
        $user = $user->load(['roles', 'permissions']); 
        return inertia('UserManagement/Users/Edit', [
            'user' => new UserResource($user),
            'permissions' => Permission::select('id', 'name')->get(),
        ]);
    }

    public function store(UserStoreRequest $request)
    {
        $data = $request->validated();
        $data['password'] = Hash::make($data['password']);

        $user = User::create($data);

        if (isset($data['roles'])) {
            $user->syncRoles($data['roles']);
        }

        if (isset($data['permissions'])) {
            $user->syncPermissions($data['permissions']);
        }

        if ($request->input('create_and_new')) {
            return redirect()->route('users.create')->with('success', 'User created successfully.');
        }

        return redirect()->route('users.index')->with('success', 'User created successfully.');
    }

    public function show(Request $request, User $user)
    {
        $user->load(['company', 'roles']);
        return new UserResource($user);
    }

    public function update(UserUpdateRequest $request, User $user)
    { 
        $data = $request->validated(); 
        if (isset($data['password']) && !empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }
        
        $user->update($data);
        
        if (array_key_exists('roles', $data)) {
            $user->syncRoles($data['roles'] ?? []);
        }
        
        if (array_key_exists('permissions', $data)) {
            $user->syncPermissions($data['permissions'] ?? []);
        }

        return redirect()->route('users.index')->with('success', 'User updated successfully.');
    }

    public function destroy(Request $request, User $user)
    {
        // Prevent deleting own account
        if ($user->id === $request->user()->id) {
            return redirect()->route('users.index')->with('error', 'You cannot delete your own account.');
        }
        
        $user->delete();
        return redirect()->route('users.index')->with('success', 'User deleted successfully.');
    }

    public function restore(Request $request, $id)
    {
        $user = User::withTrashed()->findOrFail($id);
        $user->restore();
        return redirect()->route('users.index')->with('success', 'User restored successfully.');
    }
}

