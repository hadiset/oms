<flux:modal name="{{ $name }}" class="md:w-96" @close="closeModal">
    <div class="space-y-6">
        <div>
            <flux:heading size="lg">{{ $modalTitle }}</flux:heading>
            <flux:text class="mt-2">{{ $modalDesc }}</flux:text>
        </div>
        <form class="space-y-4" wire:submit="{{ $submit }}">
            {{ $slot }}
            <div class="flex gap-2">
                <flux:spacer />

                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>

                <flux:button type="submit" variant="primary" color="{{ $btnColor }}">{{ $btnText }}</flux:button>
            </div>
        </form>
    </div>
</flux:modal>
