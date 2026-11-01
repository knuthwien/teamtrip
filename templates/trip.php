<?php
/** @var array $_ */
$trip = $_['trip'];
$travelers = is_array($trip['travelers'] ?? null) ? array_values($trip['travelers']) : [];
$categories = is_array($trip['categories'] ?? null) ? array_values($trip['categories']) : [];
$paymentModes = is_array($trip['paymentModes'] ?? null)
    ? array_values($trip['paymentModes'])
    : (is_array($trip['paymentMethods'] ?? null) ? array_values($trip['paymentMethods']) : []);
$budgets = is_array($trip['budgets'] ?? null) ? array_values($trip['budgets']) : [];
$currencies = is_array($trip['currencies'] ?? null) ? array_values($trip['currencies']) : $budgets;
$scalarText = static function ($value): string {
    return is_scalar($value) ? trim((string)$value) : '';
};
$primaryCurrency = $scalarText($trip['currencyCode'] ?? $trip['currency'] ?? '');
if ($currencies === [] && $primaryCurrency !== '') {
    $currencies[] = ['currencyCode' => $primaryCurrency];
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
    foreach (['icon', 'emoji', 'symbol'] as $key) {
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
$costTotals = [];
foreach ($_['items'] as $item) {
    $currency = $scalarText($item['currencyCode'] ?? $item['currency'] ?? $primaryCurrency);
    $costTotals[$currency] = ($costTotals[$currency] ?? 0.0) + (float)($item['cost'] ?? 0);
}
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
            <div class="teamtrip-table-wrap"><table class="teamtrip-table">
                <thead><tr><th><?php p($l->t('Start')); ?></th><th><?php p($l->t('Activity')); ?></th><th><?php p($l->t('Location')); ?></th><th><?php p($l->t('Cost')); ?></th></tr></thead>
                <tbody>
                <?php foreach ($_['items'] as $item): ?>
                    <?php
                    $type = $formatActivityType($item['typeRaw'] ?? '');
                    $activityTitle = $scalarText($item['title'] ?? '');
                    $location = $scalarText($item['location'] ?? '');
                    $address = $scalarText($item['locationAddress'] ?? '');
                    $itemCurrency = $scalarText($item['currencyCode'] ?? $item['currency'] ?? $primaryCurrency);
                    ?>
                    <tr>
                        <td class="teamtrip-table-start">
                            <span class="teamtrip-table-primary"><?php p($formatDateTime($item['fromAt'] ?? null)); ?></span>
                            <?php if (!empty($item['fromTimeZoneId'])): ?><small><?php p((string)$item['fromTimeZoneId']); ?></small><?php endif; ?>
                        </td>
                        <td class="teamtrip-table-activity">
                            <strong class="teamtrip-activity-title"><?php p($activityTitle !== '' ? $activityTitle : $type['label']); ?></strong>
                            <span class="teamtrip-activity-type"><span aria-hidden="true"><?php p($type['icon']); ?></span><?php p($type['label']); ?></span>
                        </td>
                        <td class="teamtrip-table-location">
                            <?php if ($location !== ''): ?><span class="teamtrip-table-primary"><?php p($location); ?></span><?php endif; ?>
                            <?php if ($address !== ''): ?><small><?php p($address); ?></small><?php endif; ?>
                            <?php if ($location === '' && $address === ''): ?>—<?php endif; ?>
                        </td>
                        <td class="teamtrip-table-cost"><?php p(number_format((float)($item['cost'] ?? 0), 2, '.', '')); ?><?php if ($itemCurrency !== ''): ?> <small><?php p($itemCurrency); ?></small><?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        <?php endif; ?>
    </section>

    <div class="teamtrip-detail-grid">
        <section id="travelers" class="teamtrip-panel teamtrip-anchor-section">
            <h2><?php p($l->t('Travelers')); ?></h2>
            <?php if ($travelers === []): ?>
                <p class="teamtrip-muted"><?php p($l->t('No travelers stored.')); ?></p>
            <?php else: ?>
                <ul class="teamtrip-entity-list">
                    <?php foreach ($travelers as $traveler): ?>
                        <?php $travelerName = $entryName($traveler, ['name', 'title'], $l->t('Unnamed traveler')); $color = $entryColor($traveler); $userId = $linkedUser($traveler); ?>
                        <li>
                            <span class="teamtrip-entity-mark"<?php if ($color !== null): ?> style="--teamtrip-entity-color: <?php p($color); ?>"<?php endif; ?> aria-hidden="true"></span>
                            <div><strong><?php p($travelerName); ?></strong><?php if ($userId !== ''): ?><small><?php p($l->t('Linked Nextcloud user')); ?> · <?php p($userId); ?></small><?php endif; ?></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section id="categories" class="teamtrip-panel teamtrip-anchor-section">
            <h2><?php p($l->t('Categories')); ?></h2>
            <?php if ($categories === []): ?>
                <p class="teamtrip-muted"><?php p($l->t('No categories stored.')); ?></p>
            <?php else: ?>
                <ul class="teamtrip-entity-list">
                    <?php foreach ($categories as $category): ?>
                        <?php $categoryName = $entryName($category, ['name', 'title', 'label'], $l->t('Unnamed category')); $categoryIcon = $entryIcon($category, '•'); $color = $entryColor($category); ?>
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
                <ul class="teamtrip-entity-list">
                    <?php foreach ($paymentModes as $paymentMode): ?>
                        <?php $modeName = $entryName($paymentMode, ['name', 'title', 'label'], $l->t('Unnamed payment mode')); $modeIcon = $entryIcon($paymentMode, '•'); $color = $entryColor($paymentMode); ?>
                        <li><span class="teamtrip-entity-icon"<?php if ($color !== null): ?> style="--teamtrip-entity-color: <?php p($color); ?>"<?php endif; ?> aria-hidden="true"><?php p($modeIcon); ?></span><div><strong><?php p($modeName); ?></strong></div></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section id="currencies" class="teamtrip-panel teamtrip-anchor-section">
            <h2><?php p($l->t('Currencies & budgets')); ?></h2>
            <?php if ($currencies === []): ?>
                <p class="teamtrip-muted"><?php p($l->t('No currencies or budgets stored.')); ?></p>
            <?php else: ?>
                <ul class="teamtrip-entity-list">
                    <?php foreach ($currencies as $currency): ?>
                        <?php
                        $currencyName = $entryName($currency, ['currencyCode', 'code', 'currency', 'name', 'title'], $l->t('Not specified'));
                        $budgetAmount = is_array($currency) ? ($currency['amount'] ?? $currency['budget'] ?? $currency['limit'] ?? null) : null;
                        ?>
                        <li><span class="teamtrip-entity-icon" aria-hidden="true">¤</span><div><strong><?php p($currencyName); ?></strong><?php if (is_numeric($budgetAmount)): ?><small><?php p($l->t('Budget')); ?> · <?php p(number_format((float)$budgetAmount, 2, '.', '')); ?></small><?php endif; ?></div></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>

    <section id="shares" class="teamtrip-panel teamtrip-anchor-section">
        <h2><?php p($l->t('Shares')); ?></h2>
        <div class="teamtrip-owner-row"><span><?php p($l->t('Owner')); ?></span><strong><?php p($_['owner']); ?></strong><span class="teamtrip-badge"><?php p($roleLabels['owner']); ?></span></div>
        <?php if ($_['shares'] === []): ?>
            <p class="teamtrip-muted teamtrip-share-empty"><?php p($l->t('This trip is not shared.')); ?></p>
        <?php else: ?>
            <ul class="teamtrip-entity-list teamtrip-share-list">
                <?php foreach ($_['shares'] as $share): ?>
                    <li><span class="teamtrip-entity-icon" aria-hidden="true">👤</span><div><strong><?php p($share['userId']); ?></strong></div><span class="teamtrip-badge"><?php p($roleLabels[$share['role']] ?? $share['role']); ?></span></li>
                <?php endforeach; ?>
            </ul>
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
