<x-filament-panels::page>
    {{-- Existing workflows --}}
    <x-filament::section>
        <x-slot name="heading">Workflows</x-slot>
        <x-slot name="description">Trigger → conditions → action. Runs automatically (queued) when the trigger fires.</x-slot>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500 border-b dark:border-gray-700">
                        <th class="py-2 pr-4">Name</th>
                        <th class="py-2 pr-4">Trigger</th>
                        <th class="py-2 pr-4">Action</th>
                        <th class="py-2 pr-4">Enabled</th>
                        <th class="py-2">&nbsp;</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->workflowRows() as $w)
                        <tr class="border-b dark:border-gray-800">
                            <td class="py-2 pr-4 font-medium">{{ $w['name'] }}</td>
                            <td class="py-2 pr-4"><code>{{ $w['trigger'] }}</code></td>
                            <td class="py-2 pr-4"><code>{{ $w['action'] }}</code></td>
                            <td class="py-2 pr-4">
                                <x-filament::badge :color="$w['enabled'] ? 'success' : 'gray'">
                                    {{ $w['enabled'] ? 'on' : 'off' }}
                                </x-filament::badge>
                            </td>
                            <td class="py-2 flex gap-2">
                                <x-filament::button size="xs" color="gray" wire:click="edit({{ $w['id'] }})">Edit</x-filament::button>
                                <x-filament::button size="xs" color="danger"
                                    wire:click="deleteWorkflow({{ $w['id'] }})"
                                    wire:confirm="Delete this workflow?">Delete</x-filament::button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-3 text-gray-500">No workflows yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>

    {{-- Create / edit form --}}
    <x-filament::section>
        <x-slot name="heading">{{ $editingId ? 'Edit workflow' : 'New workflow' }}</x-slot>
        <form wire:submit="save" class="space-y-6">
            {{ $this->form }}
            <div class="flex gap-2">
                <x-filament::button type="submit">{{ $editingId ? 'Update' : 'Create' }}</x-filament::button>
                @if ($editingId)
                    <x-filament::button type="button" color="gray" wire:click="$set('editingId', null)">Cancel</x-filament::button>
                @endif
            </div>
        </form>
    </x-filament::section>
</x-filament-panels::page>
