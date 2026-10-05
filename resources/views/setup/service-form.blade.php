@extends('layouts.app')
@section('title', $service->exists ? 'Edit service' : 'Add service')
@section('content')
<div class="page-head"><h1>{{ $service->exists ? 'Edit '.$service->name : 'Add service' }}</h1></div>
<form class="card" method="POST" action="{{ $service->exists ? route('services.update', $service) : route('services.store') }}" style="max-width:720px">
    @csrf @if ($service->exists) @method('PUT') @endif
    <div class="fields">
        <div class="field"><label>Name *</label><input type="text" name="name" value="{{ old('name', $service->name) }}" required></div>
        <div class="field"><label>Category *</label>
            <select name="category">@foreach (\App\Http\Controllers\ServiceController::CATEGORIES as $v => $l)<option value="{{ $v }}" @selected(old('category', $service->category) === $v)>{{ $l }}</option>@endforeach</select></div>
        <div class="field"><label>Price (ETB) *</label><input type="number" step="0.01" min="0" name="price" value="{{ old('price', $service->price) }}" required></div>
    </div>
    <div class="field"><label class="check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $service->is_active))> Active (shown when adding to a bill)</label></div>
    <p class="small muted">To stop selling something, untick Active. Past bills keep showing it.</p>
    <div class="actions">
        <button class="btn" type="submit">Save</button>
        <a class="btn btn-light" href="{{ route('services.index') }}">Cancel</a>
    </div>
</form>
@endsection
