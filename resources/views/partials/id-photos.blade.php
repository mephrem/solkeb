{{-- ID card photos of a guest: @include('partials.id-photos', ['guest' => $g]) --}}
@if ($guest->id_front_path || $guest->id_back_path)
    <div class="id-photos">
        @foreach (['front' => $guest->id_front_path, 'back' => $guest->id_back_path] as $side => $path)
            @if ($path)
                @php $url = route('guests.id-photo', [$guest, $side]).'?v='.substr(md5($path), 0, 8); @endphp
                <a href="{{ $url }}" target="_blank" class="id-photo" title="Open the ID {{ $side }}">
                    @if (\Illuminate\Support\Str::endsWith(strtolower($path), '.pdf'))
                        <span class="id-pdf">PDF</span>
                    @else
                        <img src="{{ $url }}" alt="ID {{ $side }} of {{ $guest->full_name }}" loading="lazy">
                    @endif
                    <span>ID {{ $side }}</span>
                </a>
            @endif
        @endforeach
    </div>
@else
    <span class="small owe">No ID photos yet</span>
@endif
