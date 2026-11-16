{{-- <x-commerce-rental::booking :product="$product" /> (a Product model, or :entry="$entry"): the booking form, for a theme's product page. --}}
@props(['product' => null, 'entry' => null])
@php($rentalForm = $product !== null ? \Sunrice\CommerceRental\BookingForm::forProduct($product) : ($entry !== null ? \Sunrice\CommerceRental\BookingForm::forEntry((int) $entry->id) : null))
@if ($rentalForm !== null)
    @include('commerce-rental::booking', ['form' => $rentalForm])
@endif
