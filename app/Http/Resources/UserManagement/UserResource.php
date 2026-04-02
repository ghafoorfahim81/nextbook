<?php

namespace App\Http\Resources\UserManagement;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'status' => $this->status?->value ?? $this->status,
            'branch_id' => $this->branch_id,
            'company_id' => $this->company_id,
            'branch' => $this->whenLoaded('branch'),
            'company' => $this->whenLoaded('company'),
            'roles' => $this->whenLoaded('roles', function () {
                return $this->roles->map(fn($role) => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'slug' => $role->slug,
                ]);
            }),
            'permissions' => $this->whenLoaded('permissions', fn() => $this->permissions->pluck('id')->values()),
            'direct_permission_details' => $this->whenLoaded('permissions', function () {
                return $this->permissions->map(fn($permission) => [
                    'id' => $permission->id,
                    'name' => $permission->name,
                ])->values();
            }),
            'effective_permissions' => $this->when(
                $request->routeIs('users.show'),
                fn() => $this->getAllPermissions()->map(fn($permission) => [
                    'id' => $permission->id,
                    'name' => $permission->name,
                ])->values()
            ),
        ];
    }
}
