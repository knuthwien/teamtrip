# TeamTrip for Nextcloud

TeamTrip provides a Nextcloud-backed synchronization and sharing service for the TeamTrip iOS app. It stores versioned trip snapshots, supports read/edit sharing between Nextcloud users, and includes a compact web view for inspecting the current server state.

## Features

- Synchronize TeamTrip journeys with Nextcloud
- Share trips with other Nextcloud users using read or edit permissions
- Optimistic concurrency control to prevent silent overwrites
- Read-only web view of synchronized trips and activities
- English, German, Spanish, French, and Italian localization

## Requirements

- Nextcloud 31–35
- HTTPS strongly recommended
- TeamTrip iOS build with Nextcloud Sync support

## Manual installation

1. Copy the `teamtrip` folder to `<nextcloud>/apps/teamtrip`.
2. Ensure the web-server user can read the files.
3. Run `sudo -u www-data php occ app:enable teamtrip` from the Nextcloud root.
4. Open **TeamTrip** from the Nextcloud app navigation.
5. In the iOS app, open the three-dot menu, choose **Connect to Nextcloud**, and enter the server URL, user ID, and an app password.

The database migration is executed automatically when the app is enabled.

## API

OCS base path: `/ocs/v2.php/apps/teamtrip/api/v1`

Clients authenticate with standard Nextcloud Basic Auth using the user ID and an app password, send `OCS-APIRequest: true`, and request JSON.

Key endpoints:

- `GET /ping`
- `GET /trips`
- `GET /trips/{uuid}`
- `PUT /trips/{uuid}`
- `DELETE /trips/{uuid}`
- `GET /trips/{uuid}/shares`
- `POST /trips/{uuid}/shares`
- `DELETE /trips/{uuid}/shares/{userId}`

`PUT /trips/{uuid}` uses `baseRevision` for optimistic concurrency. A stale client receives HTTP 409 instead of silently overwriting a newer server revision.

## License

TeamTrip for Nextcloud is licensed under the GNU Affero General Public License v3.0 or later (`AGPL-3.0-or-later`).
