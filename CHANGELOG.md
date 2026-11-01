# Changelog

## 0.2.0

- Document and freeze the TeamTrip 0.1.8 iOS synchronization contract for GUI-only development.
- Redesign the trip overview with upcoming and past trip sections.
- Add trip-title search and filters for all, owned and shared trips.
- Show activity and revision counts in responsive, accessible trip cards.
- Replace technical activity type names with localized labels and recognizable icons.
- Remove activity UUIDs from the main table and prevent long values from creating oversized rows.
- Present activities as compact cards on narrow screens instead of a horizontally scrolling table.
- Add a read-only, iOS-inspired trip detail view with section navigation and a cost overview.
- Surface travelers, categories, payment modes, currencies, budgets and shares without exposing technical identifiers.
- Compact travelers, categories and payment modes into icon chips, aggregate meaningful budgets by currency and strengthen page-heading and anchor navigation contrast.
- Bust stale Nextcloud stylesheet caches and refine the trip detail with linked-user avatar badges, compact category/payment tiles and stronger location hierarchy.
- Recognize the trip owner's localized “Me” traveler as a linked Nextcloud account and keep category labels on one line.
- Prevent awkward category-name breaks and render real currency symbols instead of the generic currency placeholder.
- Group activities by local travel day and show time ranges, durations, time zones, notes, locations and costs responsively.
- Add trip status, currency-safe totals, category and traveler cost breakdowns, plus notices for incomplete cost assignments.
- Let trip owners add, update and remove Nextcloud shares through CSRF-protected web forms without changing trip snapshots.
- Preserve all existing OCS routes, snapshot semantics, permissions and persistence behavior.

## 0.1.8

- Fix scrolling by using Nextcloud's outer content viewport as the scroll container.
- Stack technical metadata labels and values to prevent overlap at every width.
- Add extra bottom space so expanded server information remains fully reachable.

## 0.1.7

- Replaced the generic suitcase with a dedicated TeamTrip map-and-route navigation icon.
- Prevented metadata labels and values from overlapping on desktop and compact layouts.
- Made the app content vertically scrollable when technical server information is expanded.

## 0.1.6

- Ship a new cache-safe SVG navigation icon for the Nextcloud app menu.
- Prevent localized metadata labels from colliding with values in trip cards.
- Improve responsive activity tables and traveler/share rows.

## 0.1.5

- Deliver the navigation icon as a newly named RGBA PNG to avoid stale icon caches and SVG theming/rendering incompatibilities.
- Keep the compact, high-contrast web layout without the redundant username pill.

## 0.1.4

- Use a newly named, cache-busting navigation icon with explicit light and dark variants.
- Rebuild trip cards with collision-free metadata rows and a responsive full-width layout.
- Improve text contrast, date readability, UUID wrapping and scaling on narrow screens.
- Remove the redundant account-name pill from the trip overview.

## 0.1.3

- Correct the light and dark navigation icon variants so the icon remains visible in Nextcloud's overflow menu.
- Improve secondary-text contrast throughout the web interface.
- Add German, English, Spanish, French and Italian web-interface localization matching the iOS app.

## 0.1.2

- Allow the authenticated read-only web pages to load without an unavailable initial CSRF token.
- Use an explicit white navigation icon instead of `currentColor`, which is not inherited by an externally loaded SVG.
- Add a dark icon variant for light navigation contexts.

## 0.1.1

- Register TeamTrip in the Nextcloud app menu using the current `navigations` metadata schema.
- Declare the application icon and link navigation type explicitly.
- Use the SPDX identifier for the AGPL license required by current Nextcloud releases.

## 0.1.0

- Initial Nextcloud synchronization API and server-state web interface.
