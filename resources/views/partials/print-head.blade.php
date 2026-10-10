{{-- Page setup and hotel letterhead for printed documents (credit invoice, payment request letter). --}}
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" type="image/png" href="{{ asset('images/solkeb-icon-64.png') }}">
<link rel="stylesheet" href="{{ asset('css/solkeb.css') }}?v=17">
<style>
    body { background: #fff; }
    .doc { max-width: 780px; margin: 1.5rem auto; padding: 0 1rem; }
    .inv-head { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 4px solid var(--brand); padding-bottom: 1rem; margin-bottom: 1.25rem; gap: 1rem; }
    .inv-tagline { font-family: Georgia, 'Times New Roman', serif; font-style: italic; color: #2e3192; margin: .15rem 0 .35rem; }
    .inv-meta { text-align: right; }
    .inv-parties { display: flex; justify-content: space-between; gap: 1rem; margin-bottom: 1.25rem; flex-wrap: wrap; }
    .sign { display: flex; justify-content: space-between; margin-top: 3rem; gap: 2rem; }
    .sign div { flex: 1; border-top: 1px solid #94a3b8; padding-top: .3rem; font-size: .85rem; color: var(--muted); }
    .letter p { line-height: 1.65; }
    @media print { .doc { margin: 0 auto; } }
        @media screen and (max-width: 640px) { .inv-head { flex-wrap: wrap; } .inv-meta { text-align: left; } .inv-parties .right { text-align: left; } table { display: block; overflow-x: auto; } .sign { flex-direction: column; gap: 2.5rem; } }
</style>
