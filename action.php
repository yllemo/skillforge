<?php
if (!defined('DOKU_INC')) die();

/**
 * Handles SkillForge downloads outside the admin dispatcher.
 *
 * DokuWiki's admin pages can redirect or re-render before a binary download is
 * sent, especially on compact/on-a-stick installs. A dedicated action avoids
 * that path completely:
 *   doku.php?id=start&do=skillforge_download&sf_file=...zip&sectok=...
 */
class action_plugin_skillforge extends DokuWiki_Action_Plugin {
    public function register(Doku_Event_Handler $controller) {
        $controller->register_hook('DOKUWIKI_STARTED', 'BEFORE', $this, 'handleDownload');
    }

    public function handleDownload(Doku_Event $event, $param) {
        if (!isset($_REQUEST['do']) || !in_array($_REQUEST['do'], array('skillforge_download', 'skillforge_download_current'), true)) return;

        if (!auth_isadmin()) {
            http_status(403);
            echo 'SkillForge download denied.';
            exit;
        }

        if (!checkSecurityToken()) {
            http_status(403);
            echo 'SkillForge download denied: invalid security token.';
            exit;
        }

        /** @var helper_plugin_skillforge $helper */
        $helper = plugin_load('helper', 'skillforge');
        if (!$helper) {
            http_status(500);
            echo 'SkillForge helper could not be loaded.';
            exit;
        }

        try {
            if ($_REQUEST['do'] === 'skillforge_download_current') {
                global $ID;
                $page = isset($_REQUEST['sf_page']) ? $_REQUEST['sf_page'] : $ID;
                $result = $helper->exportPage($page);
                $helper->sendDownload($result['name']);
                return;
            }

            $name = isset($_REQUEST['sf_file']) ? $_REQUEST['sf_file'] : '';
            $helper->sendDownload($name);
        } catch (Exception $e) {
            http_status(404);
            echo 'SkillForge download failed: ' . hsc($e->getMessage());
            exit;
        }
    }
}
