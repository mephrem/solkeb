@if (session('success'))
    <div class="alert alert-success" data-flash="success">{{ session('success') }}</div>
@endif
@if (session('error'))
    <div class="alert alert-error" data-flash="error">{{ session('error') }}</div>
@endif
@if ($errors->any())
    <div class="alert alert-error" data-flash-errors="{{ $errors->count() }}">
        Please fix the following:
        <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif
