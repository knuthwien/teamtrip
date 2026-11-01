<?php

declare(strict_types=1);

namespace OCA\TeamTrip\Controller;

use OCA\TeamTrip\Service\TripRepository;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\NotFoundResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Util;

class PageController extends Controller {
    public function __construct(
        string $appName,
        IRequest $request,
        private IUserSession $userSession,
        private IUserManager $userManager,
        private IURLGenerator $urlGenerator,
        private TripRepository $repository,
    ) {
        parent::__construct($appName, $request);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function index(): TemplateResponse {
        Util::addStyle('teamtrip', 'teamtrip-0200-r5');

        $uid = $this->userSession->getUser()?->getUID() ?? '';
        $scopeParam = $this->request->getParam('scope', 'all');
        $scope = is_scalar($scopeParam) ? strtolower(trim((string)$scopeParam)) : 'all';
        if (!in_array($scope, ['all', 'own', 'shared'], true)) {
            $scope = 'all';
        }
        $queryParam = $this->request->getParam('q', '');
        $query = is_scalar($queryParam) ? trim(mb_substr((string)$queryParam, 0, 100)) : '';
        $now = time();
        $counts = ['all' => 0, 'own' => 0, 'shared' => 0];
        $groups = ['upcoming' => [], 'past' => []];

        foreach ($this->repository->listAccessible($uid) as $row) {
            $role = (string)$row['role'];
            $isOwned = $role === 'owner';
            ++$counts['all'];
            ++$counts[$isOwned ? 'own' : 'shared'];

            if (($scope === 'own' && !$isOwned) || ($scope === 'shared' && $isOwned)) {
                continue;
            }

            $title = (string)$row['title'];
            if ($query !== '' && mb_stripos($title, $query) === false) {
                continue;
            }

            $snapshot = json_decode((string)$row['snapshot'], true);
            $tripSnapshot = is_array($snapshot) && is_array($snapshot['trip'] ?? null) ? $snapshot['trip'] : [];
            $items = is_array($tripSnapshot['items'] ?? null) ? $tripSnapshot['items'] : [];
            $arrivalAt = $row['arrival_at'] !== null ? (string)$row['arrival_at'] : null;
            $arrivalTimestamp = $arrivalAt !== null ? strtotime($arrivalAt) : false;
            $group = $arrivalTimestamp !== false && $arrivalTimestamp < $now ? 'past' : 'upcoming';

            $groups[$group][] = [
                'uuid' => (string)$row['uuid'],
                'title' => $title,
                'owner' => (string)$row['owner_uid'],
                'role' => $role,
                'revision' => (int)$row['revision'],
                'departureAt' => $row['departure_at'],
                'arrivalAt' => $arrivalAt,
                'updatedAt' => (int)$row['updated_at'],
                'activityCount' => count($items),
                'url' => $this->urlGenerator->linkToRoute('teamtrip.page.trip', ['uuid' => (string)$row['uuid']]),
            ];
        }

        usort($groups['upcoming'], static function (array $a, array $b): int {
            $aTime = is_string($a['departureAt']) ? strtotime($a['departureAt']) : false;
            $bTime = is_string($b['departureAt']) ? strtotime($b['departureAt']) : false;
            return ($aTime === false ? PHP_INT_MAX : $aTime) <=> ($bTime === false ? PHP_INT_MAX : $bTime);
        });
        usort($groups['past'], static function (array $a, array $b): int {
            $aTime = is_string($a['arrivalAt']) ? strtotime($a['arrivalAt']) : false;
            $bTime = is_string($b['arrivalAt']) ? strtotime($b['arrivalAt']) : false;
            return ($bTime === false ? 0 : $bTime) <=> ($aTime === false ? 0 : $aTime);
        });

        $indexUrl = $this->urlGenerator->linkToRoute('teamtrip.page.index');
        $scopeUrl = static function (string $targetScope) use ($indexUrl, $query): string {
            $params = [];
            if ($targetScope !== 'all') {
                $params['scope'] = $targetScope;
            }
            if ($query !== '') {
                $params['q'] = $query;
            }
            return $params === [] ? $indexUrl : $indexUrl . '?' . http_build_query($params);
        };
        return new TemplateResponse('teamtrip', 'main', [
            'groups' => $groups,
            'counts' => $counts,
            'scope' => $scope,
            'query' => $query,
            'resultCount' => count($groups['upcoming']) + count($groups['past']),
            'indexUrl' => $indexUrl,
            'scopeUrls' => [
                'all' => $scopeUrl('all'),
                'own' => $scopeUrl('own'),
                'shared' => $scopeUrl('shared'),
            ],
        ]);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function trip(string $uuid): TemplateResponse|NotFoundResponse {
        Util::addStyle('teamtrip', 'teamtrip-0200-r5');
        Util::addScript('teamtrip', 'teamtrip-0200');
        $uid = $this->userSession->getUser()?->getUID() ?? '';
        $role = $this->repository->roleFor($uuid, $uid);
        $row = $this->repository->find($uuid);
        if ($role === null || $row === null) {
            return new NotFoundResponse();
        }

        $snapshot = json_decode((string)$row['snapshot'], true);
        if (!is_array($snapshot)) {
            $snapshot = [];
        }
        $trip = is_array($snapshot['trip'] ?? null) ? $snapshot['trip'] : [];
        $items = is_array($trip['items'] ?? null) ? $trip['items'] : [];
        usort($items, static function(array $a, array $b): int {
            $ad = (string)($a['fromAt'] ?? '');
            $bd = (string)($b['fromAt'] ?? '');
            return $ad <=> $bd ?: ((int)($a['order'] ?? 0) <=> (int)($b['order'] ?? 0));
        });

        $shares = $this->repository->listShares($uuid);
        $shareUsers = [];
        if ($role === 'owner') {
            $sharedUserIds = array_fill_keys(array_column($shares, 'userId'), true);
            foreach ($this->userManager->searchDisplayName('', 100, 0) as $user) {
                $userId = $user->getUID();
                if ($userId === $uid || isset($sharedUserIds[$userId])) {
                    continue;
                }
                $shareUsers[] = [
                    'userId' => $userId,
                    'displayName' => $user->getDisplayName(),
                ];
            }
            usort($shareUsers, static fn(array $a, array $b): int => strcasecmp($a['displayName'], $b['displayName']));
        }

        $shareStatusParam = $this->request->getParam('shareStatus', '');
        $shareStatus = is_scalar($shareStatusParam) ? (string)$shareStatusParam : '';
        if (!in_array($shareStatus, ['saved', 'removed', 'invalid-user', 'invalid-role'], true)) {
            $shareStatus = '';
        }

        return new TemplateResponse('teamtrip', 'trip', [
            'uuid' => $uuid,
            'title' => (string)$row['title'],
            'owner' => (string)$row['owner_uid'],
            'role' => $role,
            'revision' => (int)$row['revision'],
            'updatedAt' => (int)$row['updated_at'],
            'trip' => $trip,
            'items' => $items,
            'shares' => $shares,
            'shareUsers' => $shareUsers,
            'shareStatus' => $shareStatus,
            'shareSaveUrl' => $this->urlGenerator->linkToRoute('teamtrip.page.upsertShare', ['uuid' => $uuid]),
            'shareRemoveUrl' => $this->urlGenerator->linkToRoute('teamtrip.page.removeShare', ['uuid' => $uuid]),
            'backUrl' => $this->urlGenerator->linkToRoute('teamtrip.page.index'),
        ]);
    }

    #[NoAdminRequired]
    public function upsertShare(string $uuid): RedirectResponse|NotFoundResponse {
        $uid = $this->userSession->getUser()?->getUID() ?? '';
        if ($this->repository->roleFor($uuid, $uid) !== 'owner') {
            return new NotFoundResponse();
        }

        $userParam = $this->request->getParam('shareUserId', '');
        $roleParam = $this->request->getParam('shareRole', 'read');
        $userId = is_scalar($userParam) ? trim((string)$userParam) : '';
        $role = is_scalar($roleParam) ? strtolower(trim((string)$roleParam)) : '';
        if ($userId === '' || $userId === $uid || !$this->userManager->userExists($userId)) {
            return $this->shareRedirect($uuid, 'invalid-user');
        }
        if (!in_array($role, ['read', 'edit'], true)) {
            return $this->shareRedirect($uuid, 'invalid-role');
        }

        $this->repository->upsertShare($uuid, $userId, $role);
        return $this->shareRedirect($uuid, 'saved');
    }

    #[NoAdminRequired]
    public function removeShare(string $uuid): RedirectResponse|NotFoundResponse {
        $uid = $this->userSession->getUser()?->getUID() ?? '';
        if ($this->repository->roleFor($uuid, $uid) !== 'owner') {
            return new NotFoundResponse();
        }

        $userParam = $this->request->getParam('shareUserId', '');
        $userId = is_scalar($userParam) ? trim((string)$userParam) : '';
        if ($userId === '' || $userId === $uid) {
            return $this->shareRedirect($uuid, 'invalid-user');
        }

        $this->repository->removeShare($uuid, $userId);
        return $this->shareRedirect($uuid, 'removed');
    }

    private function shareRedirect(string $uuid, string $status): RedirectResponse {
        $url = $this->urlGenerator->linkToRoute('teamtrip.page.trip', ['uuid' => $uuid]);
        return new RedirectResponse($url . '?' . http_build_query(['shareStatus' => $status]) . '#shares');
    }
}
