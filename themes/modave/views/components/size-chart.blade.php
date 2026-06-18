@props(['sizeChart' => [], 'slug' => ''])

@php
    $rows = $sizeChart['rows'] ?? [];
    $measurements = $sizeChart['measurements'] ?? [];
    $material = $sizeChart['material'] ?? null;
    $hasChart = $sizeChart['has_chart'] ?? false;
    $modalId = 'size-intelligence-modal';
@endphp

@if ($hasChart || $material)
    {{-- Trigger --}}
    <button
        type="button"
        class="size-intelligence-trigger"
        data-bs-toggle="modal"
        data-bs-target="#{{ $modalId }}"
        style="display:inline-flex;align-items:center;gap:6px;background:none;border:1px solid #ddd;border-radius:8px;padding:8px 14px;font-size:14px;cursor:pointer;"
    >
        <i class="icon icon-ruler"></i> Size &amp; Fit
    </button>

    {{-- Popup --}}
    <div class="modal fade modal-size-guide size-intelligence" id="{{ $modalId }}" tabindex="-1" aria-hidden="true" data-slug="{{ $slug }}">
        <div class="modal-dialog modal-dialog-centered" style="max-width:680px;">
            <div class="modal-content" style="padding:24px;">
                <div class="d-flex" style="justify-content:space-between;align-items:center;margin-bottom:16px;">
                    <h5 style="font-size:18px;font-weight:600;margin:0;">Size &amp; Fit</h5>
                    <span class="icon-close icon-close-popup" data-bs-dismiss="modal" style="cursor:pointer;"></span>
                </div>

                @if (! empty($rows))
                    {{-- Size chart --}}
                    <div style="overflow-x:auto;">
                        <table class="size-chart-table" style="width:100%;border-collapse:collapse;font-size:14px;">
                            <thead>
                                <tr style="text-align:left;border-bottom:2px solid #eee;">
                                    <th style="padding:8px;">Size</th>
                                    <th style="padding:8px;">Fit</th>
                                    @foreach ($measurements as $m)
                                        <th style="padding:8px;text-transform:capitalize;">{{ $m }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($rows as $row)
                                    <tr data-size="{{ $row['size'] }}" style="border-bottom:1px solid #f3f3f3;">
                                        <td style="padding:8px;font-weight:600;">{{ $row['size'] ?: '—' }}</td>
                                        <td style="padding:8px;text-transform:capitalize;">{{ $row['fit'] ?: '—' }}</td>
                                        @foreach ($measurements as $m)
                                            <td style="padding:8px;">{{ $row[$m] ?: '—' }}</td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Find My Size --}}
                    <div class="find-my-size" style="margin-top:20px;padding:16px;background:#fafafa;border-radius:8px;">
                        <p style="font-size:14px;font-weight:600;margin-bottom:4px;">Find my size</p>
                        <p style="font-size:13px;color:#666;margin-bottom:10px;">Enter your body measurements (cm) — we’ll suggest your best fit.</p>
                        <div style="display:flex;flex-wrap:wrap;gap:10px;">
                            @foreach (['bust' => 'Bust/Chest', 'waist' => 'Waist', 'hip' => 'Hip'] as $key => $label)
                                <label style="font-size:13px;">
                                    {{ $label }}
                                    <input type="number" name="{{ $key }}" min="1" max="300" step="0.1"
                                        style="display:block;width:110px;padding:6px;border:1px solid #ddd;border-radius:6px;margin-top:4px;">
                                </label>
                            @endforeach
                        </div>
                        <button type="button" class="fms-submit" style="margin-top:12px;background:#111;color:#fff;border:0;border-radius:8px;padding:8px 16px;cursor:pointer;">
                            Recommend
                        </button>
                        <div class="fms-result" style="margin-top:12px;font-size:14px;"></div>
                    </div>
                @endif

                @if ($material)
                    <div class="material-care" style="margin-top:18px;font-size:14px;color:#444;">
                        <h6 style="font-weight:600;margin-bottom:6px;">Material &amp; Care</h6>
                        <ul style="list-style:none;padding:0;margin:0;display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:4px;">
                            @foreach (['material' => 'Material', 'composition' => 'Composition', 'fabric_weight' => 'Weight', 'stretch' => 'Stretch', 'transparency' => 'Transparency', 'lining' => 'Lining'] as $key => $label)
                                @if (! empty($material[$key]))
                                    <li><strong>{{ $label }}:</strong> {{ ucfirst($material[$key]) }}</li>
                                @endif
                            @endforeach
                        </ul>
                        @if (! empty($material['care_instruction']))
                            <p style="margin-top:6px;"><strong>Care:</strong> {{ $material['care_instruction'] }}</p>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>

    <script>
        (function () {
            const modal = document.getElementById(@json($modalId));
            if (!modal) return;

            const slug = modal.dataset.slug;
            const submit = modal.querySelector('.fms-submit');
            if (!submit) return;
            const result = modal.querySelector('.fms-result');

            submit.addEventListener('click', async function () {
                const payload = {};
                modal.querySelectorAll('.find-my-size input[type=number]').forEach(function (i) {
                    if (i.value) payload[i.name] = parseFloat(i.value);
                });

                if (Object.keys(payload).length === 0) {
                    result.innerHTML = '<span style="color:#b91c1c;">Please enter at least one measurement.</span>';
                    return;
                }

                result.textContent = 'Calculating…';

                try {
                    const client = window.axios || null;
                    const res = client
                        ? (await client.post('/api/v1/products/' + slug + '/recommend-size', payload)).data
                        : await (await fetch('/api/v1/products/' + slug + '/recommend-size', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                            body: JSON.stringify(payload),
                        })).json();

                    const rec = res.data && res.data.recommended;
                    if (rec) {
                        result.innerHTML = 'Recommended size: <strong>' + rec.size +
                            '</strong> <span style="color:#666;">(' + rec.confidence + ' confidence)</span>';
                        modal.querySelectorAll('.size-chart-table tbody tr').forEach(function (tr) {
                            tr.style.background = tr.dataset.size === String(rec.size) ? '#fff3cd' : '';
                        });
                    } else {
                        result.innerHTML = '<span style="color:#666;">No size data to match. Try our chart above.</span>';
                    }
                } catch (e) {
                    result.innerHTML = '<span style="color:#b91c1c;">Something went wrong. Please try again.</span>';
                }
            });
        })();
    </script>
@endif
