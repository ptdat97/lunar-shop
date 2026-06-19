{{-- Fashion Size Intelligence — surfaced in the product modal:
       1. Size chart table (SSR, crawlable).
       2. "Find my size" — body measurements → POST /api/v1/products/{slug}/recommend-size.
     Expects $sizeChart (SizeChartService::for shape) + $slug. The measurement
     keys present in the chart drive the "find my size" inputs (no point asking
     for a measurement the chart can't match). enhance/size-finder.js handles
     the fetch + result rendering. --}}
@if(!empty($sizeChart['has_chart']))
    @php
        $labels = [
            'bust' => 'Bust', 'waist' => 'Waist', 'hip' => 'Hip',
            'shoulder' => 'Shoulder', 'length' => 'Length', 'inseam' => 'Inseam',
        ];
    @endphp
    <div class="mt-4">
        <button class="btn btn-link p-0 text-dark" type="button"
                data-bs-toggle="modal" data-bs-target="#sizeChart">
            <i class="bi bi-list"></i> Size chart &amp; find my size
        </button>
    </div>

    <div class="modal fade" id="sizeChart" tabindex="-1" aria-labelledby="sizeChartLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
            <div class="modal-content" data-size-finder data-slug="{{ $slug }}">
                <div class="modal-header">
                    <h5 class="modal-title" id="sizeChartLabel">
                        Size guide{{ $sizeChart['name'] ? ' — '.$sizeChart['name'] : '' }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    {{-- Tabs: chart / find my size --}}
                    <ul class="nav nav-pills mb-3" role="tablist">
                        <li class="nav-item">
                            <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#sc-chart" type="button">
                                Size chart
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" data-bs-toggle="pill" data-bs-target="#sc-finder" type="button">
                                Find my size
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content">
                        {{-- Size chart table --}}
                        <div class="tab-pane fade show active" id="sc-chart">
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

                        {{-- Find my size --}}
                        <div class="tab-pane fade" id="sc-finder">
                            <p class="text-muted small">Enter your body measurements (cm) and we’ll suggest your best fit.</p>
                            <form data-size-finder-form>
                                <div class="row g-2">
                                    @foreach($sizeChart['measurements'] as $m)
                                        @continue(! array_key_exists($m, $labels))
                                        <div class="col-6 col-md-4">
                                            <label class="form-label small text-capitalize" for="sf-{{ $m }}">{{ $labels[$m] }}</label>
                                            <input class="form-control form-control-sm" type="number" step="0.1" min="1" max="300"
                                                   id="sf-{{ $m }}" name="{{ $m }}" inputmode="decimal">
                                        </div>
                                    @endforeach
                                </div>
                                <div class="alert alert-danger mt-3 mb-0" data-size-finder-error hidden></div>
                                <button class="btn btn-dark mt-3" type="submit">Find my size</button>
                            </form>

                            {{-- Result --}}
                            <div class="mt-3" data-size-finder-result hidden>
                                <div class="border rounded p-3">
                                    <div class="small text-muted text-uppercase">Recommended size</div>
                                    <div class="d-flex align-items-baseline gap-2">
                                        <span class="h3 mb-0" data-sf-size>—</span>
                                        <span class="badge" data-sf-confidence></span>
                                        <span class="text-muted small" data-sf-fit></span>
                                    </div>
                                    <div class="small text-muted mt-2" data-sf-alternatives></div>
                                    <button class="btn btn-outline-dark btn-sm mt-2" type="button" data-sf-apply hidden>
                                        Use this size
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif
