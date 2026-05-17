<?php

namespace BBS\Controllers;

use BBS\Core\Controller;
use BBS\Services\PermissionService;
use BBS\Services\TwoFactorService;

class UserController extends Controller
{
    public function index(): void
    {
        $this->requireAdmin();

        $users = $this->db->fetchAll("
            SELECT u.*,
                   (SELECT COUNT(*) FROM user_agents ua WHERE ua.user_id = u.id) as agent_count
            FROM users u
            ORDER BY u.id
        ");

        $this->view('users/index', [
            'pageTitle' => 'User Management',
            'users' => $users,
        ]);
    }

    public function store(): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();

        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'user';

        if (empty($username) || empty($email) || empty($password)) {
            $this->flash('danger', 'All fields are required.');
            $this->redirect('/users');
        }

        $existing = $this->db->fetchOne("SELECT id FROM users WHERE username = ? OR email = ?", [$username, $email]);
        if ($existing) {
            $this->flash('danger', 'Username or email already exists.');
            $this->redirect('/users');
        }

        $userId = $this->db->insert('users', [
            'username' => $username,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_BCRYPT),
            'role' => in_array($role, ['admin', 'user']) ? $role : 'user',
        ]);

        $this->flash('success', "User \"{$username}\" created.");
        $this->redirect("/users/{$userId}/edit");
    }

    public function edit(int $id): void
    {
        $this->requireAdmin();

        $user = $this->db->fetchOne("SELECT * FROM users WHERE id = ?", [$id]);
        if (!$user) {
            $this->flash('danger', 'User not found.');
            $this->redirect('/users');
        }

        $permService = new PermissionService();
        $userPermissions = $permService->getUserPermissions($id);
        $ownedAgents = $this->db->fetchAll("
            SELECT a.id, a.name, a.user_id, u.username AS owner_name
            FROM agents a
            LEFT JOIN users u ON u.id = a.user_id
            WHERE a.user_id = ?
            ORDER BY a.name
        ", [$id]);
        $permissionData = [];
        foreach ($userPermissions as $up) {
            if ($up['agent_id'] !== null) {
                $permissionData[(int) $up['agent_id']][$up['permission']] = true;
            }
        }

        $this->view('users/edit', [
            'pageTitle' => 'Edit User',
            'user' => $user,
            'permissionData' => $permissionData,
            'allAgents' => $ownedAgents,
            'allPermissions' => PermissionService::ALL_PERMISSIONS,
            'permissionLabels' => PermissionService::PERMISSION_LABELS,
        ]);
    }

    public function update(int $id): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();

        $user = $this->db->fetchOne("SELECT * FROM users WHERE id = ?", [$id]);
        if (!$user) {
            $this->flash('danger', 'User not found.');
            $this->redirect('/users');
        }

        // Update basic fields
        $data = [];
        if (!empty($_POST['email'])) {
            $newEmail = trim($_POST['email']);
            // Check for duplicate email (exclude current user)
            $existingEmail = $this->db->fetchOne(
                "SELECT id FROM users WHERE email = ? AND id != ?",
                [$newEmail, $id]
            );
            if ($existingEmail) {
                $this->flash('danger', 'Email already in use by another user.');
                $this->redirect("/users/{$id}/edit");
            }
            $data['email'] = $newEmail;
        }
        if (!empty($_POST['role']) && in_array($_POST['role'], ['admin', 'user'])) {
            $data['role'] = $_POST['role'];
        }
        if (!empty($_POST['password'])) {
            $data['password_hash'] = password_hash($_POST['password'], PASSWORD_BCRYPT);
        }
        $data['all_clients'] = 0;

        $newRole = $_POST['role'] ?? $user['role'];
        if ($newRole === 'admin') {
            $ownedClientCount = (int) ($this->db->fetchOne("SELECT COUNT(*) AS cnt FROM agents WHERE user_id = ?", [$id])['cnt'] ?? 0);
            $ownedVirtualStorageCount = (int) ($this->db->fetchOne("SELECT COUNT(*) AS cnt FROM virtual_storages WHERE user_id = ?", [$id])['cnt'] ?? 0);
            if ($ownedClientCount > 0 || $ownedVirtualStorageCount > 0) {
                $this->flash('danger', 'Cannot promote this user to admin while they own clients or virtual storage. Move those resources first.');
                $this->redirect("/users/{$id}/edit");
            }
        }

        if (!empty($data)) {
            $this->db->update('users', $data, 'id = ?', [$id]);
        }

        // Client ownership is managed from the client edit screen. This page
        // only edits explicit action permissions for clients already owned by
        // the target user.
        if ($newRole !== 'admin') {
            $this->syncUserClientPermissions($id, $_POST['permissions'] ?? []);
        } else {
            // Admin users don't need explicit permissions - clear any stale rows.
            $permService = new PermissionService();
            $permService->assignAgents($id, []);
            $permService->setPermissions($id, []);
        }

        $this->flash('success', 'User updated.');
        $this->redirect('/users');
    }

    private function syncUserClientPermissions(int $userId, array $permissionInput): void
    {
        $ownedRows = $this->db->fetchAll("SELECT id FROM agents WHERE user_id = ?", [$userId]);
        $ownedIds = array_values(array_map('intval', array_column($ownedRows, 'id')));
        $ownedSet = array_fill_keys($ownedIds, true);

        $permissions = [];
        foreach ($permissionInput as $agentId => $selected) {
            $agentId = (int) $agentId;
            if (empty($ownedSet[$agentId]) || !is_array($selected)) {
                continue;
            }
            foreach (PermissionService::ALL_PERMISSIONS as $permission) {
                if (!empty($selected[$permission])) {
                    $permissions[] = [
                        'permission' => $permission,
                        'agent_id' => $agentId,
                    ];
                }
            }
        }

        (new PermissionService())->setPermissions($userId, $permissions);
    }

    public function reset2fa(int $id): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();

        $user = $this->db->fetchOne("SELECT username, totp_enabled FROM users WHERE id = ?", [$id]);
        if (!$user) {
            $this->flash('danger', 'User not found.');
            $this->redirect('/users');
        }

        if (!$user['totp_enabled']) {
            $this->flash('warning', '2FA is not enabled for this user.');
            $this->redirect('/users');
        }

        $twoFactor = new TwoFactorService();
        $twoFactor->disableTotp($id);

        $this->flash('success', "2FA disabled for user \"{$user['username']}\".");
        $this->redirect('/users');
    }

    public function approveOidc(int $id): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();

        $user = $this->db->fetchOne("SELECT * FROM users WHERE id = ?", [$id]);
        if (!$user || $user['oidc_status'] !== 'pending') {
            $this->flash('danger', 'User not found or not pending.');
            $this->redirect('/users');
        }

        $this->db->update('users', ['oidc_status' => 'active'], 'id = ?', [$id]);
        $this->flash('success', "User \"{$user['username']}\" approved for SSO access.");
        $this->redirect('/users');
    }

    public function delete(int $id): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();

        if ($id == $_SESSION['user_id']) {
            $this->flash('danger', 'You cannot delete your own account.');
            $this->redirect('/users');
        }

        $user = $this->db->fetchOne("SELECT username FROM users WHERE id = ?", [$id]);
        $this->db->delete('users', 'id = ?', [$id]);
        $this->flash('success', "User \"{$user['username']}\" deleted.");
        $this->redirect('/users');
    }
}
