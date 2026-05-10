<?php

namespace BBS\Services;

use BBS\Core\Database;

class VirtualStorageService
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public static function quotaBytesFromGb($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_numeric($value)) {
            throw new \InvalidArgumentException('Quota must be a number of GB.');
        }
        $gb = (float) $value;
        if ($gb <= 0) {
            return null;
        }
        return (int) round($gb * 1024 * 1024 * 1024);
    }

    public function getAssignableRepositories(): array
    {
        return $this->db->fetchAll("
            SELECT r.id, r.name, r.size_bytes, r.storage_type,
                   a.id AS agent_id, a.name AS agent_name
            FROM repositories r
            JOIN agents a ON a.id = r.agent_id
            ORDER BY a.name, r.name
        ");
    }

    public function listUsers(): array
    {
        return $this->db->fetchAll("
            SELECT id, username, email
            FROM users
            WHERE role != 'admin'
            ORDER BY username
        ");
    }

    public function getAll(): array
    {
        return $this->decorate($this->db->fetchAll("
            SELECT vs.*, u.username, u.email
            FROM virtual_storages vs
            JOIN users u ON u.id = vs.user_id
            ORDER BY vs.name
        "));
    }

    public function getForUser(int $userId): array
    {
        return $this->decorate($this->db->fetchAll("
            SELECT vs.*, u.username, u.email
            FROM virtual_storages vs
            JOIN users u ON u.id = vs.user_id
            WHERE vs.user_id = ?
            ORDER BY vs.name
        ", [$userId]));
    }

    public function getById(int $id): ?array
    {
        $rows = $this->decorate($this->db->fetchAll("
            SELECT vs.*, u.username, u.email
            FROM virtual_storages vs
            JOIN users u ON u.id = vs.user_id
            WHERE vs.id = ?
        ", [$id]));

        return $rows[0] ?? null;
    }

    public function create(string $name, int $userId, int $quotaBytes, array $repoIds, bool $strictMode = false): int
    {
        $this->db->getPdo()->beginTransaction();
        try {
            $id = $this->db->insert('virtual_storages', [
                'name' => $name,
                'user_id' => $userId,
                'quota_bytes' => $quotaBytes,
                'strict_mode' => $strictMode ? 1 : 0,
            ]);
            $this->replaceRepositories($id, $repoIds);
            $this->db->getPdo()->commit();
            return $id;
        } catch (\Throwable $e) {
            $this->db->getPdo()->rollBack();
            throw $e;
        }
    }

    public function update(int $id, string $name, int $userId, int $quotaBytes, array $repoIds, bool $strictMode = false): void
    {
        $this->db->getPdo()->beginTransaction();
        try {
            $this->db->update('virtual_storages', [
                'name' => $name,
                'user_id' => $userId,
                'quota_bytes' => $quotaBytes,
                'strict_mode' => $strictMode ? 1 : 0,
            ], 'id = ?', [$id]);
            $this->replaceRepositories($id, $repoIds);
            $this->db->getPdo()->commit();
        } catch (\Throwable $e) {
            $this->db->getPdo()->rollBack();
            throw $e;
        }
    }

    public function delete(int $id): void
    {
        $this->db->delete('virtual_storages', 'id = ?', [$id]);
    }

    public function getStrictQuotaBlockForRepository(int $repoId): ?array
    {
        $repo = $this->db->fetchOne("
            SELECT id, agent_id, name
            FROM repositories
            WHERE id = ?
        ", [$repoId]);

        if (!$repo) {
            return null;
        }

        $allocations = $this->db->fetchAll("
            SELECT vs.id, vs.name, vs.quota_bytes, vs.strict_mode, u.username,
                   COALESCE(SUM(r.size_bytes), 0) AS used_bytes
            FROM virtual_storage_repositories target
            JOIN virtual_storages vs ON vs.id = target.virtual_storage_id
            JOIN users u ON u.id = vs.user_id
            JOIN virtual_storage_repositories vsr ON vsr.virtual_storage_id = vs.id
            JOIN repositories r ON r.id = vsr.repository_id
            WHERE target.repository_id = ?
              AND vs.strict_mode = 1
            GROUP BY vs.id, vs.name, vs.quota_bytes, vs.strict_mode, u.username
            HAVING COALESCE(SUM(r.size_bytes), 0) > vs.quota_bytes
            ORDER BY COALESCE(SUM(r.size_bytes), 0) - vs.quota_bytes DESC
            LIMIT 1
        ", [$repoId]);

        if (empty($allocations)) {
            return null;
        }

        $allocation = $allocations[0];
        $used = (int) $allocation['used_bytes'];
        $quota = (int) $allocation['quota_bytes'];

        return [
            'repository_id' => (int) $repo['id'],
            'repository_name' => $repo['name'],
            'agent_id' => (int) $repo['agent_id'],
            'virtual_storage_id' => (int) $allocation['id'],
            'virtual_storage_name' => $allocation['name'],
            'strict_mode' => (int) $allocation['strict_mode'],
            'username' => $allocation['username'],
            'used_bytes' => $used,
            'quota_bytes' => $quota,
            'overage_bytes' => max(0, $used - $quota),
        ];
    }

    private function replaceRepositories(int $virtualStorageId, array $repoIds): void
    {
        $repoIds = array_values(array_unique(array_filter(array_map('intval', $repoIds))));
        $this->db->delete('virtual_storage_repositories', 'virtual_storage_id = ?', [$virtualStorageId]);
        foreach ($repoIds as $repoId) {
            $repo = $this->db->fetchOne("SELECT id FROM repositories WHERE id = ?", [$repoId]);
            if (!$repo) {
                continue;
            }
            $this->db->insert('virtual_storage_repositories', [
                'virtual_storage_id' => $virtualStorageId,
                'repository_id' => $repoId,
            ]);
        }
    }

    private function decorate(array $virtualStorages): array
    {
        foreach ($virtualStorages as &$vs) {
            $repos = $this->db->fetchAll("
                SELECT r.id, r.name, r.size_bytes, r.storage_type,
                       a.id AS agent_id, a.name AS agent_name
                FROM virtual_storage_repositories vsr
                JOIN repositories r ON r.id = vsr.repository_id
                JOIN agents a ON a.id = r.agent_id
                WHERE vsr.virtual_storage_id = ?
                ORDER BY a.name, r.name
            ", [$vs['id']]);

            $used = 0;
            $repoIds = [];
            foreach ($repos as $repo) {
                $used += (int) $repo['size_bytes'];
                $repoIds[] = (int) $repo['id'];
            }

            $quota = (int) $vs['quota_bytes'];
            $vs['id'] = (int) $vs['id'];
            $vs['user_id'] = (int) $vs['user_id'];
            $vs['quota_bytes'] = $quota;
            $vs['strict_mode'] = !empty($vs['strict_mode']);
            $vs['repositories'] = $repos;
            $vs['repository_ids'] = $repoIds;
            $vs['used_bytes'] = $used;
            $vs['free_bytes'] = max(0, $quota - $used);
            $vs['usage_percent'] = $quota > 0 ? min(100, round(($used / $quota) * 100, 1)) : 0;
        }
        unset($vs);

        return $virtualStorages;
    }
}
