<?php

use App\Exceptions\ProtectedRoleException;
use App\Livewire\Forms\RoleForm;
use App\Models\Role;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Permission;
use SweetAlert2\Laravel\Traits\WithSweetAlert;

new class extends Component {
    use WithPagination, WithSweetAlert;

    #[Url(as: 'q')]
    public $search = '';

    public RoleForm $form;

    public function mount(): void
    {
        $this->authorize('viewAny', Role::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function roles()
    {
        return Role::withCount('permissions')
            ->search($this->search)
            ->orderBy('id', 'asc')
            ->paginate(5);
    }

    #[Computed]
    public function allPermissions()
    {
        return Permission::orderBy('name')->get(['id', 'name']);
    }

    public function resetProp(): void
    {
        $this->form->reset();
        $this->resetValidation();
    }

    public function closeModal(): void
    {
        $this->resetProp();
        Flux::modals()->close();
    }

    public function successModal(string $msg): void
    {
        $this->swalSuccess([
            'title' => 'Success!',
            'text' => $msg,
            'timer' => 3000,
            'timerProgressBar' => true,
        ]);
    }

    public function errorModal(string $msg): void
    {
        $this->swalError([
            'title' => 'Not allowed',
            'text' => $msg,
        ]);
    }

    public function save(): void
    {
        $this->authorize('create', Role::class);

        $this->form->store();

        $this->closeModal();
        $this->successModal('Role added successfully.');
    }

    public function edit(int $id): void
    {
        $role = Role::findOrFail($id);
        $this->authorize('update', $role);

        $this->form->setRole($role);
    }

    public function update(): void
    {
        $this->authorize('update', $this->loadedRole());

        try {
            $this->form->update();
        } catch (ProtectedRoleException $e) {
            $this->closeModal();
            $this->errorModal($e->getMessage());

            return;
        }

        $this->closeModal();
        $this->successModal('Role updated successfully.');
    }

    public function deleteConfirm(int $id): void
    {
        $role = Role::findOrFail($id);
        $this->authorize('delete', $role);

        $this->form->setRole($role);
    }

    public function delete(): void
    {
        $role = $this->loadedRole();
        $this->authorize('delete', $role);

        try {
            $role->delete();
        } catch (ProtectedRoleException $e) {
            $this->closeModal();
            $this->errorModal($e->getMessage());

            return;
        }

        $this->closeModal();
        $this->successModal('Role deleted successfully.');
    }

    private function loadedRole(): Role
    {
        return $this->form->role ?? throw (new ModelNotFoundException)->setModel(Role::class);
    }
};
?>

<div>
    <div class="flex flex-row">
        <div class="flex basis-2/3 justify-start">
            <flux:input icon="magnifying-glass" type="text" placeholder="Search roles" wire:model.live="search" />
        </div>

        <div class="flex basis-1/3 justify-end">
            @can('create', App\Models\Role::class)
                <flux:modal.trigger name="create-role">
                    <flux:button icon="plus-circle" variant="primary" color="blue">Add Role</flux:button>
                </flux:modal.trigger>
            @endcan
        </div>
    </div>

    <flux:table :paginate="$this->roles">
        <flux:table.columns>
            <flux:table.column>Role</flux:table.column>
            <flux:table.column>Permissions</flux:table.column>
            <flux:table.column>Action</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($this->roles as $role)
                <flux:table.row :key="$role->id">
                    <flux:table.cell class="flex items-center gap-3">
                        {{ $role->name }}
                    </flux:table.cell>

                    <flux:table.cell>
                        @if ($role->isSuperAdmin())
                            <flux:badge size="sm" color="amber">All (bypass)</flux:badge>
                        @else
                            <flux:badge size="sm">{{ $role->permissions_count }}</flux:badge>
                        @endif
                    </flux:table.cell>

                    <flux:table.cell class="py-0">
                        @unless ($role->isSuperAdmin())
                            @can('update', $role)
                                <flux:modal.trigger name="edit-role">
                                    <flux:button wire:click="edit({{ $role->id }})" class="w-30" size="sm"
                                        icon="pencil-square" variant="primary" color="emerald">Edit</flux:button>
                                </flux:modal.trigger>
                            @endcan
                            @can('delete', $role)
                                <flux:modal.trigger name="delete-role">
                                    <flux:button wire:click="deleteConfirm({{ $role->id }})" class="w-30"
                                        size="sm" icon="trash" variant="primary" color="red">Delete</flux:button>
                                </flux:modal.trigger>
                            @endcan
                        @endunless
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    <x-modal name="create-role" modalTitle="Create Role" modalDesc="Name the role and pick its permissions"
        btnColor="blue" btnText="Save Role" submit="save">
        <flux:input wire:model="form.name" label="Name" placeholder="e.g. editor" />
        <flux:checkbox.group wire:model="form.permissions" label="Permissions" class="max-h-64 overflow-y-auto">
            @foreach ($this->allPermissions as $permission)
                <flux:checkbox :value="(string) $permission->id" :label="$permission->name" />
            @endforeach
        </flux:checkbox.group>
    </x-modal>

    <x-modal name="edit-role" modalTitle="Edit Role" modalDesc="Rename the role or change its permissions"
        btnColor="emerald" btnText="Update Role" submit="update">
        <flux:input wire:model="form.name" label="Name" placeholder="e.g. editor" />
        <flux:checkbox.group wire:model="form.permissions" label="Permissions" class="max-h-64 overflow-y-auto">
            @foreach ($this->allPermissions as $permission)
                <flux:checkbox :value="(string) $permission->id" :label="$permission->name" />
            @endforeach
        </flux:checkbox.group>
    </x-modal>

    <x-modal name="delete-role" modalTitle="Delete Role" modalDesc="Delete a role" btnColor="red"
        btnText="Delete Role" submit="delete">
        <flux:text class="mt-2">This action will delete <span
                class="inline-flex items-center rounded-md bg-gray-50 px-2 py-1 text-xs font-medium text-gray-600 inset-ring inset-ring-gray-500/10"
                x-text="$wire.form.name"></span> and remove it from every user. Are you sure?</flux:text>
    </x-modal>
</div>
