{{--
    Delete button with a confirmation popup.
    @include('partials.delete', ['action' => route(...), 'name' => 'room 101', 'text' => '...', 'block' => null|'why not'])
--}}
<form method="POST" action="{{ $action }}" class="inline"
    @if (! empty($block))
        data-block-title="Can't delete {{ $name }}" data-block="{{ $block }}"
    @else
        data-confirm-title="Delete {{ $name }}?" data-confirm="{{ $text ?? 'This cannot be undone.' }}" data-confirm-button="Yes, delete" data-confirm-danger
    @endif>
    @csrf @method('DELETE')
    <button class="btn btn-sm btn-red" type="submit" @isset($label) aria-label="{{ $label }}" @endisset>Delete</button>
</form>
