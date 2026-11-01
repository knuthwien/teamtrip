<?php
/** @var array $_ */
$trip = $_['trip'];
$travelers = is_array($trip['travelers'] ?? null) ? array_values($trip['travelers']) : [];
$categories = is_array($trip['categories'] ?? null) ? array_values($trip['categories']) : [];
$paymentModes = is_array($trip['paymentModes'] ?? null)
    ? array_values($trip['paymentModes'])
    : (is_array($trip['paymentMethods'] ?? null) ? array_values($trip['paymentMethods']) : []);
$budgetSource = $trip['budgets'] ?? ($trip['budget'] ?? []);
$currencySource = $trip['currencies'] ?? ($trip['currencyCodes'] ?? []);
$scalarText = static function ($value): string {
    return is_scalar($value) ? trim((string)$value) : '';
};
$extractCurrencyCode = static function ($value) use ($scalarText): string {
    if (is_array($value)) {
        foreach (['currencyCode', 'code', 'currency', 'isoCode'] as $key) {
            if (array_key_exists($key, $value)) {
                $code = strtoupper($scalarText($value[$key]));
                if (preg_match('/^[A-Z]{3}$/', $code) === 1) {
                    return $code;
                }
            }
        }
        return '';
    }

    $code = strtoupper($scalarText($value));
    return preg_match('/^[A-Z]{3}$/', $code) === 1 ? $code : '';
};
$primaryCurrency = $extractCurrencyCode($trip['currencyCode'] ?? '');
if ($primaryCurrency === '') {
    $primaryCurrency = $extractCurrencyCode($trip['currency'] ?? '');
}
$currencyCodes = [];
$addCurrency = static function ($value) use (&$currencyCodes, $extractCurrencyCode): void {
    $code = $extractCurrencyCode($value);
    if ($code !== '') {
        $currencyCodes[$code] = true;
    }
};
$currencySymbol = static function (string $code): string {
    return match ($code) {
        'EUR' => '€',
        'USD' => '$',
        'GBP' => '£',
        'JPY', 'CNY' => '¥',
        'CHF' => 'Fr.',
        'CAD' => 'C$',
        'AUD' => 'A$',
        'NZD' => 'NZ$',
        'INR' => '₹',
        'KRW' => '₩',
        'SEK', 'NOK', 'DKK' => 'kr',
        'PLN' => 'zł',
        'CZK' => 'Kč',
        'HUF' => 'Ft',
        'RON' => 'lei',
        'BGN' => 'лв',
        'TRY' => '₺',
        'ZAR' => 'R',
        default => '💱',
    };
};
$addCurrency($primaryCurrency);
if (is_array($currencySource)) {
    foreach ($currencySource as $key => $currency) {
        $addCurrency($key);
        $addCurrency($currency);
    }
} else {
    $addCurrency($currencySource);
}

