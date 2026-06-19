@if(session('status') || session('success') || session('error'))
    @php
        $type = session('error') ? 'danger' : 'success';
        $message = session('error') ?? session('success') ?? session('status');
    @endphp
    <div class="container mt-3">
        <div class="alert alert-{{ $type }} alert-dismissible fade show" role="alert">
            {{ $message }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    </div>
@endif
