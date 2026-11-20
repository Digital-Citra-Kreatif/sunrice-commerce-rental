{{--
    The booking form: size, pickup and return days, location, the free units and the price, then
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

    @if ($form->packages !== [])
        <fieldset class="commerce-rental__packages">
            <legend>{{ __('commerce-rental::rental.form.package') }}</legend>
            <label><input type="radio" name="package" value="" @checked(! old('package')) data-rental-package data-days=""> {{ __('commerce-rental::rental.form.regular') }}</label>
            @foreach ($form->packages as $package)
                <label><input type="radio" name="package" value="{{ $package['key'] }}" @checked(old('package') === $package['key']) data-rental-package data-days="{{ $package['days'] }}">
                    {{ __('commerce-rental::rental.form.package_option', ['name' => $package['name'], 'days' => $package['days'], 'fee' => $package['fee']]) }}</label>
            @endforeach
        </fieldset>
    @endif

    <div class="commerce-rental__dates">
        <label class="commerce-rental__field">
            <span>{{ __('commerce-rental::rental.form.start') }}</span>
            <input type="date" name="start" required min="{{ $form->minDate }}" @if ($form->maxDate) max="{{ $form->maxDate }}" @endif value="{{ old('start') }}" data-rental-start>
        </label>
        <label class="commerce-rental__field">
            <span>{{ __('commerce-rental::rental.form.end') }}</span>
            <input type="date" name="end" required min="{{ $form->minDate }}" value="{{ old('end') }}" data-rental-end>
        </label>
    </div>

    <label class="commerce-rental__field">
        <span>{{ \Sunrice\CommerceRental\Wording::trans('commerce-rental::rental.form.location') }}</span>
        <select name="location" required data-rental-location>
            @foreach ($form->locations as $location)
                <option value="{{ $location['id'] }}" @selected((int) old('location') === $location['id'])>{{ $location['name'] }}</option>
            @endforeach
        </select>
    </label>

    <p class="commerce-rental__status" data-rental-status aria-live="polite">{{ __('commerce-rental::rental.form.choose') }}</p>
    <p class="commerce-muted commerce-rental__rules">{{ $form->pricingNote ?? __('commerce-rental::rental.form.rules', ['min' => $form->minDays, 'max' => $form->maxDays]) }}</p>

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
        var place = form.querySelector('[data-rental-location]'), variant = form.querySelector('[data-rental-variant]');
        var status = form.querySelector('[data-rental-status]'), submit = form.querySelector('[data-rental-submit]');
        var timer, last;
        function chosenPackage() { var p = form.querySelector('[data-rental-package]:checked'); return p ? p : null; }
        function check() {
            var pkg = chosenPackage(), days = pkg && pkg.dataset.days ? parseInt(pkg.dataset.days, 10) : 0;
            end.readOnly = days > 0;
            if (start.value && days > 0) { var d = new Date(start.value + 'T00:00:00'); d.setDate(d.getDate() + days - 1); end.value = d.toISOString().slice(0, 10); }
            if (start.value) { end.min = start.value; if (end.value && end.value < start.value) { end.value = start.value; } }
            if (!start.value || !end.value) { status.textContent = t.choose; return; }
            var url = form.dataset.availability + '?' + new URLSearchParams({ variant: variant.value, start: start.value, end: end.value, package: pkg ? pkg.value : '' });
            last = url;
            fetch(url, { headers: { Accept: 'application/json' } }).then(function (r) { return r.json(); }).then(function (data) {
                if (url !== last) { return; }
                if (data.message) { status.textContent = data.message; status.dataset.state = 'error'; return; }
                var row = (data.locations || []).filter(function (l) { return String(l.id) === place.value; })[0];
                var name = place.options[place.selectedIndex].text;
                var ok = row && row.available > 0;
                status.dataset.state = ok ? 'ok' : 'error';
                status.textContent = (ok ? fill(t.available, { name: name }) : fill(t.unavailable, { name: name })) + ' · ' + fill(t.price, { days: data.days, price: data.price_formatted });
                if (submit) { submit.disabled = !ok; }
            }).catch(function () {});
        }
        [start, end, place, variant].concat(Array.prototype.slice.call(form.querySelectorAll('[data-rental-package]'))).forEach(function (el) { el && el.addEventListener('change', function () { clearTimeout(timer); timer = setTimeout(check, 150); }); });
        check();
    });
})();
</script>
@endpush
@endonce