$roleLabels = [
    'owner' => $l->t('Owner role'),
    'edit' => $l->t('Can edit'),
    'read' => $l->t('Read only'),
];
$formatDateTime = static function ($value): string {
    if (!is_string($value) || trim($value) === '') {
        return '—';
    }
    $timestamp = strtotime($value);
    return $timestamp === false ? $value : date('d.m.Y, H:i', $timestamp);
};
$parseLocalDateTime = static function ($value, $timeZoneId = null): ?\DateTimeImmutable {
    if (!is_scalar($value) || trim((string)$value) === '') {
        return null;
    }
    try {
        $date = new \DateTimeImmutable((string)$value);
        if (is_scalar($timeZoneId) && trim((string)$timeZoneId) !== '') {
            $date = $date->setTimezone(new \DateTimeZone(trim((string)$timeZoneId)));
        }
        return $date;
    } catch (\Throwable $e) {
        return null;
    }
};
$formatDay = static function (\DateTimeImmutable $date) use ($l): string {
    if (class_exists(\IntlDateFormatter::class)) {
        $formatter = new \IntlDateFormatter(
            $l->getLanguageCode(),
            \IntlDateFormatter::FULL,
            \IntlDateFormatter::NONE,
            $date->getTimezone()->getName(),
            \IntlDateFormatter::GREGORIAN,
            'EEEE, d. MMMM yyyy',
        );
        $formatted = $formatter->format($date);
        if (is_string($formatted) && $formatted !== '') {
            return $formatted;
        }
    }
    return $date->format('d.m.Y');
};
$formatTime = static fn(?\DateTimeImmutable $date): string => $date?->format('H:i') ?? '—';
$formatDuration = static function (?\DateTimeImmutable $start, ?\DateTimeImmutable $end, $storedMinutes = null): string {
    $minutes = is_numeric($storedMinutes) ? max(0, (int)round((float)$storedMinutes)) : null;
    if ($minutes === null && $start !== null && $end !== null && $end >= $start) {
        $minutes = (int)round(($end->getTimestamp() - $start->getTimestamp()) / 60);
    }
    if ($minutes === null || $minutes <= 0) {
        return '';
    }
    $hours = intdiv($minutes, 60);
    $remaining = $minutes % 60;
    if ($hours > 0 && $remaining > 0) {
        return $hours . ' h ' . $remaining . ' min';
    }
    return $hours > 0 ? $hours . ' h' : $remaining . ' min';
};
$activityTypes = [
    'activity' => ['✦', 'Activity'],
    'flight' => ['✈️', 'Flight'],
    'plane' => ['✈️', 'Flight'],
    'train' => ['🚆', 'Train'],
    'bus' => ['🚌', 'Bus'],
    'car' => ['🚗', 'Car'],
    'taxi' => ['🚕', 'Taxi'],
    'ferry' => ['⛴️', 'Ferry'],
    'hotel' => ['🛏️', 'Accommodation'],
    'stay' => ['🛏️', 'Accommodation'],
    'accommodation' => ['🛏️', 'Accommodation'],
    'breakfast' => ['☕', 'Breakfast'],
    'lunch' => ['🍽️', 'Lunch'],
    'dinner' => ['🍽️', 'Dinner'],
    'food' => ['🍽️', 'Meal'],
    'meal' => ['🍽️', 'Meal'],
    'restaurant' => ['🍽️', 'Meal'],
    'event' => ['🎟️', 'Event'],
    'sightseeing' => ['◎', 'Sightseeing'],
    'walk' => ['🚶', 'Walk'],
    'hike' => ['🥾', 'Hike'],
    'shopping' => ['🛍️', 'Shopping'],
    'other' => ['•', 'Other'],
];
$formatActivityType = static function ($value) use ($activityTypes, $l, $scalarText): array {
    $raw = $scalarText($value);
    $key = strtolower($raw);
    if (isset($activityTypes[$key])) {
        return [
            'icon' => $activityTypes[$key][0],
            'label' => $l->t($activityTypes[$key][1]),
        ];
    }

    $readable = trim(str_replace(['_', '-'], ' ', $raw));
    return [
        'icon' => '•',
        'label' => $readable === '' ? $l->t('Other') : mb_convert_case($readable, MB_CASE_TITLE, 'UTF-8'),
    ];
};
$normalizeColor = static function ($value): ?string {
    if (!is_scalar($value)) {
        return null;
    }
    $color = trim((string)$value);
    if (preg_match('/^#[0-9a-fA-F]{6}([0-9a-fA-F]{2})?$/', $color) === 1) {
        return $color;
    }
    if (preg_match('/^[0-9a-fA-F]{6}([0-9a-fA-F]{2})?$/', $color) === 1) {
        return '#' . $color;
    }
    return null;
};
$entryName = static function ($entry, array $keys, string $fallback): string {
    if (is_scalar($entry)) {
        $value = trim((string)$entry);
        return $value !== '' ? $value : $fallback;
    }
    if (!is_array($entry)) {
        return $fallback;
    }
    foreach ($keys as $key) {
        if (isset($entry[$key]) && is_scalar($entry[$key])) {
            $value = trim((string)$entry[$key]);
            if ($value !== '') {
                return $value;
            }
        }
    }
    return $fallback;
};
$entryIcon = static function ($entry, string $fallback): string {
    if (!is_array($entry)) {
        return $fallback;
    }
    foreach (['icon', 'emoji', 'symbol', 'iconName', 'symbolName', 'sfSymbol'] as $key) {
        if (isset($entry[$key]) && is_scalar($entry[$key]) && trim((string)$entry[$key]) !== '') {
            return trim((string)$entry[$key]);
        }
    }
    return $fallback;
};
$entryColor = static function ($entry) use ($normalizeColor): ?string {
    if (!is_array($entry)) {
        return null;
    }
    foreach (['colorHex', 'hexColor', 'color'] as $key) {
        if (isset($entry[$key])) {
            $color = $normalizeColor($entry[$key]);
            if ($color !== null) {
                return $color;
            }
        }
    }
    return null;
};
$linkedUser = static function ($entry): string {
    if (!is_array($entry)) {
        return '';
    }
    foreach (['nextcloudUserId', 'linkedUserId', 'userId'] as $key) {
        if (isset($entry[$key]) && is_scalar($entry[$key])) {
            $value = trim((string)$entry[$key]);
            if ($value !== '') {
                return $value;
            }
        }
    }
    return '';
};
$entryIdentifiers = static function ($entry): array {
    if (!is_array($entry)) {
        return [];
    }
    $identifiers = [];
    foreach (['uuid', 'id', 'categoryId', 'categoryUUID', 'categoryUuid', 'travelerId', 'travelerUUID', 'travelerUuid', 'memberId', 'memberUUID', 'typeRaw', 'rawValue', 'type', 'systemName'] as $key) {
        if (isset($entry[$key]) && is_scalar($entry[$key])) {
            $value = trim((string)$entry[$key]);
            if ($value !== '') {
                $identifiers[] = mb_strtolower($value, 'UTF-8');
            }
        }
    }
    return array_values(array_unique($identifiers));
};
$itemReference = static function (array $item, array $keys) use ($scalarText): string {
    foreach ($keys as $key) {
        if (!array_key_exists($key, $item)) {
            continue;
        }
        $value = $item[$key];
        if (is_array($value)) {
            foreach (['uuid', 'id', 'name', 'title', 'label'] as $nestedKey) {
                if (array_key_exists($nestedKey, $value)) {
                    $nested = $scalarText($value[$nestedKey]);
                    if ($nested !== '') {
                        return mb_strtolower($nested, 'UTF-8');
                    }
                }
            }
            continue;
        }
        $reference = $scalarText($value);
        if ($reference !== '') {
            return mb_strtolower($reference, 'UTF-8');
        }
    }
    return '';
};
$isOwnerTraveler = static function (string $name): bool {
    $normalized = mb_strtolower(trim($name), 'UTF-8');
    return in_array($normalized, ['ich', 'me', 'moi', 'io', 'yo'], true);
};
$resolveEntityIcon = static function ($entry, string $name, string $kind) use ($entryIcon): string {
    $stored = $entryIcon($entry, '');
    if ($stored !== '' && preg_match('/[^\x00-\x7F]/u', $stored) === 1) {
        return $stored;
    }

    $haystack = mb_strtolower($name . ' ' . $stored, 'UTF-8');
    $maps = $kind === 'payment'
        ? [
            ['card', 'karte', 'credit', 'debit', '💳'],
            ['cash', 'bar', 'bargeld', '💵'],
            ['bank', 'transfer', 'überweisung', 'ueberweisung', '🏦'],
            ['paypal', 'wallet', 'apple pay', '📱'],
        ]
        : [
            ['hotel', 'unterkunft', 'accommodation', 'bed', '🛏️'],
            ['restaurant', 'essen', 'food', 'meal', 'fork', '🍽️'],
            ['frühstück', 'breakfast', 'coffee', '☕'],
            ['flug', 'flight', 'plane', 'airplane', '✈️'],
            ['zug', 'train', 'tram', '🚆'],
            ['bus', 'public transport', 'öffis', '🚌'],
            ['auto', 'car', 'taxi', '🚗'],
            ['fähre', 'ferry', 'ship', '⛴️'],
            ['aktivität', 'activity', 'event', 'ticket', '🎟️'],
            ['kultur', 'culture', 'museum', '🏛️'],
            ['einkauf', 'shopping', 'shop', '🛍️'],
            ['gebühr', 'fee', 'money', '💶'],
        ];
    foreach ($maps as $mapping) {
        $icon = array_pop($mapping);
        foreach ($mapping as $needle) {
            if (str_contains($haystack, $needle)) {
                return $icon;
            }
        }
    }
    return $kind === 'payment' ? '💳' : '✦';
};
$categoryLookup = [];
foreach ($categories as $index => $category) {
    $name = $entryName($category, ['name', 'title', 'label'], $l->t('Unnamed category'));
    $entity = [
        'key' => 'category-' . $index,
        'name' => $name,
        'icon' => $resolveEntityIcon($category, $name, 'category'),
    ];
    foreach (array_merge($entryIdentifiers($category), [mb_strtolower($name, 'UTF-8')]) as $alias) {
        $categoryLookup[$alias] = $entity;
    }
}
$travelerLookup = [];
foreach ($travelers as $index => $traveler) {
    $name = $entryName($traveler, ['name', 'title', 'label'], $l->t('Unnamed traveler'));
    $entity = ['key' => 'traveler-' . $index, 'name' => $name];
    foreach (array_merge($entryIdentifiers($traveler), [mb_strtolower($name, 'UTF-8')]) as $alias) {
        $travelerLookup[$alias] = $entity;
    }
}

