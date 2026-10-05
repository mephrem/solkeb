{{--
    "Paid by": the guest themself, or an organisation (the booking's own organisation first).
    @include('partials.paid-by', ['name' => 'payer_name', 'org' => $r->organization, 'default' => ''])
    The guest sends an empty value; an organisation sends its name.
--}}
@php
    $first = $org ?? null;
    $orgs = \App\Models\Organization::where('is_active', true)->when($first, fn ($q) => $q->whereKeyNot($first->id))->orderBy('name')->pluck('name');
    $value = (string) old($name, $default ?? '');
@endphp
<div class="field"><label>Paid by</label>
    <select name="{{ $name }}">
        <option value="" @selected($value === '')>Guest (self)</option>
        @if ($first)<option value="{{ $first->name }}" @selected($value === $first->name)>{{ $first->name }}</option>@endif
        @if ($orgs->isNotEmpty())
            <optgroup label="{{ $first ? 'Other organisations' : 'Organisations' }}">
                @foreach ($orgs as $o)<option value="{{ $o }}" @selected($value === $o)>{{ $o }}</option>@endforeach
            </optgroup>
        @endif
        @if ($value !== '' && $value !== $first?->name && ! $orgs->contains($value))<option value="{{ $value }}" selected>{{ $value }}</option>@endif
    </select></div>
