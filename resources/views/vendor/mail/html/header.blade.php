@props(['url'])
{{-- Overrides Laravel's stock header, which prints the app name as plain text
     (and hardcodes the Laravel logo). We render the store's brand logo instead,
     falling back to the site name when no logo has been uploaded. --}}
@php($logo = app(\Modules\Theme\Services\ThemeSettings::class)->emailLogo())
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
@if ($logo)
<img src="{{ $logo }}" class="logo" alt="{{ config('app.name') }}">
@else
{!! $slot !!}
@endif
</a>
</td>
</tr>
