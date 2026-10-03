<?php

declare(strict_types=1);

return [
    'routes' => [
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
        ['name' => 'page#trip', 'url' => '/trip/{uuid}', 'verb' => 'GET'],
    ],
    'ocs' => [
        ['name' => 'api#ping', 'url' => '/api/v1/ping', 'verb' => 'GET'],
        ['name' => 'api#listTrips', 'url' => '/api/v1/trips', 'verb' => 'GET'],
        ['name' => 'api#getTrip', 'url' => '/api/v1/trips/{uuid}', 'verb' => 'GET'],
        ['name' => 'api#putTrip', 'url' => '/api/v1/trips/{uuid}', 'verb' => 'PUT'],
        ['name' => 'api#deleteTrip', 'url' => '/api/v1/trips/{uuid}', 'verb' => 'DELETE'],
        ['name' => 'api#listShares', 'url' => '/api/v1/trips/{uuid}/shares', 'verb' => 'GET'],
        ['name' => 'api#addShare', 'url' => '/api/v1/trips/{uuid}/shares', 'verb' => 'POST'],
        ['name' => 'api#removeShare', 'url' => '/api/v1/trips/{uuid}/shares/{userId}', 'verb' => 'DELETE'],
    ],
];
