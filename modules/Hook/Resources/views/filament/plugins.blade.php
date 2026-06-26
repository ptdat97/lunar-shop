<x-filament-panels::page>
    {{-- Management table: every discovered plugin + its state + actions --}}
    <x-filament::section>
        <x-slot name="heading">Installed plugins</x-slot>
        <x-slot name="description">
            Plugins load only when listed in <code>config/plugins.php</code> (allow-list).
            Install runs the plugin's migrations; disable keeps data.
        </x-slot>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500 border-b dark:border-gray-700">
                        <th class="py-2 pr-4">Plugin</th>
                        <th class="py-2 pr-4">Version</th>
                        <th class="py-2 pr-4">Enabled</th>
                        <th class="py-2 pr-4">Installed</th>
                        <th class="py-2 pr-4">Active</th>
                        <th class="py-2 pr-4">Requires</th>
                        <th class="py-2">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->pluginRows() as $p)
                        <tr class="border-b dark:border-gray-800">
                            <td class="py-2 pr-4 font-medium">{{ $p['id'] }}</td>
                            <td class="py-2 pr-4">{{ $p['version'] }}</td>
                            <td class="py-2 pr-4">
                                <x-filament::badge :color="$p['enabled'] ? 'success' : 'gray'">
                                    {{ $p['enabled'] ? 'yes' : 'no' }}
                                </x-filament::badge>
                            </td>
                            <td class="py-2 pr-4">{{ $p['installed'] ? 'yes' : 'no' }}</td>
                            <td class="py-2 pr-4">
                                <x-filament::badge :color="$p['active'] ? 'success' : 'gray'">
                                    {{ $p['active'] ? 'active' : 'inactive' }}
                                </x-filament::badge>
                            </td>
                            <td class="py-2 pr-4">
                                <x-filament::badge :color="$p['satisfied'] ? 'success' : 'danger'">
                                    {{ $p['satisfied'] ? 'ok' : 'unmet' }}
                                </x-filament::badge>
                            </td>
                            <td class="py-2 flex gap-2">
                                <x-filament::button size="xs" wire:click="installPlugin('{{ $p['id'] }}')">
                                    {{ $p['active'] ? 'Reinstall' : 'Install' }}
                                </x-filament::button>
                                @if ($p['active'])
                                    <x-filament::button size="xs" color="gray" wire:click="disablePlugin('{{ $p['id'] }}')">
                                        Disable
                                    </x-filament::button>
                                @endif
                                @if ($p['installed'])
                                    <x-filament::button size="xs" color="danger"
                                        wire:click="uninstallPlugin('{{ $p['id'] }}')"
                                        wire:confirm="Roll back {{ $p['id'] }}'s data?">
                                        Uninstall
                                    </x-filament::button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-3 text-gray-500">No plugins discovered.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>

    {{-- Config tabs contributed by enabled plugins --}}
    @if ($this->hasConfigTabs())
        <x-filament::section>
            <x-slot name="heading">Plugin settings</x-slot>
            <form wire:submit="save" class="space-y-6">
                {{ $this->form }}
                <div>
                    <x-filament::button type="submit">Save</x-filament::button>
                </div>
            </form>
        </x-filament::section>
    @endif
</x-filament-panels::page>
