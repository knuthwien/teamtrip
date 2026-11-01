<?php

declare(strict_types=1);

namespace OCA\TeamTrip\Controller;

use OCA\TeamTrip\Service\TripRepository;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\NotFoundResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\Util;

class PageController extends Controller {
    public function __construct(
        string $appName,
        IRequest $request,
        private IUserSession $userSession,
        private IURLGenerator $urlGenerator,
        private TripRepository $repository,
    ) {
        parent::__construct($appName, $request);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function index(): TemplateResponse {
        Util::addStyle('teamtrip', 'teamtrip-0200-r4');

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
        Util::addStyle('teamtrip', 'teamtrip-0200-r4');
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

        return new TemplateResponse('teamtrip', 'trip', [
            'uuid' => $uuid,
            'title' => (string)$row['title'],
            'owner' => (string)$row['owner_uid'],
            'role' => $role,
            'revision' => (int)$row['revision'],
            'updatedAt' => (int)$row['updated_at'],
            'trip' => $trip,
            'items' => $items,
            'shares' => $this->repository->listShares($uuid),
            'backUrl' => $this->urlGenerator->linkToRoute('teamtrip.page.index'),
        ]);
    }
}
