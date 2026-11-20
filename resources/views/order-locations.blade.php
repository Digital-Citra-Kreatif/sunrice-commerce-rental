{{-- order.after_summary: the locations of the order's rentals, with their address and a WhatsApp link. $order --}}
@php($rentalLocations = \Sunrice\CommerceMultiLocations\Models\Location::query()->whereIn('id', \Sunrice\CommerceRental\Models\Booking::query()->where('order_id', $order->id)->where('kind', 'order')->join('commerce_rental_units', 'commerce_rental_units.id', '=', 'commerce_rental_bookings.unit_id')->select('commerce_rental_units.location_id'))->orderBy('sort_order')->orderBy('id')->get())
@if ($rentalLocations->isNotEmpty())
    <div class="commerce-rental-branches commerce-rental-locations">
        <h3>{{ \Sunrice\CommerceRental\Wording::choice('commerce-rental::rental.order.locations', $rentalLocations->count()) }}</h3>
        @foreach ($rentalLocations as $location)
            <div class="commerce-rental-branches__item commerce-rental-locations__item">
                <strong>{{ $location->name }}</strong>
                @if ($location->address)<p class="commerce-muted">{{ $location->address }}</p>@endif
                @if ($location->whatsapp)
                    <a class="commerce-button commerce-button--whatsapp" target="_blank" rel="noopener"
                       href="https://wa.me/{{ preg_replace('/\D+/', '', $location->whatsapp) }}?text={{ rawurlencode(__('commerce-rental::rental.order.whatsapp_text', ['number' => $order->displayNumber()])) }}">{{ \Sunrice\CommerceRental\Wording::trans('commerce-rental::rental.order.whatsapp') }}</a>
                @endif
            </div>
        @endforeach
    </div>
@endif
