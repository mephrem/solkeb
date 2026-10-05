@extends('layouts.app')
@section('title', 'Services')
@section('content')
@php $cats = \App\Http\Controllers\ServiceController::CATEGORIES; @endphp
<div class="page-head">
    <div><h1>Services &amp; prices</h1><div class="muted">Things staff can add to a guest's bill</div></div>
    <a class="btn" href="{{ route('services.create') }}">+ Service</a>
</div>
<div class="card" style="padding:0">
    <div class="table-wrap">
    <table class="datatable" data-export="services">
        <thead><tr><th>Service</th><th>Category</th><th class="right">Price (ETB)</th><th class="right">Times sold</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @forelse ($services as $s)
            <tr>
                <td><strong>{{ $s->name }}</strong></td>
                <td>{{ $cats[$s->category] ?? $s->category }}</td>
                <td class="right">{{ number_format($s->price, 2) }}</td>
                <td class="right">{{ $s->charges_count }}</td>
                <td>@if ($s->is_active)<span class="badge b-available">Active</span>@else<span class="badge">Hidden</span>@endif</td>
                <td class="right nowrap">
                    <a class="btn btn-sm btn-light" href="{{ route('services.edit', $s) }}">Edit</a>
                    @include('partials.delete', ['action' => route('services.destroy', $s), 'name' => $s->name,
                        'text' => 'It will no longer appear in the price list.',
                        'block' => $s->charges_count ? "{$s->name} is on {$s->charges_count} past bill(s), which must stay correct. Edit it and untick Active to stop selling it." : null])
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="empty">No services yet.</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>
</div>
@endsection
