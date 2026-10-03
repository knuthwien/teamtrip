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
        Util::addStyle('teamtrip', 'style');
        $uid = $this->userSession->getUser()?->getUID() ?? '';
        $trips = [];
        foreach ($this->repository->listAccessible($uid) as $row) {
            $trips[] = [
                'uuid' => (string)$row['uuid'],
                'title' => (string)$row['title'],
                'owner' => (string)$row['owner_uid'],
                'role' => (string)$row['role'],
                'revision' => (int)$row['revision'],
                'departureAt' => $row['departure_at'],
                'arrivalAt' => $row['arrival_at'],
                'updatedAt' => (int)$row['updated_at'],
                'url' => $this->urlGenerator->linkToRoute('teamtrip.page.trip', ['uuid' => (string)$row['uuid']]),
            ];
        }
        return new TemplateResponse('teamtrip', 'main', ['trips' => $trips, 'uid' => $uid]);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function trip(string $uuid): TemplateResponse|NotFoundResponse {
        Util::addStyle('teamtrip', 'style');
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
