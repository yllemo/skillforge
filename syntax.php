<?php
if (!defined('DOKU_INC')) die();

/**
 * Optional syntax component: renders <frontmatter> and <skillmeta> blocks as
 * readable page metadata with an optional ZIP download action.
 */
class syntax_plugin_skillforge extends DokuWiki_Syntax_Plugin {
    public function getType() { return 'substition'; }
    public function getSort() { return 32; }
    public function connectTo($mode) {
        $this->Lexer->addSpecialPattern('<frontmatter>.*?</frontmatter>', $mode, 'plugin_skillforge');
        $this->Lexer->addSpecialPattern('<skillmeta>.*?</skillmeta>', $mode, 'plugin_skillforge');
    }

    public function handle($match, $state, $pos, Doku_Handler $handler) {
        if (preg_match('/^<(frontmatter|skillmeta)>(.*?)<\/\1>$/is', $match, $m)) {
            return array('metadata' => trim($m[2]));
        }
        return array('metadata' => '');
    }

    public function render($mode, Doku_Renderer $renderer, $data) {
        if ($mode !== 'xhtml') return true;

        $showMetadata = (bool)$this->getConf('show_rendered_metadata');
        $showDownload = (bool)$this->getConf('show_page_download_button');
        if (!$showMetadata && !$showDownload) return true;

        $content = '';
        if ($showMetadata) {
            $content .= $this->renderMetadata($data['metadata']);
        }
        if ($showDownload && auth_isadmin()) {
            $content .= $this->renderDownloadButton($data['metadata']);
        }
        if ($content === '') return true;

        $renderer->doc .= '<div class="skillforge-page-tools">' . $content . '</div>';
        return true;
    }

    private function renderMetadata($metadata) {
        $fields = $this->parseMetadata($metadata);
        if (!$fields) return '';

        $html = '<section class="skillforge-metadata"><h2>Skill metadata</h2><dl>';
        foreach ($fields as $name => $value) {
            $html .= '<dt>' . hsc($name) . '</dt><dd>';
            if (is_array($value)) {
                $html .= '<ul>';
                foreach ($value as $item) {
                    $html .= '<li>' . hsc($item) . '</li>';
                }
                $html .= '</ul>';
            } else {
                $html .= hsc($value);
            }
            $html .= '</dd>';
        }
        return $html . '</dl></section>';
    }

    private function renderDownloadButton($metadata) {
        global $ID;
        $url = wl($ID, array(
            'do' => 'skillforge_download_current',
            'sf_page' => $ID,
            'sectok' => getSecurityToken()
        ), false, '&');
        $label = $this->downloadButtonLabel($metadata);

        return '<p class="skillforge-download"><a class="button" href="' . hsc($url) . '">' . hsc($label) . '</a></p>';
    }

    private function downloadButtonLabel($metadata) {
        $label = trim((string)$this->getConf('download_button_label'));
        if ($label === '') $label = 'Download SKILL.md (.zip)';

        $fields = $this->parseMetadata($metadata);
        $replacements = array(
            '{name}' => isset($fields['name']) && !is_array($fields['name']) ? $fields['name'] : '',
            '{title}' => isset($fields['title']) && !is_array($fields['title']) ? $fields['title'] : '',
            '{description}' => isset($fields['description']) && !is_array($fields['description']) ? $fields['description'] : '',
            '{output}' => $this->getConf('output_skill_filename') ?: 'SKILL.md',
        );

        $label = str_replace(array_keys($replacements), array_values($replacements), $label);
        $label = preg_replace('/\s+/', ' ', trim($label));
        return $label !== '' ? $label : 'Download SKILL.md (.zip)';
    }

    private function parseMetadata($metadata) {
        $fields = array();
        $current = null;
        foreach (preg_split('/\R/', (string)$metadata) as $line) {
            $line = rtrim($line);
            if ($line === '' || preg_match('/^\s*#/', $line)) continue;

            if ($current !== null && preg_match('/^\s*-\s*(.+)$/', $line, $m)) {
                if (!isset($fields[$current]) || !is_array($fields[$current])) $fields[$current] = array();
                $fields[$current][] = trim($m[1], " \t\"'");
                continue;
            }

            if (preg_match('/^([A-Za-z0-9_.-]+):\s*(.*)$/', $line, $m)) {
                $current = $m[1];
                $value = trim($m[2], " \t\"'");
                $fields[$current] = ($value === '') ? array() : $value;
            }
        }
        return $fields;
    }
}
