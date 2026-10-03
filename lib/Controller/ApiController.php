<?php

declare(strict_types=1);

namespace OCA\TeamTrip\Controller;

use OCA\TeamTrip\Service\TripRepository;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\AppFramework\OCSController;
use OCP\AppFramework\Http;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\IUserSession;

class ApiController extends OCSController {
    public function __construct(
        string $appName,
        IRequest $request,
        private IUserSession $userSession,
        private IUserManager $userManager,
        private TripRepository $repository,
    ) {
        parent::__construct($appName, $request);
    }

    #[NoAdminRequired]
    public function ping(): DataResponse {
        return new DataResponse([
            'app' => 'teamtrip',
            'version' => '0.1.8',
            'userId' => $this->uid(),
            'apiVersion' => 1,
        ]);
    }

    #[NoAdminRequired]
    public function listTrips(): DataResponse {
        $rows = array_map(fn(array $row): array => $this->metadata($row), $this->repository->listAccessible($this->uid()));
        return new DataResponse(['trips' => $rows]);
    }

    #[NoAdminRequired]
    public function getTrip(string $uuid): DataResponse {
        $this->assertUuid($uuid);
        $role = $this->repository->roleFor($uuid, $this->uid());
        if ($role === null) {
            throw new OCSNotFoundException('Trip not found');
        }
        $row = $this->repository->find($uuid);
        if ($row === null) {
            throw new OCSNotFoundException('Trip not found');
        }
        return new DataResponse([
            'trip' => $this->metadata(array_merge($row, ['role' => $role])),
            'snapshot' => (string)$row['snapshot'],
        ]);
    }

    #[NoAdminRequired]
    public function putTrip(string $uuid, string $snapshot, int $baseRevision = 0, bool $force = false): DataResponse {
        $this->assertUuid($uuid);
        $decoded = $this->decodeSnapshot($snapshot);
        $summary = $this->summaryFromSnapshot($decoded);

        $existing = $this->repository->find($uuid);
        if ($existing === null) {
            if ($baseRevision !== 0) {
                throw new OCSBadRequestException('baseRevision must be 0 when creating a trip');
            }
            $row = $this->repository->create(
                $uuid,
                $this->uid(),
                $summary['title'],
                $summary['departureAt'],
                $summary['arrivalAt'],
                $snapshot,
            );
            $row['role'] = 'owner';
            return new DataResponse(['trip' => $this->metadata($row)], Http::STATUS_CREATED);
        }

        $role = $this->repository->roleFor($uuid, $this->uid());
        if ($role === null) {
            throw new OCSNotFoundException('Trip not found');
        }
        if (!in_array($role, ['owner', 'edit'], true)) {
            throw new OCSForbiddenException('This share is read-only');
        }

        $currentRevision = (int)$existing['revision'];
        if (!$force && $baseRevision !== $currentRevision) {
            return new DataResponse([
                'conflict' => true,
                'currentRevision' => $currentRevision,
                'trip' => $this->metadata(array_merge($existing, ['role' => $role])),
                'snapshot' => (string)$existing['snapshot'],
            ], Http::STATUS_CONFLICT);
        }

        $row = $this->repository->update(
            $uuid,
            $summary['title'],
            $summary['departureAt'],
            $summary['arrivalAt'],
            $snapshot,
            $currentRevision + 1,
        );
        $row['role'] = $role;
        return new DataResponse(['trip' => $this->metadata($row)]);
    }

    #[NoAdminRequired]
    public function deleteTrip(string $uuid): DataResponse {
        $this->assertUuid($uuid);
        $role = $this->repository->roleFor($uuid, $this->uid());
        if ($role !== 'owner') {
            throw new OCSForbiddenException('Only the owner can delete a trip');
        }
        $this->repository->softDelete($uuid);
        return new DataResponse(['deleted' => true]);
    }

