{{-- order.after_summary: the branches of the order's rentals, with their address and a WhatsApp link. $order --}}
@php($rentalBranches = \Sunrice\CommerceRental\Models\Branch::query()->whereIn('id', \Sunrice\CommerceRental\Models\Booking::query()->where('order_id', $order->id)->where('kind', 'order')->join('commerce_rental_units', 'commerce_rental_units.id', '=', 'commerce_rental_bookings.unit_id')->select('commerce_rental_units.branch_id'))->orderBy('sort_order')->get())
@if ($rentalBranches->isNotEmpty())
    <div class="commerce-rental-branches">
        <h3>{{ trans_choice('commerce-rental::rental.order.branches', $rentalBranches->count()) }}</h3>
        @foreach ($rentalBranches as $branch)
            <div class="commerce-rental-branches__item">
                <strong>{{ $branch->name }}</strong>
                @if ($branch->address)<p class="commerce-muted">{{ $branch->address }}</p>@endif
                @if ($branch->whatsapp)
                    <a class="commerce-button commerce-button--whatsapp" target="_blank" rel="noopener"
                       href="https://wa.me/{{ preg_replace('/\D+/', '', $branch->whatsapp) }}?text={{ rawurlencode(__('commerce-rental::rental.order.whatsapp_text', ['number' => $order->displayNumber()])) }}">{{ __('commerce-rental::rental.order.whatsapp') }}</a>
                @endif
            </div>
        @endforeach
    </div>
@endif
