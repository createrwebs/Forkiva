<?php

namespace Modules\User\Transformers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\User\Models\User;
use Nwidart\Modules\Facades\Module;

/** @mixin User */
class MeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $hrModuleEnabled = Module::find('Hr') || Module::find('hr');

        if ($hrModuleEnabled) {
            $this->resource->loadMissing([
                'employee.branch:id,name',
                'employee.department:id,name',
                'employee.designation:id,name',
                'employee.manager:id,name,code',
            ]);

            $this->employee?->loadExists('subordinates');
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'short_name' => $this->short_name,
            'profile_photo_url' => $this->profile_photo_url,
            'username' => $this->username,
            'email' => $this->email,
            'gender' => $this->gender->toTrans(),
            'branch' => is_null($this->branch_id)
                ? null
                : [
                    'id' => $this->branch_id,
                    'name' => $this->relationLoaded('branch') ? $this->branch?->name : '',
                ],
            'role' => $this->getRolesWithPermissions()[0],
            'employee' => $hrModuleEnabled && $this->employee ? [
                'id' => $this->employee->id,
                'name' => $this->employee->name,
                'short_name' => $this->employee->short_name,
                'code' => $this->employee->code,
                'profile_photo_url' => $this->employee->profile_photo_url,
                'branch' => $this->employee->branch_id ? [
                    'id' => $this->employee->branch_id,
                    'name' => $this->employee->branch?->name ?? '',
                ] : null,
                'department' => $this->employee->department_id ? [
                    'id' => $this->employee->department_id,
                    'name' => $this->employee->department?->name ?? '',
                ] : null,
                'designation' => $this->employee->designation_id ? [
                    'id' => $this->employee->designation_id,
                    'name' => $this->employee->designation?->name ?? '',
                ] : null,
                'manager' => $this->employee->manager_id ? [
                    'id' => $this->employee->manager_id,
                    'name' => $this->employee->manager?->name ?? '',
                    'code' => $this->employee->manager?->code ?? '',
                ] : null,
                'manager_id' => $this->employee->manager_id,
                'is_manager' => (bool) ($this->employee->getAttribute('subordinates_exists') ?? false),
                'is_active' => (bool) $this->employee->is_active,
                'termination_date' => $this->employee->termination_date?->format('Y-m-d'),
            ] : null,
        ];
    }
}
