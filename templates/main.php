<?php
/** @var array $_ */
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
?>
<div id="teamtrip-app" class="teamtrip-wrap">
    <header class="teamtrip-header">
        <div>
            <h1>TeamTrip</h1>
            <p><?php p($l->t('Server status for synchronized and shared trips.')); ?></p>
        </div>
    </header>

    <?php if (empty($_['trips'])): ?>
        <div class="teamtrip-empty">
            <h2><?php p($l->t('No synchronized trips yet')); ?></h2>
            <p><?php p($l->t('Upload a trip from the TeamTrip iOS app. It will appear here immediately.')); ?></p>
        </div>
    <?php else: ?>
        <div class="teamtrip-grid">
            <?php foreach ($_['trips'] as $trip): ?>
                <a class="teamtrip-card" href="<?php p($trip['url']); ?>">
                    <div class="teamtrip-card-top">
                        <h2><?php p($trip['title'] !== '' ? $trip['title'] : $l->t('Untitled trip')); ?></h2>
                        <span class="teamtrip-badge"><?php p($roleLabels[$trip['role']] ?? $trip['role']); ?></span>
                    </div>
                    <div class="teamtrip-meta">
                        <div class="teamtrip-meta-row"><span><?php p($l->t('Period')); ?></span><strong><?php p($formatDateTime($trip['departureAt'] ?? null) . ' → ' . $formatDateTime($trip['arrivalAt'] ?? null)); ?></strong></div>
                        <div class="teamtrip-meta-row"><span><?php p($l->t('Owner')); ?></span><strong><?php p($trip['owner']); ?></strong></div>
                        <div class="teamtrip-meta-row"><span><?php p($l->t('Revision')); ?></span><strong><?php p((string)$trip['revision']); ?></strong></div>
                        <div class="teamtrip-meta-row"><span><?php p($l->t('Server update')); ?></span><strong><?php p(date('d.m.Y, H:i', $trip['updatedAt'])); ?></strong></div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
