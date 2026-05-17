<div class="d-flex justify-content-between align-items-center mb-4">
    <h5 class="mb-0">Edit User: <?= htmlspecialchars($user['username']) ?></h5>
    <a href="/users" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Back to Users
    </a>
</div>

<form method="POST" action="/users/<?= $user['id'] ?>/edit">
    <input type="hidden" name="csrf_token" value="<?= $this->csrfToken() ?>">

    <!-- Basic Info -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header border-0">
            <h6 class="mb-0"><i class="bi bi-person me-2"></i>Account Information</h6>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Username</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" disabled>
                    <div class="form-text">Username cannot be changed</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Email</label>
                    <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Role</label>
                    <select class="form-select" name="role" id="roleSelect">
                        <option value="user" <?= $user['role'] === 'user' ? 'selected' : '' ?>>User</option>
                        <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <?php if (($user['auth_provider'] ?? 'local') === 'oidc'): ?>
                    <label class="form-label fw-semibold">Authentication</label>
                    <div><span class="badge text-bg-info"><i class="bi bi-box-arrow-in-right me-1"></i>SSO (OIDC)</span></div>
                    <div class="form-text">This user authenticates via Single Sign-On. No password required.</div>
                    <?php else: ?>
                    <label class="form-label fw-semibold">New Password</label>
                    <input type="password" class="form-control" name="password" placeholder="Leave blank to keep current">
                    <div class="form-text">Only fill this if you want to change the password</div>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Two-Factor Authentication</label>
                    <?php if ($user['totp_enabled']): ?>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-success"><i class="bi bi-shield-check me-1"></i>Enabled</span>
                        <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#reset2faModal">
                            <i class="bi bi-shield-x me-1"></i>Reset 2FA
                        </button>
                    </div>
                    <?php else: ?>
                    <div><span class="badge bg-secondary">Disabled</span></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Owned Clients and Permissions (hidden for admins) -->
    <div class="card border-0 shadow-sm mb-4" id="clientAccessCard" style="<?= $user['role'] === 'admin' ? 'display:none' : '' ?>">
        <div class="card-header border-0">
            <h6 class="mb-0"><i class="bi bi-pc-display me-2"></i>Client Access</h6>
        </div>
        <div class="card-body">
            <p class="text-muted small mb-2">Only clients currently owned by this user are listed here. Change client ownership from the client edit screen; this page only grants action permissions.</p>
            <div id="specificClientsDiv">
                <?php if (empty($allAgents)): ?>
                <p class="text-muted">No clients are assigned to this user.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Client</th>
                                <?php foreach ($allPermissions as $perm): ?>
                                <th class="text-center" style="width: 96px;"><small><?= htmlspecialchars($permissionLabels[$perm] ?? $perm) ?></small></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allAgents as $agent): ?>
                            <?php
                            $agentId = (int) $agent['id'];
                            $ownerLabel = $agent['owner_name'] ?? 'No owner';
                            ?>
                            <tr data-agent-id="<?= $agent['id'] ?>">
                                <td>
                                    <span class="client-name fw-semibold">
                                        <?= htmlspecialchars($agent['name']) ?>
                                    </span>
                                    <div class="text-muted small">Owner: <?= htmlspecialchars($ownerLabel) ?></div>
                                </td>
                                <?php foreach ($allPermissions as $perm): ?>
                                <td class="text-center">
                                    <input class="form-check-input permission-checkbox" type="checkbox"
                                        name="permissions[<?= $agentId ?>][<?= htmlspecialchars($perm) ?>]"
                                        value="1"
                                        data-agent-id="<?= $agentId ?>"
                                        <?= !empty($permissionData[$agentId][$perm]) ? 'checked' : '' ?>>
                                </td>
                                <?php endforeach; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Actions -->
    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-sm btn-primary">
            <i class="bi bi-check-lg me-1"></i> Save Changes
        </button>
        <a href="/users" class="btn btn-sm btn-outline-secondary">Cancel</a>
    </div>
</form>

<!-- Reset 2FA Modal -->
<?php if ($user['totp_enabled']): ?>
<div class="modal fade" id="reset2faModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reset Two-Factor Authentication</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to reset 2FA for <strong><?= htmlspecialchars($user['username']) ?></strong>?</p>
                <p class="text-muted small">This will disable their 2FA and delete all recovery codes. They will need to set up 2FA again.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" action="/users/<?= $user['id'] ?>/reset-2fa" class="d-inline">
                    <input type="hidden" name="csrf_token" value="<?= $this->csrfToken() ?>">
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-shield-x me-1"></i> Reset 2FA
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const roleSelect = document.getElementById('roleSelect');
    const clientAccessCard = document.getElementById('clientAccessCard');

    // Toggle client access based on role
    roleSelect.addEventListener('change', function() {
        const isAdmin = this.value === 'admin';
        clientAccessCard.style.display = isAdmin ? 'none' : '';
    });
});
</script>
