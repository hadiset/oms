<?php

use App\Exceptions\ProtectedRoleException;
use App\Livewire\Forms\UserRoleForm;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use SweetAlert2\Laravel\Traits\WithSweetAlert;

new class extends Component {
    use WithPagination, WithSweetAlert;

    #[Url(as: 'q')]
    public $search = '';

    public UserRoleForm $form;

    public function mount(): void
    {
        $this->authorize('viewAny', User::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function users()
    {
        return User::with('roles')
            ->search($this->search)
            ->orderBy('id', 'asc')
            ->paginate(10);
    }

    /**
     * Only super-admins may hand out (or take away) the super-admin role.
     */
    #[Computed]
    public function assignableRoles()
    {
        return Role::orderBy('name')
            ->when(! auth()->user()?->hasRole(Role::SUPER_ADMIN), fn (Builder $query) => $query->where('name', '!=', Role::SUPER_ADMIN))
            ->get(['id', 'name']);
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

    public function editRoles(int $id): void
    {
        $user = User::findOrFail($id);
        $this->authorize('assignRole', $user);

        $this->form->setUser($user);
    }

    public function saveRoles(): void
    {
        $user = $this->form->user ?? throw (new ModelNotFoundException)->setModel(User::class);
        $this->authorize('assignRole', $user);

        try {
            $this->form->save();
        } catch (ProtectedRoleException $e) {
            $this->addError('form.roles', $e->getMessage());

            return;
        }

        $this->closeModal();
        $this->successModal('Roles updated successfully.');
    }
};
?>

<div>
    <div class="flex flex-row">
        <div class="flex basis-2/3 justify-start">
            <flux:input icon="magnifying-glass" type="text" placeholder="Search users" wire:model.live="search" />
        </div>
    </div>

    <flux:table :paginate="$this->users">
        <flux:table.columns>
            <flux:table.column>Name</flux:table.column>
            <flux:table.column>Email</flux:table.column>
            <flux:table.column>Roles</flux:table.column>
            <flux:table.column>Action</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($this->users as $user)
                <flux:table.row :key="$user->id">
                    <flux:table.cell>{{ $user->name }}</flux:table.cell>
                    <flux:table.cell>{{ $user->email }}</flux:table.cell>
                    <flux:table.cell>
                        <div class="flex flex-wrap gap-1">
                            @forelse ($user->roles as $role)
                                <flux:badge size="sm" :color="$role->name === App\Models\Role::SUPER_ADMIN ? 'amber' : 'zinc'">
                                    {{ $role->name }}
                                </flux:badge>
                            @empty
                                <flux:text size="sm">—</flux:text>
                            @endforelse
                        </div>
                    </flux:table.cell>

                    <flux:table.cell class="py-0">
                        @can('assignRole', $user)
                            <flux:modal.trigger name="assign-roles">
                                <flux:button wire:click="editRoles({{ $user->id }})" size="sm" icon="user-circle"
                                    variant="primary" color="emerald">Roles</flux:button>
                            </flux:modal.trigger>
                        @endcan
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    <x-modal name="assign-roles" modalTitle="Assign Roles" modalDesc="Checked roles are assigned, unchecked are revoked"
        btnColor="emerald" btnText="Save Roles" submit="saveRoles">
        <flux:checkbox.group wire:model="form.roles" label="Roles" class="max-h-64 overflow-y-auto">
            @foreach ($this->assignableRoles as $role)
                <flux:checkbox :value="(string) $role->id" :label="$role->name" />
            @endforeach
        </flux:checkbox.group>
    </x-modal>
</div>
