{{--
    The booking form: size, pickup and return days, branch, the free units and the price, then
    "Add to cart". Works without JavaScript (the dates are checked when added and again at
    checkout); with it, the availability line follows the choices.
    $form: Sunrice\CommerceRental\BookingForm. Themes may include it anywhere on the product page.
--}}
<form class="commerce-rental" method="POST" action="{{ $form->action }}" data-commerce-rental data-availability="{{ $form->availabilityUrl }}">
    @csrf
    <x-honeypot />
    <h2 class="commerce-rental__title">{{ __('commerce-rental::rental.form.title') }}</h2>

    @if (count($form->variants) > 1)
        <label class="commerce-rental__field">
            <span>{{ __('commerce-rental::rental.form.size') }}</span>
            <select name="variant" required data-rental-variant>
                @foreach ($form->variants as $variant)
                    <option value="{{ $variant['id'] }}" @selected((int) old('variant') === $variant['id'])>{{ $variant['title'] }} · {{ $variant['price'] }}</option>
                @endforeach
            </select>
        </label>
    @else
        <input type="hidden" name="variant" value="{{ $form->variants[0]['id'] }}" data-rental-variant>
    @endif

    <div class="commerce-rental__dates">
        <label class="commerce-rental__field">
            <span>{{ __('commerce-rental::rental.form.start') }}</span>
            <input type="date" name="start" required min="{{ $form->minDate }}" max="{{ $form->maxDate }}" value="{{ old('start') }}" data-rental-start>
        </label>
        <label class="commerce-rental__field">
            <span>{{ __('commerce-rental::rental.form.end') }}</span>
            <input type="date" name="end" required min="{{ $form->minDate }}" value="{{ old('end') }}" data-rental-end>
        </label>
    </div>

    <label class="commerce-rental__field">
        <span>{{ __('commerce-rental::rental.form.branch') }}</span>
        <select name="branch" required data-rental-branch>
            @foreach ($form->branches as $branch)
                <option value="{{ $branch['id'] }}" @selected((int) old('branch') === $branch['id'])>{{ $branch['name'] }}</option>
            @endforeach
        </select>
    </label>

    <p class="commerce-rental__status" data-rental-status aria-live="polite">{{ __('commerce-rental::rental.form.choose') }}</p>
    <p class="commerce-muted commerce-rental__rules">{{ __('commerce-rental::rental.form.rules', ['min' => $form->minDays, 'max' => $form->maxDays]) }}</p>

    <div class="commerce-rental__actions">
        <button type="submit" class="commerce-add__button" data-rental-submit>{{ __('commerce-rental::rental.form.add') }}</button>
        <button type="submit" name="buy_now" value="1" class="commerce-button commerce-button--secondary">{{ __('commerce-rental::rental.form.buy_now') }}</button>
    </div>
</form>

@once
@php($rentalTexts = ['choose' => __('commerce-rental::rental.form.choose'), 'available' => __('commerce-rental::rental.form.available'),
    'unavailable' => __('commerce-rental::rental.form.unavailable'), 'price' => __('commerce-rental::rental.form.price')])
@push('scripts')
<script>
(function () {
    var t = {!! json_encode($rentalTexts, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!};
    function fill(text, values) { return text.replace(/:(\w+)/g, function (m, k) { return values[k] !== undefined ? values[k] : m; }); }
    document.querySelectorAll('[data-commerce-rental]').forEach(function (form) {
        var start = form.querySelector('[data-rental-start]'), end = form.querySelector('[data-rental-end]');
        var branch = form.querySelector('[data-rental-branch]'), variant = form.querySelector('[data-rental-variant]');
        var status = form.querySelector('[data-rental-status]'), submit = form.querySelector('[data-rental-submit]');
        var timer, last;
        function check() {
            if (start.value) { end.min = start.value; if (end.value && end.value < start.value) { end.value = start.value; } }
            if (!start.value || !end.value) { status.textContent = t.choose; return; }
            var url = form.dataset.availability + '?' + new URLSearchParams({ variant: variant.value, start: start.value, end: end.value });
            last = url;
            fetch(url, { headers: { Accept: 'application/json' } }).then(function (r) { return r.json(); }).then(function (data) {
                if (url !== last) { return; }
                if (data.message) { status.textContent = data.message; status.dataset.state = 'error'; return; }
                var row = (data.branches || []).filter(function (b) { return String(b.id) === branch.value; })[0];
                var name = branch.options[branch.selectedIndex].text;
                var ok = row && row.available > 0;
                status.dataset.state = ok ? 'ok' : 'error';
                status.textContent = (ok ? fill(t.available, { branch: name }) : fill(t.unavailable, { branch: name })) + ' · ' + fill(t.price, { days: data.days, price: data.price_formatted });
                if (submit) { submit.disabled = !ok; }
            }).catch(function () {});
        }
        [start, end, branch, variant].forEach(function (el) { el && el.addEventListener('change', function () { clearTimeout(timer); timer = setTimeout(check, 150); }); });
        check();
    });
})();
</script>
@endpush
@endonce
