<div class="numpad-grid" style="display:grid;grid-template-columns:repeat(3,1fr);gap:6px;max-width:240px;margin:0 auto;">
    @foreach ([1,2,3,4,5,6,7,8,9] as $n)
        <button type="button" class="btn btn-outline-secondary numpad-btn" data-val="{{ $n }}"
                style="font-size:1.2rem;font-weight:600;padding:12px;">{{ $n }}</button>
    @endforeach
    <div></div>
    <button type="button" class="btn btn-outline-secondary numpad-btn" data-val="0"
            style="font-size:1.2rem;font-weight:600;padding:12px;">0</button>
    <button type="button" class="btn btn-outline-danger numpad-btn" data-val="back"
            style="font-size:1rem;padding:12px;"><i class="fa fa-backspace"></i></button>
</div>
