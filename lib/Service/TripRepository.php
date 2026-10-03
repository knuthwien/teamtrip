<?php

declare(strict_types=1);

namespace OCA\TeamTrip\Service;

use OCP\IDBConnection;

class TripRepository {
    public function __construct(private IDBConnection $db) {
    }

    /** @return array<string,mixed>|null */
    public function find(string $uuid): ?array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from('teamtrip_trips')
            ->where($qb->expr()->eq('uuid', $qb->createNamedParameter($uuid)))
            ->setMaxResults(1);
        $row = $qb->executeQuery()->fetchAssociative();
        return $row === false ? null : $row;
    }

    /** @return array<int,array<string,mixed>> */
    public function listAccessible(string $userId): array {
        $result = [];

        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from('teamtrip_trips')
            ->where($qb->expr()->eq('owner_uid', $qb->createNamedParameter($userId)))
            ->andWhere($qb->expr()->isNull('deleted_at'))
            ->orderBy('updated_at', 'DESC');
        foreach ($qb->executeQuery()->fetchAllAssociative() as $row) {
            $row['role'] = 'owner';
            $result[(string)$row['uuid']] = $row;
        }

        $shareQb = $this->db->getQueryBuilder();
        $shareQb->select('trip_uuid', 'role')
            ->from('teamtrip_shares')
            ->where($shareQb->expr()->eq('user_uid', $shareQb->createNamedParameter($userId)));
        foreach ($shareQb->executeQuery()->fetchAllAssociative() as $share) {
            $uuid = (string)$share['trip_uuid'];
            if (isset($result[$uuid])) {
                continue;
            }
            $trip = $this->find($uuid);
            if ($trip !== null && $trip['deleted_at'] === null) {
                $trip['role'] = (string)$share['role'];
                $result[$uuid] = $trip;
            }
        }

        uasort($result, static fn(array $a, array $b): int => ((int)$b['updated_at']) <=> ((int)$a['updated_at']));
        return array_values($result);
    }

    public function roleFor(string $uuid, string $userId): ?string {
        $trip = $this->find($uuid);
        if ($trip === null || $trip['deleted_at'] !== null) {
            return null;
        }
        if ((string)$trip['owner_uid'] === $userId) {
            return 'owner';
        }

        $qb = $this->db->getQueryBuilder();
        $qb->select('role')
            ->from('teamtrip_shares')
            ->where($qb->expr()->eq('trip_uuid', $qb->createNamedParameter($uuid)))
            ->andWhere($qb->expr()->eq('user_uid', $qb->createNamedParameter($userId)))
            ->setMaxResults(1);
        $role = $qb->executeQuery()->fetchOne();
        return $role === false ? null : (string)$role;
    }

    /** @return array<string,mixed> */
    public function create(string $uuid, string $ownerUid, string $title, ?string $departureAt, ?string $arrivalAt, string $snapshot): array {
        $now = time();
        $qb = $this->db->getQueryBuilder();
        $qb->insert('teamtrip_trips')
            ->values([
                'uuid' => $qb->createNamedParameter($uuid),
                'owner_uid' => $qb->createNamedParameter($ownerUid),
                'title' => $qb->createNamedParameter($title),
                'departure_at' => $qb->createNamedParameter($departureAt),
                'arrival_at' => $qb->createNamedParameter($arrivalAt),
                'revision' => $qb->createNamedParameter(1),
                'snapshot' => $qb->createNamedParameter($snapshot),
                'created_at' => $qb->createNamedParameter($now),
                'updated_at' => $qb->createNamedParameter($now),
                'deleted_at' => $qb->createNamedParameter(null),
            ]);
        $qb->executeStatement();
        return $this->find($uuid) ?? [];
    }

    /** @return array<string,mixed> */
    public function update(string $uuid, string $title, ?string $departureAt, ?string $arrivalAt, string $snapshot, int $nextRevision): array {
        $qb = $this->db->getQueryBuilder();
        $qb->update('teamtrip_trips')
            ->set('title', $qb->createNamedParameter($title))
            ->set('departure_at', $qb->createNamedParameter($departureAt))
            ->set('arrival_at', $qb->createNamedParameter($arrivalAt))
            ->set('snapshot', $qb->createNamedParameter($snapshot))
            ->set('revision', $qb->createNamedParameter($nextRevision))
            ->set('updated_at', $qb->createNamedParameter(time()))
            ->set('deleted_at', $qb->createNamedParameter(null))
            ->where($qb->expr()->eq('uuid', $qb->createNamedParameter($uuid)));
        $qb->executeStatement();
        return $this->find($uuid) ?? [];
    }

    public function softDelete(string $uuid): void {
        $qb = $this->db->getQueryBuilder();
        $qb->update('teamtrip_trips')
            ->set('deleted_at', $qb->createNamedParameter(time()))
            ->set('updated_at', $qb->createNamedParameter(time()))
            ->where($qb->expr()->eq('uuid', $qb->createNamedParameter($uuid)));
        $qb->executeStatement();
    }

    /** @return array<int,array{userId:string,role:string,createdAt:int}> */
    public function listShares(string $uuid): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('user_uid', 'role', 'created_at')
            ->from('teamtrip_shares')
            ->where($qb->expr()->eq('trip_uuid', $qb->createNamedParameter($uuid)))
            ->orderBy('user_uid', 'ASC');
        $rows = [];
        foreach ($qb->executeQuery()->fetchAllAssociative() as $row) {
            $rows[] = [
                'userId' => (string)$row['user_uid'],
                'role' => (string)$row['role'],
                'createdAt' => (int)$row['created_at'],
            ];
        }
        return $rows;
    }

    public function upsertShare(string $uuid, string $userId, string $role): void {
        $delete = $this->db->getQueryBuilder();
        $delete->delete('teamtrip_shares')
            ->where($delete->expr()->eq('trip_uuid', $delete->createNamedParameter($uuid)))
            ->andWhere($delete->expr()->eq('user_uid', $delete->createNamedParameter($userId)));
        $delete->executeStatement();

        $insert = $this->db->getQueryBuilder();
        $insert->insert('teamtrip_shares')
            ->values([
                'trip_uuid' => $insert->createNamedParameter($uuid),
                'user_uid' => $insert->createNamedParameter($userId),
                'role' => $insert->createNamedParameter($role),
                'created_at' => $insert->createNamedParameter(time()),
            ]);
        $insert->executeStatement();
    }

    public function removeShare(string $uuid, string $userId): void {
        $qb = $this->db->getQueryBuilder();
        $qb->delete('teamtrip_shares')
            ->where($qb->expr()->eq('trip_uuid', $qb->createNamedParameter($uuid)))
            ->andWhere($qb->expr()->eq('user_uid', $qb->createNamedParameter($userId)));
        $qb->executeStatement();
    }
}