    #[NoAdminRequired]
    public function listShares(string $uuid): DataResponse {
        $this->assertReadable($uuid);
        $trip = $this->repository->find($uuid);
        return new DataResponse([
            'ownerId' => (string)($trip['owner_uid'] ?? ''),
            'shares' => $this->repository->listShares($uuid),
        ]);
    }

    #[NoAdminRequired]
    public function addShare(string $uuid, string $userId, string $role = 'read'): DataResponse {
        $this->assertOwner($uuid);
        $userId = trim($userId);
        $role = strtolower(trim($role));
        if ($userId === '' || !$this->userManager->userExists($userId)) {
            throw new OCSBadRequestException('Unknown Nextcloud user');
        }
        if ($userId === $this->uid()) {
            throw new OCSBadRequestException('The owner cannot be added as a share');
        }
        if (!in_array($role, ['read', 'edit'], true)) {
            throw new OCSBadRequestException('Role must be read or edit');
        }
        $this->repository->upsertShare($uuid, $userId, $role);
        return new DataResponse(['shares' => $this->repository->listShares($uuid)], Http::STATUS_CREATED);
    }

    #[NoAdminRequired]
    public function removeShare(string $uuid, string $userId): DataResponse {
        $this->assertOwner($uuid);
        $this->repository->removeShare($uuid, $userId);
        return new DataResponse(['shares' => $this->repository->listShares($uuid)]);
    }

    private function uid(): string {
        $user = $this->userSession->getUser();
        if ($user === null) {
            throw new OCSForbiddenException('Authentication required');
        }
        return $user->getUID();
    }

    private function assertOwner(string $uuid): void {
        $this->assertUuid($uuid);
        if ($this->repository->roleFor($uuid, $this->uid()) !== 'owner') {
            throw new OCSForbiddenException('Only the owner can manage sharing');
        }
    }

    private function assertReadable(string $uuid): void {
        $this->assertUuid($uuid);
        if ($this->repository->roleFor($uuid, $this->uid()) === null) {
            throw new OCSNotFoundException('Trip not found');
        }
    }

    private function assertUuid(string $uuid): void {
        if (preg_match('/^[0-9a-fA-F-]{36}$/', $uuid) !== 1) {
            throw new OCSBadRequestException('Invalid trip UUID');
        }
    }

    /** @return array<string,mixed> */
    private function decodeSnapshot(string $snapshot): array {
        try {
            $decoded = json_decode($snapshot, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new OCSBadRequestException('Invalid snapshot JSON');
        }
        if (!is_array($decoded) || (int)($decoded['schemaVersion'] ?? 0) !== 1 || !is_array($decoded['trip'] ?? null)) {
            throw new OCSBadRequestException('Unsupported TeamTrip snapshot schema');
        }
        return $decoded;
    }

    /** @param array<string,mixed> $decoded
     *  @return array{title:string,departureAt:?string,arrivalAt:?string}
     */
    private function summaryFromSnapshot(array $decoded): array {
        $trip = $decoded['trip'];
        $title = trim((string)($trip['title'] ?? ''));
        return [
            'title' => mb_substr($title, 0, 255),
            'departureAt' => isset($trip['departureAt']) ? (string)$trip['departureAt'] : null,
            'arrivalAt' => isset($trip['arriveHomeAt']) ? (string)$trip['arriveHomeAt'] : null,
        ];
    }

    /** @param array<string,mixed> $row
     *  @return array<string,mixed>
     */
    private function metadata(array $row): array {
        return [
            'uuid' => (string)$row['uuid'],
            'title' => (string)$row['title'],
            'ownerId' => (string)$row['owner_uid'],
            'role' => (string)($row['role'] ?? ((string)$row['owner_uid'] === $this->uid() ? 'owner' : 'read')),
            'revision' => (int)$row['revision'],
            'departureAt' => $row['departure_at'] !== null ? (string)$row['departure_at'] : null,
            'arrivalAt' => $row['arrival_at'] !== null ? (string)$row['arrival_at'] : null,
            'createdAt' => (int)$row['created_at'],
            'updatedAt' => (int)$row['updated_at'],
        ];
    }
}
