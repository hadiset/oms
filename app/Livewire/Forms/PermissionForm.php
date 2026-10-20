<?php

namespace App\Livewire\Forms;

use App\Concerns\AccessControlValidationRules;
use App\Models\Permission;
use Illuminate\Contracts\Validation\ValidationRule;
use Livewire\Form;

class PermissionForm extends Form
{
    use AccessControlValidationRules;

    public ?Permission $permission = null;

    public string $name = '';

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        return [
            'name' => $this->accessNameRules('permissions', $this->permission?->id),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->accessNameMessages('permission');
    }

    public function setPermission(Permission $permission): void
    {
        $this->permission = $permission;
        $this->name = $permission->name;
    }

    public function store(): Permission
    {
        $this->validate();

        /** @var Permission */
        return Permission::create(['name' => $this->name]);
    }

    public function update(): void
    {
        $this->validate();

        $this->permission?->update(['name' => $this->name]);
    }
}
