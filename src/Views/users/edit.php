<div class="d-flex justify-content-between align-items-center mb-4">
    <h5 class="mb-0">Edit User: <?= htmlspecialchars($user['username']) ?></h5>
    <a href="/users" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Back to Users
    </a>
</div>

<?php
// Custom column labels for the permission table
$columnLabels = [
    'trigger_backup' => 'Run Backups',
    'manage_repos' => 'Manage Repos',
    'manage_plans' => 'Manage Plans',
    'restore' => 'Perform Restores',
    'repo_maintenance' => 'Repo Maint',
];
?>

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

    <!-- Owned Clients (hidden for admins) -->
    <div class="card border-0 shadow-sm mb-4" id="clientAccessCard" style="<?= $user['role'] === 'admin' ? 'display:none' : '' ?>">
        <div class="card-header border-0">
            <h6 class="mb-0"><i class="bi bi-pc-display me-2"></i>Owned Clients</h6>
        </div>
        <div class="card-body">
            <p class="text-muted small mb-2">Assigned clients grant full management rights for backup jobs, plans, repositories, restores, and maintenance. Admins always manage all clients.</p>
            <div id="specificClientsDiv">
                <?php if (empty($allAgents)): ?>
                <p class="text-muted">No clients available</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Client</th>
                                <th class="text-center" style="width: 120px;"><small>Owner</small></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allAgents as $agent): ?>
                            <?php $isAssigned = in_array($agent['id'], $userAgentIds); ?>
                            <tr class="<?= $isAssigned ? '' : 'table-light' ?>" data-agent-id="<?= $agent['id'] ?>">
                                <td>
                                    <span class="client-name <?= $isAssigned ? 'fw-semibold' : 'text-muted' ?>">
                                        <?= htmlspecialchars($agent['name']) ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <input class="form-check-input client-checkbox" type="checkbox" name="agents[]"
                                        value="<?= $agent['id'] ?>" id="agent_<?= $agent['id'] ?>"
                                        data-agent-id="<?= $agent['id'] ?>"
                                        <?= $isAssigned ? 'checked' : '' ?>>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <td class="text-end small text-muted">Select all:</td>
                                <td class="text-center">
                                    <input type="checkbox" class="form-check-input" id="selectAllClients" title="Select/Deselect All">
                                </td>
                            </tr>
                        </tfoot>
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
    const selectAllClients = document.getElementById('selectAllClients');

    // Toggle client access based on role
    roleSelect.addEventListener('change', function() {
        const isAdmin = this.value === 'admin';
        clientAccessCard.style.display = isAdmin ? 'none' : '';
    });

    // Handle client checkbox changes.
    document.querySelectorAll('.client-checkbox').forEach(function(checkbox) {
        checkbox.addEventListener('change', function() {
            const row = this.closest('tr');
            const clientName = row.querySelector('.client-name');

            if (this.checked) {
                row.classList.remove('table-light');
                clientName.classList.add('fw-semibold');
                clientName.classList.remove('text-muted');
            } else {
                row.classList.add('table-light');
                clientName.classList.remove('fw-semibold');
                clientName.classList.add('text-muted');
            }
            updateSelectAllState();
        });
    });

    // Select all clients checkbox
    if (selectAllClients) {
        selectAllClients.addEventListener('change', function() {
            document.querySelectorAll('.client-checkbox').forEach(cb => {
                if (cb.checked !== this.checked) {
                    cb.checked = this.checked;
                    cb.dispatchEvent(new Event('change'));
                }
            });
        });
    }

    // Update select-all checkbox state based on individual checkboxes
    function updateSelectAllState() {
        if (!selectAllClients) return;
        const allCheckboxes = document.querySelectorAll('.client-checkbox');
        const checkedCount = document.querySelectorAll('.client-checkbox:checked').length;
        selectAllClients.checked = checkedCount === allCheckboxes.length;
        selectAllClients.indeterminate = checkedCount > 0 && checkedCount < allCheckboxes.length;
    }

    // Initial state update
    updateSelectAllState();
});
</script>
