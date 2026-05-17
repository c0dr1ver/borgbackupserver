<?php

namespace BBS\Controllers;

use BBS\Core\Controller;
use BBS\Services\PermissionService;
use BBS\Services\PluginManager;

class PluginController extends Controller
{
    /**
     * Enable/disable plugins for a client.
     * POST /clients/{id}/plugins
     */
    public function updateAgentPlugins(int $id): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $agent = $this->db->fetchOne("SELECT * FROM agents WHERE id = ?", [$id]);
        if (!$agent || !$this->canAccessAgent($id)) {
            $this->flash('danger', 'Access denied.');
            $this->redirect('/clients');
        }
        $this->requirePermission(PermissionService::MANAGE_PLANS, $id);

        $pluginManager = new PluginManager();
        $selectedPlugins = array_map('intval', $_POST['plugins'] ?? []);
        $allPlugins = $pluginManager->getAllPlugins();

        foreach ($allPlugins as $plugin) {
            $enabled = in_array($plugin['id'], $selectedPlugins);
            $pluginManager->setAgentPlugin($id, $plugin['id'], $enabled);
        }

        $this->flash('success', 'Plugin settings updated.');
        $this->redirect("/clients/{$id}?tab=plugins");
    }
}
