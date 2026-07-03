<?php
if (!defined('DOKU_INC')) die();

class admin_plugin_skillforge extends DokuWiki_Admin_Plugin {
    private $message = '';
    private $download = '';

    public function forAdminOnly() { return true; }
    public function getMenuSort() { return 250; }

    public function handle() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && checkSecurityToken()) {
            try {
                /** @var helper_plugin_skillforge $helper */
                $helper = plugin_load('helper', 'skillforge');
                $result = $helper->exportNamespace(
                    $_POST['sf_namespace'],
                    null,
                    array(
                        'recursive' => !empty($_POST['sf_recursive']),
                        'include_media' => !empty($_POST['sf_include_media'])
                    )
                );
                $this->message = 'Export created: ' . hsc($result['name']) . ' (' . (int)$result['count'] . ' pages).';
                $this->download = $result['name'];
            } catch (Exception $e) {
                $this->message = 'SkillForge export failed: ' . hsc($e->getMessage());
            }
        }
    }

    public function html() {
        /** @var helper_plugin_skillforge $helper */
        $helper = plugin_load('helper', 'skillforge');
        $namespaces = $helper ? $helper->listNamespaces() : array();
        $selectedNamespace = isset($_POST['sf_namespace']) ? $_POST['sf_namespace'] : '';

        ptln('<h1>SkillForge</h1>');
        ptln('<p>Export a DokuWiki namespace as an AI-ready Markdown package with SKILL.md, index.md, skill.json and optional media. ZIP creation uses an internal ZIP writer and does not require PHP ZipArchive.</p>');
        if ($this->message) ptln('<div class="info">' . $this->message . '</div>');
        if ($this->download) {
            global $ID;
            $url = wl($ID ?: 'start', array(
                'do' => 'skillforge_download',
                'sf_file' => $this->download,
                'sectok' => getSecurityToken()
            ), false, '&');
            ptln('<p><a class="button" href="' . hsc($url) . '">Download ZIP</a></p>');
            ptln('<p><small>If the button does not start a download, copy/open this link in a new tab: <code>' . hsc($url) . '</code></small></p>');
        }
        ptln('<form method="post">');
        formSecurityToken();
        ptln('<table class="inline">');
        ptln('<tr><th>Namespace</th><td>');
        if ($namespaces) {
            ptln('<select name="sf_namespace" required>');
            ptln('<option value="">-- Select namespace --</option>');
            foreach ($namespaces as $ns) {
                $sel = ($ns === $selectedNamespace) ? ' selected' : '';
                ptln('<option value="' . hsc($ns) . '"' . $sel . '>' . hsc($ns) . '</option>');
            }
            ptln('</select>');
        } else {
            ptln('<input type="text" name="sf_namespace" value="" placeholder="ai:prompting" required>');
            ptln('<br><small>No namespaces were found automatically. Enter a namespace manually.</small>');
        }
        ptln('</td></tr>');
        ptln('<tr><th>SKILL source page</th><td><code>' . hsc($this->getConf('default_skill_source')) . '</code> <small>Configured in plugin settings.</small></td></tr>');
        ptln('<tr><th>Recursive</th><td><label><input type="checkbox" name="sf_recursive" value="1" checked> Include subnamespaces</label></td></tr>');
        ptln('<tr><th>Media</th><td><label><input type="checkbox" name="sf_include_media" value="1" checked> Include media namespace files</label></td></tr>');
        ptln('</table>');
        ptln('<p><button type="submit">Forge Package</button></p>');
        ptln('</form>');
        ptln('<h2>Metadata in configured source page</h2>');
        ptln('<pre>&lt;frontmatter&gt;\nname: my-skill\ndescription: What this skill helps the AI do.\nversion: 1.0.0\ntags:\n  - ai\n  - dokuwiki\n&lt;/frontmatter&gt;</pre>');
    }
}
