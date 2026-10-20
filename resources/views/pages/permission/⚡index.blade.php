<?php

use App\Exceptions\ProtectedPermissionException;
use App\Livewire\Forms\PermissionForm;
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

    public PermissionForm $form;

    public function mount(): void
    {
        $this->authorize('viewAny', Permission::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function permissions()
    {
        return Permission::search($this->search)
            ->orderBy('id', 'asc')
            ->paginate(5);
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
        $this->authorize('create', Permission::class);

        $this->form->store();

        $this->closeModal();
        $this->successModal('Permission added successfully.');
    }

    public function edit(int $id): void
    {
        $permission = Permission::findOrFail($id);
        $this->authorize('update', $permission);

        $this->form->setPermission($permission);
    }

    public function update(): void
    {
        $this->authorize('update', $this->loadedPermission());

        try {
            $this->form->update();
        } catch (ProtectedPermissionException $e) {
            $this->closeModal();
            $this->errorModal($e->getMessage());

            return;
        }

        $this->closeModal();
        $this->successModal('Permission updated successfully.');
    }

    public function deleteConfirm(int $id): void
    {
        $permission = Permission::findOrFail($id);
        $this->authorize('delete', $permission);

        $this->form->setPermission($permission);
    }

    public function delete(): void
    {
        $permission = $this->loadedPermission();
        $this->authorize('delete', $permission);

        try {
            $permission->delete();
        } catch (ProtectedPermissionException $e) {
            $this->closeModal();
            $this->errorModal($e->getMessage());

            return;
        }

        $this->closeModal();
        $this->successModal('Permission deleted successfully.');
    }

    private function loadedPermission(): Permission
    {
        return $this->form->permission ?? throw (new ModelNotFoundException)->setModel(Permission::class);
    }
};
?>

<div>
    <div class="flex flex-row">
        <div class="flex basis-2/3 justify-start">
            <flux:input icon="magnifying-glass" type="text" placeholder="Search permissions" wire:model.live="search" />
        </div>

        <div class="flex basis-1/3 justify-end">
            @can('create', App\Models\Permission::class)
                <flux:modal.trigger name="create-permission">
                    <flux:button icon="plus-circle" variant="primary" color="blue">Add Permission</flux:button>
                </flux:modal.trigger>
            @endcan
        </div>
    </div>

    <flux:table :paginate="$this->permissions">
        <flux:table.columns>
            <flux:table.column>Permission</flux:table.column>
            <flux:table.column>Action</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($this->permissions as $permission)
                <flux:table.row :key="$permission->id">
                    <flux:table.cell class="flex items-center gap-3">
                        {{ $permission->name }}
                        @if ($permission->isSystem())
                            <flux:badge size="sm" color="zinc">system</flux:badge>
                        @endif
                    </flux:table.cell>

                    <flux:table.cell class="py-0">
                        @unless ($permission->isSystem())
                            @can('update', $permission)
                                <flux:modal.trigger name="edit-permission">
                                    <flux:button wire:click="edit({{ $permission->id }})" class="w-30" size="sm"
                                        icon="pencil-square" variant="primary" color="emerald">Edit</flux:button>
                                </flux:modal.trigger>
                            @endcan
                            @can('delete', $permission)
                                <flux:modal.trigger name="delete-permission">
                                    <flux:button wire:click="deleteConfirm({{ $permission->id }})" class="w-30"
                                        size="sm" icon="trash" variant="primary" color="red">Delete</flux:button>
                                </flux:modal.trigger>
                            @endcan
                        @endunless
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    <x-modal name="create-permission" modalTitle="Create Permission" modalDesc="Create a permission name"
        btnColor="blue" btnText="Save Permission" submit="save">
        <flux:input wire:model="form.name" label="Name" placeholder="e.g. post.edit" />
    </x-modal>

    <x-modal name="edit-permission" modalTitle="Edit Permission" modalDesc="Edit a permission name" btnColor="emerald"
        btnText="Update Permission" submit="update">
        <flux:input wire:model="form.name" label="Name" placeholder="e.g. post.edit" />
    </x-modal>

    <x-modal name="delete-permission" modalTitle="Delete Permission" modalDesc="Delete a permission" btnColor="red"
        btnText="Delete Permission" submit="delete">
        <flux:text class="mt-2">This action will delete <span
                class="inline-flex items-center rounded-md bg-gray-50 px-2 py-1 text-xs font-medium text-gray-600 inset-ring inset-ring-gray-500/10"
                x-text="$wire.form.name"></span>. Are you sure?</flux:text>
    </x-modal>
</div>
