{{-- The body of the rental reminders: each rental with its days and the location to go to. $context['bookings'] --}}
@foreach ($context['bookings'] ?? [] as $booking)
@php($place = $booking->unit?->location)
- **{{ $booking->order?->items->firstWhere('id', $booking->order_item_id)?->title() ?? $booking->unit?->code }}**: {{ $booking->starts_on->translatedFormat('j F Y') }} – {{ $booking->ends_on->translatedFormat('j F Y') }}@if ($place) · {{ \Sunrice\CommerceRental\Wording::trans('commerce-rental::emails.location', ['name' => $place->name]) }}@endif

@if ($place?->address)
  {{ $place->address }}
@endif
@if ($place?->whatsapp)
  {{ \Sunrice\CommerceRental\Wording::trans('commerce-rental::emails.whatsapp', ['number' => $place->whatsapp]) }}
@endif
@endforeach
