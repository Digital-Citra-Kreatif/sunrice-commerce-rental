# Changelog

## Unreleased

### Changed: branches are now the store's locations

Rental branches move to `sunrice/commerce-multi-locations`: the places dresses are picked up at are
the store's locations (Locations → Locations), which also hold stock of products for sale, ship
deliveries and offer pickup.

- Requires `sunrice/commerce-multi-locations`. Add its plugin before this one:
  `->plugin(MultiLocationsPlugin::make())->plugin(RentalPlugin::make())`.
- `php artisan migrate` turns each branch into the location with its code (made as a pickup point,
  keeping its id when free), points units and moves at it, and drops `commerce_rental_branches`.
  Rental lines in carts and orders keep working.
- The storefront, emails and rental screens say "location" ("lokasi"). Set
  `commerce-rental.location_term` to keep another word, e.g. `['id' => 'cabang', 'en' => 'branch']`.
- Rentals → Branches is gone (use Locations → Locations); permissions `rental.branches.*` go with it,
  give staff `locations.locations.*` instead.
- A delivery of dresses from two locations is refused by the locations package (its
  `errors.one_location` message); couriers quote from the location (its address, postal code, map
  point and phone).
- Names: `Unit::location()`, `location_id`, `RentalPeriod::$locationId`, `Availability::atLocations()`,
  `BookingForm::$locations`, the view `commerce-rental::order-locations`. Still accepted: cart lines
  with `rental.branch`, the form field and query parameter `branch`, `?rent_branch=`, a `branch`
  column in unit imports, and `$form->branches` / the availability JSON's `branches` for themes.
