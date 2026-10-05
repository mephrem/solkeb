{{--
    Nationality picker: Ethiopia by default, its neighbours first, then all countries.
    @include('partials.nationality', ['name' => 'nationality', 'value' => $guest->nationality])
--}}
@php
    $countries = \App\Support\Countries::class;
    $current = $value ?: $countries::DEFAULT;
    if ($current === 'Ethiopian') { $current = 'Ethiopia'; } // older records
@endphp
<div class="field"><label>Nationality</label>
    <select name="{{ $name }}">
        @unless (in_array($current, $countries::all()))<option value="{{ $current }}" selected>{{ $current }}</option>@endunless
        <optgroup label="Nearby">
            @foreach ($countries::NEAR as $c)<option value="{{ $c }}" @selected($current === $c)>{{ $c }}</option>@endforeach
        </optgroup>
        <optgroup label="All countries">
            @foreach ($countries::OTHERS as $c)<option value="{{ $c }}" @selected($current === $c)>{{ $c }}</option>@endforeach
        </optgroup>
    </select></div>
