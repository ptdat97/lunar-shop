<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}

        <div class="flex flex-wrap items-center gap-3">
            <x-filament::button type="submit">
                {{ __('admin.notification_settings.save') }}
            </x-filament::button>

            {{-- Test actions exercise the *saved* configuration, so they are
                 plain buttons rather than form submits. --}}
            <x-filament::button
                type="button"
                color="gray"
                outlined
                wire:click="sendTestMail"
                wire:loading.attr="disabled"
                wire:target="sendTestMail"
            >
                <span wire:loading.remove wire:target="sendTestMail">
                    {{ __('admin.notification_settings.test_mail') }}
                </span>
                <span wire:loading wire:target="sendTestMail">
                    {{ __('admin.notification_settings.testing') }}
                </span>
            </x-filament::button>

            @if($this->data['sms_enabled'] ?? false)
                <x-filament::button
                    type="button"
                    color="gray"
                    outlined
                    wire:click="sendTestSms"
                    wire:loading.attr="disabled"
                    wire:target="sendTestSms"
                >
                    <span wire:loading.remove wire:target="sendTestSms">
                        {{ __('admin.notification_settings.test_sms') }}
                    </span>
                    <span wire:loading wire:target="sendTestSms">
                        {{ __('admin.notification_settings.testing') }}
                    </span>
                </x-filament::button>
            @endif
        </div>
    </form>
</x-filament-panels::page>