$costTotals = [];
$categoryCosts = [];
$travelerCosts = [];
$activityGroups = [];
$dataWarnings = [
    'missingCategory' => 0,
    'unknownCategory' => 0,
    'unknownTraveler' => 0,
    'missingCurrency' => 0,
];
$categoryReferenceKeys = ['categoryId', 'categoryUUID', 'categoryUuid', 'category', 'categoryName', 'typeRaw', 'type'];
$travelerReferenceKeys = ['travelerId', 'travelerUUID', 'travelerUuid', 'memberId', 'memberUUID', 'paidById', 'payerId', 'payerUUID', 'traveler'];
foreach ($_['items'] as $item) {
    $start = $parseLocalDateTime($item['fromAt'] ?? null, $item['fromTimeZoneId'] ?? null);
    $endValue = $item['toAt'] ?? ($item['endAt'] ?? ($item['untilAt'] ?? null));
    $endTimeZone = $item['toTimeZoneId'] ?? ($item['endTimeZoneId'] ?? ($item['fromTimeZoneId'] ?? null));
    $end = $parseLocalDateTime($endValue, $endTimeZone);
    $groupKey = $start?->format('Y-m-d') ?? 'undated';
    if (!isset($activityGroups[$groupKey])) {
        $activityGroups[$groupKey] = [
            'label' => $start !== null ? $formatDay($start) : $l->t('Without date'),
            'items' => [],
        ];
    }
    $activityGroups[$groupKey]['items'][] = [
        'item' => $item,
        'start' => $start,
        'end' => $end,
        'duration' => $formatDuration($start, $end, $item['durationMinutes'] ?? ($item['duration'] ?? null)),
    ];

    $amount = is_numeric($item['cost'] ?? null) ? (float)$item['cost'] : 0.0;
    $currency = $extractCurrencyCode($item['currencyCode'] ?? '');
    if ($currency === '') {
        $currency = $extractCurrencyCode($item['currency'] ?? $primaryCurrency);
    }
    if ($currency === '' && $amount != 0.0) {
        ++$dataWarnings['missingCurrency'];
    }
    $addCurrency($currency);
    $costTotals[$currency] = ($costTotals[$currency] ?? 0.0) + $amount;
    if ($amount == 0.0) {
        continue;
    }

    $categoryReference = $itemReference($item, $categoryReferenceKeys);
    if ($categoryReference === '') {
        ++$dataWarnings['missingCategory'];
        $categoryEntity = ['key' => 'unassigned', 'name' => $l->t('Unassigned'), 'icon' => '○'];
    } elseif (isset($categoryLookup[$categoryReference])) {
        $categoryEntity = $categoryLookup[$categoryReference];
    } else {
        ++$dataWarnings['unknownCategory'];
        $categoryEntity = ['key' => 'unknown', 'name' => $l->t('Unknown category'), 'icon' => '?'];
    }
    $categoryKey = $categoryEntity['key'];
    $categoryCosts[$categoryKey] ??= ['name' => $categoryEntity['name'], 'icon' => $categoryEntity['icon'], 'totals' => []];
    $categoryCosts[$categoryKey]['totals'][$currency] = ($categoryCosts[$categoryKey]['totals'][$currency] ?? 0.0) + $amount;

    $travelerReference = $itemReference($item, $travelerReferenceKeys);
    if ($travelerReference !== '') {
        if (isset($travelerLookup[$travelerReference])) {
            $travelerEntity = $travelerLookup[$travelerReference];
            $travelerKey = $travelerEntity['key'];
            $travelerCosts[$travelerKey] ??= ['name' => $travelerEntity['name'], 'totals' => []];
            $travelerCosts[$travelerKey]['totals'][$currency] = ($travelerCosts[$travelerKey]['totals'][$currency] ?? 0.0) + $amount;
        } else {
            ++$dataWarnings['unknownTraveler'];
        }
    }
}
if (isset($activityGroups['undated'])) {
    $undated = $activityGroups['undated'];
    unset($activityGroups['undated']);
    $activityGroups['undated'] = $undated;
}
$dataWarningCount = array_sum($dataWarnings);
$budgetTotals = [];
$addBudget = static function ($amount, string $currency = '') use (&$budgetTotals, $primaryCurrency): void {
    if (!is_numeric($amount) || (float)$amount <= 0) {
        return;
    }
    $code = $currency !== '' ? $currency : $primaryCurrency;
    $budgetTotals[$code] = ($budgetTotals[$code] ?? 0.0) + (float)$amount;
};
if (is_numeric($budgetSource)) {
    $addBudget($budgetSource);
} elseif (is_array($budgetSource)) {
    foreach ($budgetSource as $key => $budget) {
        $keyCurrency = $extractCurrencyCode($key);
        if (is_numeric($budget)) {
            $addBudget($budget, $keyCurrency);
            continue;
        }
        if (!is_array($budget)) {
            continue;
        }
        $amount = $budget['amount'] ?? ($budget['budget'] ?? ($budget['limit'] ?? ($budget['value'] ?? null)));
        $currency = $extractCurrencyCode($budget) ?: $keyCurrency;
        $addBudget($amount, $currency);
        $addCurrency($currency);
    }
}
$currencies = array_keys($currencyCodes);
$departure = $parseLocalDateTime($trip['departureAt'] ?? null, $trip['departureTimeZoneId'] ?? null);
$arrival = $parseLocalDateTime($trip['arriveHomeAt'] ?? null, $trip['arriveHomeTimeZoneId'] ?? null);
$now = new \DateTimeImmutable('now');
$tripStatus = ['label' => $l->t('Date not specified'), 'detail' => ''];
if ($departure !== null && $now < $departure) {
    $days = max(0, (int)ceil(($departure->getTimestamp() - $now->getTimestamp()) / 86400));
    $tripStatus = [
        'label' => $l->t('Upcoming'),
        'detail' => $days === 0 ? $l->t('Starts today') : $l->t('Starts in %s days', [(string)$days]),
    ];
} elseif ($departure !== null && ($arrival === null || $now <= $arrival)) {
    $tripStatus = ['label' => $l->t('In progress'), 'detail' => $l->t('The trip is currently underway.')];
} elseif ($arrival !== null && $now > $arrival) {
    $tripStatus = ['label' => $l->t('Past'), 'detail' => $l->t('The trip has ended.')];
}
$shareNotices = [
    'saved' => ['success', $l->t('The share was saved.')],
    'removed' => ['success', $l->t('The share was removed.')],
    'invalid-user' => ['error', $l->t('Select a valid Nextcloud user.')],
    'invalid-role' => ['error', $l->t('Select a valid permission.')],
];
?>
<div id="teamtrip-app" class="teamtrip-wrap teamtrip-detail">
    <a class="teamtrip-back" href="<?php p($_['backUrl']); ?>">← <?php p($l->t('All trips')); ?></a>
    <header class="teamtrip-header teamtrip-detail-header">
        <div>
            <p class="teamtrip-eyebrow"><?php p($l->t('Trip details')); ?></p>
            <h1><?php p($_['title'] !== '' ? $_['title'] : $l->t('Untitled trip')); ?></h1>
            <p><?php p($formatDateTime($trip['departureAt'] ?? null)); ?> → <?php p($formatDateTime($trip['arriveHomeAt'] ?? null)); ?></p>
        </div>
        <span class="teamtrip-badge"><?php p($roleLabels[$_['role']] ?? $_['role']); ?></span>
    </header>

    <nav class="teamtrip-detail-nav" aria-label="<?php p($l->t('Trip sections')); ?>">
        <a href="#overview"><?php p($l->t('Overview')); ?></a>
        <a href="#activities"><?php p($l->t('Activities')); ?></a>
        <a href="#costs"><?php p($l->t('Costs')); ?></a>
        <a href="#travelers"><?php p($l->t('Travelers')); ?></a>
        <a href="#categories"><?php p($l->t('Categories')); ?></a>
        <a href="#payment-modes"><?php p($l->t('Payment modes')); ?></a>
        <a href="#currencies"><?php p($l->t('Currencies & budgets')); ?></a>
        <a href="#shares"><?php p($l->t('Shares')); ?></a>
    </nav>

    <section id="overview" class="teamtrip-panel teamtrip-overview-panel">
        <div>
            <h2><?php p($l->t('Overview')); ?></h2>
            <dl class="teamtrip-detail-list">
                <div><dt><?php p($l->t('Period')); ?></dt><dd><?php p($formatDateTime($trip['departureAt'] ?? null)); ?> → <?php p($formatDateTime($trip['arriveHomeAt'] ?? null)); ?></dd></div>
                <div><dt><?php p($l->t('Owner')); ?></dt><dd><?php p($_['owner']); ?></dd></div>
                <div><dt><?php p($l->t('Access')); ?></dt><dd><?php p($roleLabels[$_['role']] ?? $_['role']); ?></dd></div>
                <div><dt><?php p($l->t('Trip status')); ?></dt><dd><strong><?php p($tripStatus['label']); ?></strong><?php if ($tripStatus['detail'] !== ''): ?><small><?php p($tripStatus['detail']); ?></small><?php endif; ?></dd></div>
            </dl>
        </div>
        <div class="teamtrip-cost-summary">
            <span><?php p($l->t('Total cost')); ?></span>
            <?php if ($costTotals === []): ?>
                <strong>0.00<?php if ($primaryCurrency !== ''): ?> <?php p($primaryCurrency); ?><?php endif; ?></strong>
            <?php else: ?>
                <?php foreach ($costTotals as $currency => $amount): ?>
                    <strong><?php p(number_format($amount, 2, '.', '')); ?><?php if ($currency !== ''): ?> <?php p($currency); ?><?php endif; ?></strong>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

    <section class="teamtrip-stats" aria-label="<?php p($l->t('Trip summary')); ?>">
        <div><strong><?php p((string)count($_['items'])); ?></strong><span><?php p($l->t('Activities')); ?></span></div>
        <div><strong><?php p((string)count($travelers)); ?></strong><span><?php p($l->t('Travelers')); ?></span></div>
        <div><strong><?php p((string)count($categories)); ?></strong><span><?php p($l->t('Categories')); ?></span></div>
        <div><strong><?php p((string)$_['revision']); ?></strong><span><?php p($l->t('Revision')); ?></span></div>
    </section>

    <section id="activities" class="teamtrip-panel teamtrip-anchor-section">
        <h2><?php p($l->t('Activities')); ?></h2>
        <?php if (empty($_['items'])): ?>
            <p class="teamtrip-muted"><?php p($l->t('No activities stored.')); ?></p>
        <?php else: ?>
            <div class="teamtrip-activity-days">
                <?php foreach ($activityGroups as $activityGroup): ?>
                    <section class="teamtrip-activity-day">
                        <h3><span aria-hidden="true">▣</span><?php p($activityGroup['label']); ?></h3>
                        <div class="teamtrip-table-wrap"><table class="teamtrip-table">
                            <thead><tr><th><?php p($l->t('Time')); ?></th><th><?php p($l->t('Activity')); ?></th><th><?php p($l->t('Location')); ?></th><th><?php p($l->t('Cost')); ?></th></tr></thead>
                            <tbody>
                            <?php foreach ($activityGroup['items'] as $activity): ?>
                                <?php
                                $item = $activity['item'];
                                $type = $formatActivityType($item['typeRaw'] ?? '');
                                $activityTitle = $scalarText($item['title'] ?? '');
                                $activityNote = $scalarText($item['notes'] ?? ($item['note'] ?? ($item['details'] ?? '')));
                                $location = $scalarText($item['location'] ?? '');
                                $address = $scalarText($item['locationAddress'] ?? '');
                                $itemCurrency = $extractCurrencyCode($item['currencyCode'] ?? '');
                                if ($itemCurrency === '') {
                                    $itemCurrency = $extractCurrencyCode($item['currency'] ?? $primaryCurrency);
                                }
                                ?>
                                <tr>
                                    <td class="teamtrip-table-start">
                                        <span class="teamtrip-table-primary teamtrip-time-range"><?php p($formatTime($activity['start'])); ?><?php if ($activity['end'] !== null): ?>–<?php p($formatTime($activity['end'])); ?><?php endif; ?></span>
                                        <?php if (!empty($item['fromTimeZoneId'])): ?><div class="teamtrip-table-secondary"><?php p((string)$item['fromTimeZoneId']); ?></div><?php endif; ?>
                                        <?php if ($activity['duration'] !== ''): ?><div class="teamtrip-table-secondary"><?php p($l->t('Duration')); ?> · <?php p($activity['duration']); ?></div><?php endif; ?>
                                    </td>
                                    <td class="teamtrip-table-activity">
                                        <strong class="teamtrip-activity-title"><?php p($activityTitle !== '' ? $activityTitle : $type['label']); ?></strong>
                                        <div class="teamtrip-activity-type"><span aria-hidden="true"><?php p($type['icon']); ?></span><?php p($type['label']); ?></div>
                                        <?php if ($activityNote !== ''): ?><div class="teamtrip-table-secondary teamtrip-activity-note"><?php p($activityNote); ?></div><?php endif; ?>
                                    </td>
                                    <td class="teamtrip-table-location">
                                        <?php if ($location !== ''): ?><strong class="teamtrip-table-primary teamtrip-location-name"><?php p($location); ?></strong><?php endif; ?>
                                        <?php if ($address !== ''): ?><div class="teamtrip-table-secondary"><?php p($address); ?></div><?php endif; ?>
                                        <?php if ($location === '' && $address === ''): ?>—<?php endif; ?>
                                    </td>
                                    <td class="teamtrip-table-cost"><?php p(number_format((float)($item['cost'] ?? 0), 2, '.', '')); ?><?php if ($itemCurrency !== ''): ?> <small><?php p($itemCurrency); ?></small><?php endif; ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table></div>
                    </section>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section id="costs" class="teamtrip-panel teamtrip-anchor-section">
        <div class="teamtrip-section-title-row">
            <div>
                <h2><?php p($l->t('Cost summary')); ?></h2>
                <p class="teamtrip-muted"><?php p($l->t('Costs remain separated by currency.')); ?></p>
            </div>
            <span class="teamtrip-status-pill"><strong><?php p($tripStatus['label']); ?></strong><?php if ($tripStatus['detail'] !== ''): ?><small><?php p($tripStatus['detail']); ?></small><?php endif; ?></span>
        </div>
        <div class="teamtrip-cost-breakdown-grid">
            <article>
                <h3><?php p($l->t('By category')); ?></h3>
                <?php if ($categoryCosts === []): ?>
                    <p class="teamtrip-muted"><?php p($l->t('No category costs available.')); ?></p>
                <?php else: ?>
                    <ul class="teamtrip-breakdown-list">
                        <?php foreach ($categoryCosts as $categoryCost): ?>
                            <li><span class="teamtrip-entity-icon" aria-hidden="true"><?php p($categoryCost['icon']); ?></span><strong><?php p($categoryCost['name']); ?></strong><span><?php foreach ($categoryCost['totals'] as $currency => $amount): ?><b><?php p(number_format($amount, 2, '.', '')); ?><?php if ($currency !== ''): ?> <?php p($currency); ?><?php endif; ?></b><?php endforeach; ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </article>
            <article>
                <h3><?php p($l->t('By traveler')); ?></h3>
                <?php if ($travelerCosts === []): ?>
                    <p class="teamtrip-muted"><?php p($l->t('No costs are directly assigned to travelers.')); ?></p>
                <?php else: ?>
                    <ul class="teamtrip-breakdown-list">
                        <?php foreach ($travelerCosts as $travelerCost): ?>
                            <li><span class="teamtrip-entity-icon" aria-hidden="true">👤</span><strong><?php p($travelerCost['name']); ?></strong><span><?php foreach ($travelerCost['totals'] as $currency => $amount): ?><b><?php p(number_format($amount, 2, '.', '')); ?><?php if ($currency !== ''): ?> <?php p($currency); ?><?php endif; ?></b><?php endforeach; ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </article>
        </div>
        <?php if ($dataWarningCount > 0): ?>
            <aside class="teamtrip-data-notice">
                <strong><?php p($l->t('Check incomplete cost data')); ?></strong>
                <ul>
                    <?php if ($dataWarnings['missingCategory'] > 0): ?><li><?php p($l->t('%s activities with costs have no category.', [(string)$dataWarnings['missingCategory']])); ?></li><?php endif; ?>
                    <?php if ($dataWarnings['unknownCategory'] > 0): ?><li><?php p($l->t('%s category references could not be matched.', [(string)$dataWarnings['unknownCategory']])); ?></li><?php endif; ?>
                    <?php if ($dataWarnings['unknownTraveler'] > 0): ?><li><?php p($l->t('%s traveler references could not be matched.', [(string)$dataWarnings['unknownTraveler']])); ?></li><?php endif; ?>
                    <?php if ($dataWarnings['missingCurrency'] > 0): ?><li><?php p($l->t('%s activities with costs have no currency.', [(string)$dataWarnings['missingCurrency']])); ?></li><?php endif; ?>
                </ul>
            </aside>
        <?php endif; ?>
    </section>

    <div class="teamtrip-detail-grid teamtrip-compact-sections">
        <section id="travelers" class="teamtrip-panel teamtrip-anchor-section">
            <h2><?php p($l->t('Travelers')); ?></h2>
            <?php if ($travelers === []): ?>
                <p class="teamtrip-muted"><?php p($l->t('No travelers stored.')); ?></p>
            <?php else: ?>
                <ul class="teamtrip-people-list">
                    <?php foreach ($travelers as $traveler): ?>
                        <?php $travelerName = $entryName($traveler, ['name', 'title'], $l->t('Unnamed traveler')); $color = $entryColor($traveler); $userId = $linkedUser($traveler); $isLinked = $userId !== '' || $isOwnerTraveler($travelerName); ?>
                        <li>
                            <span class="teamtrip-person-avatar"<?php if ($color !== null): ?> style="--teamtrip-entity-color: <?php p($color); ?>"<?php endif; ?>>
                                <span aria-hidden="true">👤</span>
                                <?php if ($isLinked): ?><span class="teamtrip-linked-badge" title="<?php p($l->t('Linked Nextcloud user')); ?>" aria-label="<?php p($l->t('Linked Nextcloud user')); ?>">✓</span><?php endif; ?>
                            </span>
                            <strong><?php p($travelerName); ?></strong>
                            <?php if ($userId !== '' && mb_strtolower($userId, 'UTF-8') !== mb_strtolower($travelerName, 'UTF-8')): ?><small>@<?php p($userId); ?></small><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section id="categories" class="teamtrip-panel teamtrip-anchor-section teamtrip-categories-panel">
            <h2><?php p($l->t('Categories')); ?></h2>
            <?php if ($categories === []): ?>
                <p class="teamtrip-muted"><?php p($l->t('No categories stored.')); ?></p>
            <?php else: ?>
                <ul class="teamtrip-tile-list teamtrip-category-list">
                    <?php foreach ($categories as $category): ?>
                        <?php $categoryName = $entryName($category, ['name', 'title', 'label'], $l->t('Unnamed category')); $categoryIcon = $resolveEntityIcon($category, $categoryName, 'category'); $color = $entryColor($category); ?>
                        <li><span class="teamtrip-entity-icon"<?php if ($color !== null): ?> style="--teamtrip-entity-color: <?php p($color); ?>"<?php endif; ?> aria-hidden="true"><?php p($categoryIcon); ?></span><div><strong><?php p($categoryName); ?></strong></div></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section id="payment-modes" class="teamtrip-panel teamtrip-anchor-section">
            <h2><?php p($l->t('Payment modes')); ?></h2>
            <?php if ($paymentModes === []): ?>
                <p class="teamtrip-muted"><?php p($l->t('No payment modes stored.')); ?></p>
            <?php else: ?>
                <ul class="teamtrip-tile-list teamtrip-payment-list">
                    <?php foreach ($paymentModes as $paymentMode): ?>
                        <?php $modeName = $entryName($paymentMode, ['name', 'title', 'label'], $l->t('Unnamed payment mode')); $modeIcon = $resolveEntityIcon($paymentMode, $modeName, 'payment'); $color = $entryColor($paymentMode); ?>
                        <li><span class="teamtrip-entity-icon"<?php if ($color !== null): ?> style="--teamtrip-entity-color: <?php p($color); ?>"<?php endif; ?> aria-hidden="true"><?php p($modeIcon); ?></span><div><strong><?php p($modeName); ?></strong></div></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

    </div>

    <section id="currencies" class="teamtrip-panel teamtrip-anchor-section teamtrip-currency-panel">
        <h2><?php p($l->t('Currencies & budgets')); ?></h2>
        <?php if ($currencies === [] && $budgetTotals === []): ?>
            <p class="teamtrip-muted"><?php p($l->t('No currencies or budgets stored.')); ?></p>
        <?php else: ?>
            <ul class="teamtrip-currency-list">
                <?php foreach ($currencies as $currency): ?>
                    <li><span class="teamtrip-entity-icon teamtrip-currency-symbol" aria-hidden="true"><?php p($currencySymbol($currency)); ?></span><div><strong><?php p($currency); ?></strong><?php if (isset($budgetTotals[$currency])): ?><small><?php p($l->t('Budget')); ?> · <?php p(number_format($budgetTotals[$currency], 2, '.', '')); ?> <?php p($currency); ?></small><?php else: ?><small><?php p($l->t('No budget set.')); ?></small><?php endif; ?></div></li>
                <?php endforeach; ?>
                <?php if (isset($budgetTotals['']) && !in_array('', $currencies, true)): ?>
                    <li><span class="teamtrip-entity-icon teamtrip-currency-symbol" aria-hidden="true">💱</span><div><strong><?php p($l->t('Not specified')); ?></strong><small><?php p($l->t('Budget')); ?> · <?php p(number_format($budgetTotals[''], 2, '.', '')); ?></small></div></li>
                <?php endif; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section id="shares" class="teamtrip-panel teamtrip-anchor-section">
        <div class="teamtrip-section-title-row"><div><h2><?php p($l->t('Shares')); ?></h2><?php if ($_['role'] === 'owner'): ?><p class="teamtrip-muted"><?php p($l->t('Add Nextcloud users or change their permission without modifying the trip data.')); ?></p><?php endif; ?></div></div>
        <?php if ($_['shareStatus'] !== '' && isset($shareNotices[$_['shareStatus']])): ?>
            <p class="teamtrip-form-notice is-<?php p($shareNotices[$_['shareStatus']][0]); ?>" role="status"><?php p($shareNotices[$_['shareStatus']][1]); ?></p>
        <?php endif; ?>
        <div class="teamtrip-owner-row">
            <div class="teamtrip-share-identity">
                <span class="teamtrip-person-avatar teamtrip-share-avatar">
                    <span aria-hidden="true">👤</span>
                    <span class="teamtrip-linked-badge" title="<?php p($l->t('Linked Nextcloud user')); ?>" aria-label="<?php p($l->t('Linked Nextcloud user')); ?>">✓</span>
                </span>
                <div><strong><?php p($_['ownerDisplayName']); ?></strong><?php if ($_['ownerDisplayName'] !== $_['owner']): ?><small>@<?php p($_['owner']); ?></small><?php endif; ?></div>
            </div>
            <span class="teamtrip-badge"><?php p($roleLabels['owner']); ?></span>
        </div>
        <?php if ($_['shares'] === []): ?>
            <p class="teamtrip-muted teamtrip-share-empty"><?php p($l->t('This trip is not shared.')); ?></p>
        <?php else: ?>
            <ul class="teamtrip-entity-list teamtrip-share-list">
                <?php foreach ($_['shares'] as $share): ?>
                    <li>
                        <div class="teamtrip-share-identity">
                            <span class="teamtrip-person-avatar teamtrip-share-avatar">
                                <span aria-hidden="true">👤</span>
                                <span class="teamtrip-linked-badge" title="<?php p($l->t('Linked Nextcloud user')); ?>" aria-label="<?php p($l->t('Linked Nextcloud user')); ?>">✓</span>
                            </span>
                            <div><strong><?php p($share['displayName']); ?></strong><?php if ($share['displayName'] !== $share['userId']): ?><small>@<?php p($share['userId']); ?></small><?php endif; ?></div>
                        </div>
                        <?php if ($_['role'] === 'owner'): ?>
                            <div class="teamtrip-share-actions">
                                <form class="teamtrip-share-role-form" action="<?php p($_['shareApiUrl']); ?>" method="post" data-teamtrip-share-request="save" data-teamtrip-success-url="<?php p($_['shareSavedUrl']); ?>" data-teamtrip-error-message="<?php p($l->t('The share could not be updated. Please try again.')); ?>">
                                    <input type="hidden" name="shareUserId" value="<?php p($share['userId']); ?>">
                                    <label class="teamtrip-visually-hidden" for="share-role-<?php p(md5($share['userId'])); ?>"><?php p($l->t('Permission for %s', [$share['userId']])); ?></label>
                                    <select id="share-role-<?php p(md5($share['userId'])); ?>" name="shareRole">
                                        <option value="read"<?php if ($share['role'] === 'read'): ?> selected<?php endif; ?>><?php p($roleLabels['read']); ?></option>
                                        <option value="edit"<?php if ($share['role'] === 'edit'): ?> selected<?php endif; ?>><?php p($roleLabels['edit']); ?></option>
                                    </select>
                                    <button type="submit" class="teamtrip-small-button"><?php p($l->t('Save')); ?></button>
                                </form>
                                <form action="<?php p($share['removeUrl']); ?>" method="post" data-teamtrip-share-request="remove" data-teamtrip-success-url="<?php p($_['shareRemovedUrl']); ?>" data-teamtrip-confirm-remove="<?php p($l->t('Remove share for %s?', [$share['userId']])); ?>" data-teamtrip-error-message="<?php p($l->t('The share could not be updated. Please try again.')); ?>">
                                    <button type="submit" class="teamtrip-small-button teamtrip-danger-button"><?php p($l->t('Remove')); ?></button>
                                </form>
                            </div>
                        <?php else: ?>
                            <span class="teamtrip-badge"><?php p($roleLabels[$share['role']] ?? $share['role']); ?></span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php if ($_['role'] === 'owner'): ?>
            <form class="teamtrip-add-share-form" action="<?php p($_['shareApiUrl']); ?>" method="post" data-teamtrip-share-request="save" data-teamtrip-success-url="<?php p($_['shareSavedUrl']); ?>" data-teamtrip-error-message="<?php p($l->t('The share could not be updated. Please try again.')); ?>">
                <div>
                    <label for="teamtrip-share-user"><?php p($l->t('Nextcloud user')); ?></label>
                    <input id="teamtrip-share-user" name="shareUserId" type="text" list="teamtrip-share-users" required autocomplete="off" placeholder="<?php p($l->t('User ID')); ?>">
                    <datalist id="teamtrip-share-users">
                        <?php foreach ($_['shareUsers'] as $shareUser): ?><option value="<?php p($shareUser['userId']); ?>"><?php p($shareUser['displayName']); ?></option><?php endforeach; ?>
                    </datalist>
                </div>
                <div>
                    <label for="teamtrip-share-role"><?php p($l->t('Permission')); ?></label>
                    <select id="teamtrip-share-role" name="shareRole"><option value="read"><?php p($roleLabels['read']); ?></option><option value="edit"><?php p($roleLabels['edit']); ?></option></select>
                </div>
                <button type="submit"><?php p($l->t('Add user')); ?></button>
            </form>
        <?php endif; ?>
    </section>

    <details class="teamtrip-panel teamtrip-tech">
        <summary><?php p($l->t('Technical server information')); ?></summary>
        <dl>
            <div><dt><?php p($l->t('Trip UUID')); ?></dt><dd><code><?php p($_['uuid']); ?></code></dd></div>
            <div><dt><?php p($l->t('Revision')); ?></dt><dd><?php p((string)$_['revision']); ?></dd></div>
            <div><dt><?php p($l->t('Schema')); ?></dt><dd><?php p((string)($trip['schemaVersion'] ?? 'snapshot v1')); ?></dd></div>
            <div><dt><?php p($l->t('Last server update')); ?></dt><dd><?php p(date('c', $_['updatedAt'])); ?></dd></div>
        </dl>
    </details>
</div>
