# TeamTrip for Nextcloud

Initial synchronization backend and server-inspection UI for the TeamTrip iOS app.

## Requirements
- Nextcloud 31–35
- HTTPS strongly recommended
- TeamTrip iOS build with Nextcloud Sync support

## Installation (manual development install)
1. Copy the `teamtrip` folder to `<nextcloud>/apps/teamtrip`.
2. Ensure the web-server user can read the files.
3. Run `sudo -u www-data php occ app:enable teamtrip` from the Nextcloud root.
4. Open **TeamTrip** from the Nextcloud app navigation.
5. In the iOS app, open the three-dot menu, choose **Connect to Nextcloud**, and enter the server URL, user ID and app password.

The database migration is executed automatically when the app is enabled.

The web interface follows each user's Nextcloud language setting. English,
German, Spanish, French and Italian are included.

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
