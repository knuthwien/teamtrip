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
$hasFilters = $_['scope'] !== 'all' || $_['query'] !== '';
?>
<div id="teamtrip-app" class="teamtrip-wrap teamtrip-overview">
    <header class="teamtrip-header teamtrip-overview-header">
        <div>
            <p class="teamtrip-eyebrow"><?php p($l->t('Trip overview')); ?></p>
            <h1>TeamTrip</h1>
            <p><?php p($l->t('Server status for synchronized and shared trips.')); ?></p>
        </div>
    </header>

    <section class="teamtrip-toolbar" aria-label="<?php p($l->t('Trip overview filters')); ?>">
        <form class="teamtrip-search" action="<?php p($_['indexUrl']); ?>" method="get" role="search">
            <?php if ($_['scope'] !== 'all'): ?>
                <input type="hidden" name="scope" value="<?php p($_['scope']); ?>">
            <?php endif; ?>
            <label class="teamtrip-visually-hidden" for="teamtrip-query"><?php p($l->t('Find a trip')); ?></label>
            <input id="teamtrip-query" name="q" type="search" value="<?php p($_['query']); ?>" placeholder="<?php p($l->t('Find a trip')); ?>" autocomplete="off">
            <button type="submit"><?php p($l->t('Search')); ?></button>
        </form>

        <nav class="teamtrip-scope" aria-label="<?php p($l->t('Trip overview filters')); ?>">
            <?php foreach (['all' => $l->t('All'), 'own' => $l->t('Own'), 'shared' => $l->t('Shared')] as $scope => $label): ?>
                <a href="<?php p($_['scopeUrls'][$scope]); ?>"
                   class="<?php p($_['scope'] === $scope ? 'is-active' : ''); ?>"
                   <?php if ($_['scope'] === $scope): ?>aria-current="page"<?php endif; ?>>
                    <span><?php p($label); ?></span>
                    <strong><?php p((string)$_['counts'][$scope]); ?></strong>
                </a>
            <?php endforeach; ?>
        </nav>
    </section>

    <?php if ($_['resultCount'] === 0): ?>
        <div class="teamtrip-empty">
            <h2><?php p($l->t($hasFilters ? 'No matching trips' : 'No synchronized trips yet')); ?></h2>
            <p><?php p($l->t($hasFilters ? 'Adjust your search or select another filter.' : 'Upload a trip from the TeamTrip iOS app. It will appear here immediately.')); ?></p>
            <?php if ($hasFilters): ?>
                <a class="teamtrip-secondary-action" href="<?php p($_['indexUrl']); ?>"><?php p($l->t('Clear filters')); ?></a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <?php foreach (['upcoming' => $l->t('Upcoming trips'), 'past' => $l->t('Past trips')] as $group => $heading): ?>
            <?php if (!empty($_['groups'][$group])): ?>
                <section class="teamtrip-section" aria-labelledby="teamtrip-<?php p($group); ?>">
                    <div class="teamtrip-section-heading">
                        <h2 id="teamtrip-<?php p($group); ?>"><?php p($heading); ?></h2>
                        <span><?php p((string)count($_['groups'][$group])); ?></span>
                    </div>
                    <div class="teamtrip-grid">
                        <?php foreach ($_['groups'][$group] as $trip): ?>
                            <a class="teamtrip-card" href="<?php p($trip['url']); ?>" aria-label="<?php p($l->t('Open trip %s', [$trip['title']])); ?>">
                                <div class="teamtrip-card-top">
                                    <div>
                                        <p class="teamtrip-card-kicker"><?php p($group === 'past' ? $l->t('Past') : $l->t('Upcoming')); ?></p>
                                        <h3><?php p($trip['title'] !== '' ? $trip['title'] : $l->t('Untitled trip')); ?></h3>
                                    </div>
                                    <span class="teamtrip-badge"><?php p($roleLabels[$trip['role']] ?? $trip['role']); ?></span>
                                </div>

                                <p class="teamtrip-card-period">
                                    <span><?php p($formatDateTime($trip['departureAt'] ?? null)); ?></span>
                                    <span aria-hidden="true">→</span>
                                    <span><?php p($formatDateTime($trip['arrivalAt'] ?? null)); ?></span>
                                </p>

                                <div class="teamtrip-card-facts">
                                    <div><strong><?php p((string)$trip['activityCount']); ?></strong><span><?php p($l->t('Activities')); ?></span></div>
                                    <div><strong><?php p((string)$trip['revision']); ?></strong><span><?php p($l->t('Revision')); ?></span></div>
                                </div>

                                <div class="teamtrip-card-footer">
                                    <span><strong><?php p($l->t('Owner')); ?>:</strong> <?php p($trip['owner']); ?></span>
                                    <span class="teamtrip-card-open" aria-hidden="true">›</span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
