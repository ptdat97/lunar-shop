{{-- Size chart + "find my size". SSR table (crawlable); the recommend-size
     fetch (POST /api/v1/products/{slug}/recommend-size) lands as a small island
     in Bước 4. Expects $sizeChart (SizeChartService::for shape) + $slug. --}}
@if(!empty($sizeChart['has_chart']))
    <div class="mt-4">
        <button class="btn btn-link p-0 text-dark" type="button"
                data-bs-toggle="collapse" data-bs-target="#sizeChart">
            <i class="bi bi-list"></i> Size chart{{ $sizeChart['name'] ? ' — '.$sizeChart['name'] : '' }}
        </button>
        <div class="collapse mt-2" id="sizeChart">
            <div class="table-responsive">
                <table class="table table-sm table-bordered align-middle small mb-0">
                    <thead>
                        <tr>
                            <th>Size</th>
                            <th>Fit</th>
                            @foreach($sizeChart['measurements'] as $m)
                                <th class="text-capitalize">{{ str_replace('_', ' ', $m) }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($sizeChart['rows'] as $row)
                            <tr>
                                <td class="fw-semibold">{{ $row['size'] ?? '' }}</td>
                                <td>{{ $row['fit'] ?? '' }}</td>
                                @foreach($sizeChart['measurements'] as $m)
                                    <td>{{ $row[$m] ?? '—' }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($material = $sizeChart['material'] ?? null)
                <dl class="row small mt-3 mb-0">
                    @foreach($material as $key => $value)
                        @continue(blank($value))
                        <dt class="col-4 text-capitalize text-muted">{{ str_replace('_', ' ', $key) }}</dt>
                        <dd class="col-8">{{ $value }}</dd>
                    @endforeach
                </dl>
            @endif
        </div>
    </div>
@endif
