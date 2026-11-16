{{-- product.before_add_to_cart: the booking form, for a product that is rented out. $entry --}}
@if (($form = \Sunrice\CommerceRental\BookingForm::forEntry((int) $entry->id)) !== null)
    @include('commerce-rental::booking', ['form' => $form])
@endif
