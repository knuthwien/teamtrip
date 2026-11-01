<?php
/** @var array $_ */
$trip = $_['trip'];
$travelers = is_array($trip['travelers'] ?? null) ? $trip['travelers'] : [];
$categories = is_array($trip['categories'] ?? null) ? $trip['categories'] : [];
$budgets = is_array($trip['budgets'] ?? null) ? $trip['budgets'] : [];
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
    'train' => ['🚆', 'Train'],
    'bus' => ['🚌', 'Bus'],
    'car' => ['🚗', 'Car'],
    'taxi' => ['🚕', 'Taxi'],
    'ferry' => ['⛴️', 'Ferry'],
    'hotel' => ['🛏️', 'Accommodation'],
    'accommodation' => ['🛏️', 'Accommodation'],
    'breakfast' => ['☕', 'Breakfast'],
    'lunch' => ['🍽️', 'Lunch'],
    'dinner' => ['🍽️', 'Dinner'],
    'meal' => ['🍽️', 'Meal'],
    'restaurant' => ['🍽️', 'Meal'],
    'event' => ['🎟️', 'Event'],
    'sightseeing' => ['◎', 'Sightseeing'],
    'walk' => ['🚶', 'Walk'],
    'hike' => ['🥾', 'Hike'],
    'shopping' => ['🛍️', 'Shopping'],
    'other' => ['•', 'Other'],
];
$formatActivityType = static function ($value) use ($activityTypes, $l): array {
    $raw = trim((string)$value);
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
?>
<div id="teamtrip-app" class="teamtrip-wrap">
    <a class="teamtrip-back" href="<?php p($_['backUrl']); ?>">← <?php p($l->t('All trips')); ?></a>
    <header class="teamtrip-header teamtrip-detail-header">
        <div>
            <h1><?php p($_['title'] !== '' ? $_['title'] : $l->t('Untitled trip')); ?></h1>
            <p><?php p($formatDateTime($trip['departureAt'] ?? null)); ?> → <?php p($formatDateTime($trip['arriveHomeAt'] ?? null)); ?></p>
        </div>
        <span class="teamtrip-badge"><?php p($roleLabels[$_['role']] ?? $_['role']); ?></span>
    </header>

    <section class="teamtrip-stats">
        <div><strong><?php p((string)count($_['items'])); ?></strong><span><?php p($l->t('Activities')); ?></span></div>
        <div><strong><?php p((string)count($travelers)); ?></strong><span><?php p($l->t('Travelers')); ?></span></div>
        <div><strong><?php p((string)count($categories)); ?></strong><span><?php p($l->t('Categories')); ?></span></div>
        <div><strong><?php p((string)$_['revision']); ?></strong><span><?php p($l->t('Revision')); ?></span></div>
    </section>

    <section class="teamtrip-panel">
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
                    $activityTitle = trim((string)($item['title'] ?? ''));
                    $location = trim((string)($item['location'] ?? ''));
                    $address = trim((string)($item['locationAddress'] ?? ''));
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
                        <td class="teamtrip-table-cost"><?php p(number_format((float)($item['cost'] ?? 0), 2, '.', '')); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        <?php endif; ?>
    </section>

    <section class="teamtrip-two-col">
        <div class="teamtrip-panel">
            <h2><?php p($l->t('Travelers')); ?></h2>
            <ul class="teamtrip-clean-list">
                <?php foreach ($travelers as $traveler): ?>
                    <li><span><?php p((string)($traveler['name'] ?? '')); ?></span><code><?php p((string)($traveler['uuid'] ?? '')); ?></code></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="teamtrip-panel">
            <h2><?php p($l->t('Shares')); ?></h2>
            <p><strong><?php p($l->t('Owner:')); ?></strong> <?php p($_['owner']); ?></p>
            <ul class="teamtrip-clean-list">
                <?php foreach ($_['shares'] as $share): ?>
                    <li><span><?php p($share['userId']); ?></span><span class="teamtrip-badge"><?php p($roleLabels[$share['role']] ?? $share['role']); ?></span></li>
                <?php endforeach; ?>
            </ul>
        </div>
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
