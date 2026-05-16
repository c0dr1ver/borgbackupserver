-- Single-owner access model:
-- - non-admin users only access explicitly owned clients
-- - agents.user_id is the source of truth for client ownership
-- - user_agents/user_permissions are synchronized authorization rows
-- - virtual storage may only contain repositories from clients owned by its user

UPDATE users SET all_clients = 0;

UPDATE agents a
JOIN users u ON u.id = a.user_id
SET a.user_id = NULL
WHERE u.role = 'admin';

DELETE FROM user_agents;
DELETE FROM user_permissions;

INSERT INTO user_agents (user_id, agent_id)
SELECT a.user_id, a.id
FROM agents a
JOIN users u ON u.id = a.user_id
WHERE a.user_id IS NOT NULL
  AND u.role != 'admin';

INSERT INTO user_permissions (user_id, permission, agent_id)
SELECT DISTINCT a.user_id, p.permission, a.id
FROM agents a
JOIN users u ON u.id = a.user_id
CROSS JOIN (
    SELECT 'trigger_backup' AS permission UNION ALL
    SELECT 'manage_plans' UNION ALL
    SELECT 'restore'
) p
WHERE a.user_id IS NOT NULL
  AND u.role != 'admin';

DELETE vsr
FROM virtual_storage_repositories vsr
JOIN virtual_storages vs ON vs.id = vsr.virtual_storage_id
JOIN repositories r ON r.id = vsr.repository_id
JOIN agents a ON a.id = r.agent_id
WHERE a.user_id IS NULL
   OR a.user_id != vs.user_id;
