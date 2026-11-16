{{-- The body of the rental reminders: each rental with its days and the branch to go to. $context['bookings'] --}}
@foreach ($context['bookings'] ?? [] as $booking)
@php($branch = $booking->unit?->branch)
- **{{ $booking->order?->items->firstWhere('id', $booking->order_item_id)?->title() ?? $booking->unit?->code }}**: {{ $booking->starts_on->translatedFormat('j F Y') }} – {{ $booking->ends_on->translatedFormat('j F Y') }}@if ($branch) · {{ __('commerce-rental::emails.branch', ['branch' => $branch->name]) }}@endif

@if ($branch?->address)
  {{ $branch->address }}
@endif
@if ($branch?->whatsapp)
  {{ __('commerce-rental::emails.whatsapp', ['number' => $branch->whatsapp]) }}
@endif
@endforeach
