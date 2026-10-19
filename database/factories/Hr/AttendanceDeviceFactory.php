<?php

namespace Database\Factories\Hr;

use App\Enums\AttendanceDeviceType;
use App\Models\Administration\Branch;
use App\Models\Hr\AttendanceDevice;
use Illuminate\Database\Eloquent\Factories\Factory;

class AttendanceDeviceFactory extends Factory
{
    protected $model = AttendanceDevice::class;

    public function definition(): array
    {
        return [
            'name' => 'Main Entrance',
            'code' => strtoupper(fake()->unique()->bothify('DEV##')),
            'device_type' => AttendanceDeviceType::ZkTeco->value,
            'is_active' => true,
            'branch_id' => Branch::factory(),
            // Read off the branch rather than built separately: a document's
            // company and its branch's company have to agree, and the composite
            // foreign key enforces that.
            'company_id' => fn (array $attributes) => Branch::withoutGlobalScopes()
                ->whereKey($attributes['branch_id'])
                ->value('company_id'),
        ];
    }
}
