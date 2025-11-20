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
            'branch_id' => $this->branch_id,
            'company_id' => $this->company_id,
            'company' => $this->whenLoaded('company'),
            'roles' => $this->whenLoaded('roles', function () {
                return $this->roles->map(fn($role) => [
                    'id' => $role->id,
                    'name' => $role->name,
                ]);
            }),
            'permissions' => $this->getAllPermissions()->pluck('id'),
        ];
    }
}
