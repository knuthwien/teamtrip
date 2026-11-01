# TeamTrip iOS sync compatibility contract

TeamTrip for Nextcloud 0.2.0 is a web-interface release based on the signed 0.1.8 server implementation. The iOS app must continue to synchronize without an update.

## Frozen compatibility surface

Until a separately reviewed sync release is planned, web-interface work must not change:

- the OCS route paths, verbs or authentication requirements in `appinfo/routes.php`;
- the response keys, HTTP status codes or conflict behavior in `ApiController`;
- snapshot schema version 1 or the meaning of fields written by iOS;
- revision handling, including `baseRevision`, `force` and HTTP 409 conflicts;
- owner, edit and read permission semantics;
- soft deletion behavior;
- database tables, columns or migrations;
- `TripRepository` persistence behavior.

## Allowed 0.2.0 changes

- read-only parsing of existing snapshots for the Nextcloud web interface;
- web page controllers and GET query parameters that do not affect OCS routes;
- PHP templates, CSS, accessibility and responsive behavior;
- translations and documentation;
- later, separately reviewed web-only actions using the existing API contract.

The 0.2.0 web share forms are limited to the existing `read` and `edit` share
records. They do not write to the trip snapshot, increment its revision or
change any OCS response.

## Regression checklist

Before packaging a release candidate, verify with the existing iOS app:

1. An owner can list, upload, update and delete a trip.
2. An edit share can list, download and update a trip.
3. A read share can list and download a trip but receives a forbidden response when writing.
4. A stale revision still returns HTTP 409 with the current snapshot and revision.
5. A removed share disappears from the affected iOS account after synchronization.
6. A server-deleted trip follows the established iOS recovery flow.
7. Existing 0.1.8 trips remain readable in the web interface and on iOS.

Any change outside this contract requires an explicit compatibility review and must not be bundled into a GUI-only iteration.
