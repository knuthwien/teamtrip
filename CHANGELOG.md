# Changelog

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
