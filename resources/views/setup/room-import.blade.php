@extends('layouts.app')
@section('title', 'Import rooms')
@section('content')
@php
    $badge = ['new' => 'b-available', 'update' => 'b-confirmed', 'skip' => '', 'error' => 'b-cancelled'];
    $label = ['new' => 'New', 'update' => 'Update', 'skip' => 'Skip', 'error' => 'Error'];
@endphp
<div class="page-head">
    <div><h1>Import rooms</h1><div class="muted">Add many rooms at once from Excel, or a range of numbers</div></div>
    <div class="actions">
        <a class="btn btn-light" href="{{ route('room-import.template') }}">Download template</a>
        <a class="btn btn-light" href="{{ route('setup.rooms') }}">Back to rooms</a>
    </div>
</div>

@if ($rows !== null)
    {{-- ---------- Step 2: preview ---------- --}}
    @php $importable = ($counts['new'] ?? 0) + ($counts['update'] ?? 0); @endphp
    <div class="card" style="border-left:4px solid var(--brand)">
        <div class="card-head">
            <h2>Check before importing · <span class="muted">{{ $source }}</span></h2>
        </div>
        <div class="actions" style="margin-bottom:.75rem">
            @foreach (['new' => 'will be added', 'update' => 'will be updated', 'skip' => 'already exist (left as they are)', 'error' => 'have errors (not imported)'] as $s => $text)
                @if ($counts[$s] ?? 0)<span class="badge {{ $badge[$s] }}" style="font-size:.85rem;padding:.3rem .7rem">{{ $counts[$s] }} {{ $text }}</span>@endif
            @endforeach
        </div>
        <div class="actions">
            <form method="POST" action="{{ route('room-import.store') }}" class="inline"
                  @if ($importable) data-confirm-title="Import {{ $importable }} room(s)?"
                      data-confirm="{{ ($counts['new'] ?? 0) }} new, {{ ($counts['update'] ?? 0) }} updated.{{ ($counts['error'] ?? 0) ? ' '.$counts['error'].' row(s) with errors will be left out.' : '' }}"
                      data-confirm-button="Import"
                  @else data-block-title="Nothing to import" data-block="Every row is either already in the hotel or has an error. Fix the file and load it again."
                  @endif>
                @csrf
                <button class="btn" type="submit">Import {{ $importable }} room(s)</button>
            </form>
            <a class="btn btn-light" href="{{ route('room-import.create') }}">Start again</a>
        </div>
    </div>

    <div class="card" style="padding:0">
        <div class="table-wrap">
        <table class="datatable" data-page-length="50" data-export="room-import-check">
            <thead><tr><th>Row</th><th>Room</th><th>Floor</th><th>Type</th><th class="right">Own price</th><th>Result</th><th>Details</th></tr></thead>
            <tbody>
            @foreach ($rows as $r)
                <tr>
                    <td class="muted">{{ $r['line'] }}</td>
                    <td><strong>{{ $r['number'] }}</strong></td>
                    <td>{{ $r['floor'] ?? '—' }}</td>
                    <td>{{ $r['type'] ?: '—' }}</td>
                    <td class="right">{{ $r['price'] !== null ? number_format($r['price'], 2) : '—' }}</td>
                    <td data-order="{{ ['error' => 0, 'new' => 1, 'update' => 2, 'skip' => 3][$r['status']] }}"><span class="badge {{ $badge[$r['status']] }}">{{ $label[$r['status']] }}</span></td>
                    <td class="small {{ $r['status'] === 'error' ? 'owe' : 'muted' }}">{{ $r['message'] }}@if ($r['notes']) <span class="muted">· {{ $r['notes'] }}</span>@endif</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        </div>
    </div>
