<?php

namespace App\Livewire\Forms;

use App\Concerns\AccessControlValidationRules;
use App\Models\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Livewire\Form;

class RoleForm extends Form
{
    use AccessControlValidationRules;

    public ?Role $role = null;

    public string $name = '';

    /**
     * Permission ids (strings, as sent by the checkbox group).
     *
     * @var array<int, int|string>
     */
    public array $permissions = [];

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        return [
            'name' => $this->accessNameRules('roles', $this->role?->id),
            'permissions' => ['array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->accessNameMessages('role');
    }

    public function setRole(Role $role): void
    {
        $this->role = $role;
        $this->name = $role->name;
        $this->permissions = $role->permissions->pluck('id')->map(fn ($id) => (string) $id)->all();
    }

    public function store(): Role
    {
        $this->validate();

        /** @var Role $role */
        $role = Role::create(['name' => $this->name]);
        $role->syncPermissions($this->permissionIds());

        return $role;
    }

    /**
     * Rename the role and sync its permissions (assign checked, revoke unchecked).
     */
    public function update(): void
    {
        $this->validate();

        if ($this->role === null) {
            return;
        }

        $this->role->update(['name' => $this->name]);
        $this->role->syncPermissions($this->permissionIds());
    }

    /**
     * @return list<int>
     */
    private function permissionIds(): array
    {
        return array_values(array_map(intval(...), $this->permissions));
    }
}