@else
    {{-- ---------- Step 1: choose a source ---------- --}}
    <div class="grid grid-2">
        <form class="card" method="POST" action="{{ route('room-import.preview') }}" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="mode" value="file">
            <h2>From Excel</h2>
            <p class="small muted" style="margin-top:0">Upload a CSV file (in Excel: <em>File › Save As › CSV UTF-8</em>), <strong>or</strong> copy the rows in Excel and paste them below.</p>
            <div class="field"><label for="file">CSV file</label><input type="file" id="file" name="file" accept=".csv,.txt,text/csv"></div>
            <div class="field"><label for="pasted">…or paste rows from Excel</label>
                <textarea id="pasted" name="pasted" rows="7" style="font-family:ui-monospace,Consolas,monospace;font-size:.85rem" placeholder="number	floor	type	price	notes
401	4	Standard
402	4	Standard		Near the lift
403	4	Deluxe	2800	Balcony">{{ old('pasted') }}</textarea></div>
            <div class="field"><label class="check"><input type="checkbox" name="update_existing" value="1" @checked(old('update_existing'))> Update rooms that already exist (otherwise they are skipped)</label></div>
            <button class="btn" type="submit">Check rows</button>
        </form>

        <form class="card" method="POST" action="{{ route('room-import.preview') }}">
            @csrf
            <input type="hidden" name="mode" value="range">
            <h2>Add a range</h2>
            <p class="small muted" style="margin-top:0">For example rooms 401 to 410, all Deluxe, on floor 4.</p>
            <div class="fields">
                <div class="field"><label for="from">From number *</label><input type="number" id="from" name="from" min="0" value="{{ old('from') }}" placeholder="401" required></div>
                <div class="field"><label for="to">To number *</label><input type="number" id="to" name="to" min="0" value="{{ old('to') }}" placeholder="410" required></div>
            </div>
            <div class="fields">
                <div class="field"><label for="type">Room type *</label>
                    <select id="type" name="type" required>@foreach ($types as $t)<option value="{{ $t->name }}" @selected(old('type') === $t->name)>{{ $t->name }} · {{ number_format($t->base_rate) }} ETB</option>@endforeach</select></div>
                <div class="field"><label for="floor">Floor</label><input type="number" id="floor" name="floor" min="0" max="50" value="{{ old('floor') }}" placeholder="auto: 401 → 4"></div>
            </div>
            <div class="fields">
                <div class="field"><label for="price">Own price (ETB)</label><input type="number" id="price" name="price" min="0" step="0.01" value="{{ old('price') }}" placeholder="blank = type's rate"></div>
                <div class="field"><label for="prefix">Prefix</label><input type="text" id="prefix" name="prefix" maxlength="4" value="{{ old('prefix') }}" placeholder="e.g. B for B101"></div>
            </div>
            <div class="field"><label class="check"><input type="checkbox" name="update_existing" value="1"> Update rooms that already exist</label></div>
            <button class="btn" type="submit">Check rows</button>
        </form>
    </div>

    <div class="card small">
        <h3>File format</h3>
        <p style="margin-top:0">Columns in this order, with or without a heading row: <strong>number, floor, type, price, notes</strong>.</p>
        <ul style="margin:0 0 .5rem 1.1rem;padding:0">
            <li><strong>number</strong> is required, for example 101 or B12.</li>
            <li><strong>floor</strong> can be left empty for numbers like 101, 204, 1203: it is taken from the number (1, 2, 12).</li>
            <li><strong>type</strong> must be one of:
                @foreach ($types as $t)<strong>{{ $t->name }}</strong>@if (! $loop->last), @endif @endforeach
                (capital letters don't matter). <a href="{{ route('room-types.create') }}">Add a type</a> first if you need a new one.</li>
            <li><strong>price</strong> only if this room costs something different from its type's rate. Leave empty otherwise.</li>
        </ul>
        <p class="muted" style="margin:0">Nothing is saved until you have checked the rows and pressed Import. Up to {{ \App\Services\RoomImport::MAX_ROWS }} rows at a time.</p>
    </div>
@endif
@endsection
