<?php
if (!defined('DOKU_INC')) die();
require_once __DIR__ . '/classes/StoredZipWriter.php';
require_once __DIR__ . '/classes/DokuMarkdownConverter.php';

class helper_plugin_skillforge extends DokuWiki_Plugin {
    public function exportPage($id, $options = array()) {
        $id = trim($this->normalizeId($id), ':');
        if ($id === '' || strpos($id, ':') === false) {
            throw new Exception('Skill pages must be inside a namespace to be exported.');
        }

        $parts = explode(':', $id);
        $sourcePage = array_pop($parts);
        $namespace = implode(':', $parts);
        if ($namespace === '') throw new Exception('Namespace is required.');

        return $this->exportNamespace($namespace, $sourcePage . '.txt', $options);
    }

    public function exportNamespace($namespace, $sourcePage, $options = array()) {
        global $conf;
        $namespace = trim((string)$namespace, ':');
        if ($namespace === '') throw new Exception('Namespace is required.');
        $sourcePage = $sourcePage ?: $this->getConf('default_skill_source');
        $recursive = isset($options['recursive']) ? (bool)$options['recursive'] : (bool)$this->getConf('recursive');
        $includeMedia = isset($options['include_media']) ? (bool)$options['include_media'] : (bool)$this->getConf('include_media');

        $sourceId = $this->resolveSourceId($namespace, $sourcePage);
        $pages = $this->collectPages($namespace, $recursive);

        // Be forgiving: the source page may exist even if the scanner missed it
        // because of DokuWiki storage differences, cleanID rules or installations
        // with custom datadir/path handling.
        $sourceFile = $this->pageFile($sourceId);
        if (is_readable($sourceFile)) {
            $pages[$sourceId] = $sourceFile;
            ksort($pages);
        }

        if (!$pages) throw new Exception('No pages found in namespace: ' . hsc($namespace));
        if (!isset($pages[$sourceId])) {
            throw new Exception('Skill source page not found: ' . hsc($sourceId) . ' (' . hsc($sourceFile) . ')');
        }

        $converter = new SkillForge_DokuMarkdownConverter();
        $baseFolder = $this->safeName($namespace) . '-skill';
        $zip = new SkillForge_StoredZipWriter();
        $manifest = array(
            'name' => $this->safeName($namespace),
            'namespace' => $namespace,
            'entry' => $this->getConf('output_skill_filename') ?: 'SKILL.md',
            'generated_at' => date('c'),
            'source' => 'dokuwiki',
            'generator' => 'SkillForge',
            'files' => array()
        );

        foreach ($pages as $id => $file) {
            $raw = io_readFile($file, false);
            $md = $converter->convert($raw);
            $metadata = $converter->extractMetadata($raw);
            $outName = ($id === $sourceId) ? ($this->getConf('output_skill_filename') ?: 'SKILL.md') : $this->idToMarkdownFilename($namespace, $id);
            if ($id === $sourceId) {
                $md = $this->buildSkillMarkdown($namespace, $metadata, $md, $pages, $sourceId);
            } else {
                $md = $this->ensureFrontmatter($id, $namespace, $metadata, $md);
            }
            $zip->addFileFromString($baseFolder . '/' . $outName, $md);
            $manifest['files'][] = $outName;
        }

        if ($this->getConf('generate_index')) {
            $zip->addFileFromString($baseFolder . '/index.md', $this->buildIndex($namespace, $manifest['files']));
            $manifest['files'][] = 'index.md';
        }

        $zip->addFileFromString($baseFolder . '/skill.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        if ($includeMedia) {
            foreach ($this->collectMedia($namespace, $recursive) as $mediaId => $mediaFile) {
                $zip->addFile($baseFolder . '/media/' . basename($mediaFile), $mediaFile);
            }
        }

        $tmpDir = rtrim($conf['tmpdir'], '/\\') . '/skillforge';
        if (!is_dir($tmpDir)) io_mkdir_p($tmpDir);
        $zipName = $this->makeZipName($namespace);
        $target = $tmpDir . '/' . $zipName;
        if (!$zip->save($target)) throw new Exception('Could not write ZIP file to tmp directory.');
        return array('file' => $target, 'name' => $zipName, 'count' => count($pages));
    }


    public function listNamespaces() {
        global $conf;
        $namespaces = array();
        $base = isset($conf['datadir']) ? rtrim($conf['datadir'], '/\\') : '';
        if ($base === '' || !is_dir($base)) return array();

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (!$file->isFile() || strtolower($file->getExtension()) !== 'txt') continue;
            $id = $this->fileToPageId($file->getPathname());
            if ($id === '' || strpos($id, ':') === false) continue;
            $parts = explode(':', $id);
            array_pop($parts); // remove page name
            $current = array();
            foreach ($parts as $part) {
                if ($part === '') continue;
                $current[] = $part;
                $namespaces[implode(':', $current)] = true;
            }
        }
        $out = array_keys($namespaces);
        natcasesort($out);
        return array_values($out);
    }

    public function sendDownload($name) {
        global $conf;
        $name = basename((string)$name);
        if (!preg_match('/\.zip$/i', $name)) throw new Exception('Invalid download filename.');
        $file = rtrim($conf['tmpdir'], '/\\') . '/skillforge/' . $name;
        if (!is_readable($file)) throw new Exception('Export file not found: ' . $name);

        // Avoid corrupt ZIP downloads if something has already started output.
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }

        header('Content-Description: File Transfer');
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Content-Transfer-Encoding: binary');
        header('Content-Length: ' . filesize($file));
        header('Cache-Control: private, no-cache, no-store, must-revalidate');
        header('Pragma: public');
        header('Expires: 0');
        readfile($file);
        exit;
    }

    private function collectPages($namespace, $recursive) {
        global $conf;
        $pages = array();
        $namespace = trim($this->normalizeId($namespace), ':');
        $root = rtrim($conf['datadir'], '/\\') . '/' . str_replace(':', '/', $namespace);

        // Preferred path: scan the namespace directory directly.
        if (is_dir($root)) {
            $iterator = $recursive
                ? new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS))
                : new IteratorIterator(new DirectoryIterator($root));
            foreach ($iterator as $file) {
                if (!$file->isFile() || strtolower($file->getExtension()) !== 'txt') continue;
                $path = $file->getPathname();
                $id = $this->fileToPageId($path);
                if ($id !== '' && ($id === $namespace || strpos($id, $namespace . ':') === 0)) {
                    $pages[$id] = $path;
                }
            }
        }

        // Fallback path: scan all pages and filter by namespace. This helps on
        // installations where datadir or storage behavior differs from defaults.
        if (!$pages && is_dir($conf['datadir'])) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($conf['datadir'], FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if (!$file->isFile() || strtolower($file->getExtension()) !== 'txt') continue;
                $path = $file->getPathname();
                $id = $this->fileToPageId($path);
                if ($id === '' || $id === $namespace) continue;
                if ($recursive) {
                    if (strpos($id, $namespace . ':') === 0) $pages[$id] = $path;
                } else {
                    $tail = substr($id, strlen($namespace . ':'));
                    if (strpos($id, $namespace . ':') === 0 && strpos($tail, ':') === false) $pages[$id] = $path;
                }
            }
        }

        ksort($pages);
        return $pages;
    }

    private function collectMedia($namespace, $recursive) {
        global $conf;
        $root = rtrim($conf['mediadir'], '/\\') . '/' . str_replace(':', '/', $namespace);
        $media = array();
        if (!is_dir($root)) return $media;
        $iterator = $recursive ? new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) : new IteratorIterator(new DirectoryIterator($root));
        foreach ($iterator as $file) {
            if (!$file->isFile()) continue;
            $path = $file->getPathname();
            $rel = substr($path, strlen(rtrim($conf['mediadir'], '/\\')) + 1);
            $media[str_replace('/', ':', $rel)] = $path;
        }
        return $media;
    }

    private function resolveSourceId($namespace, $sourcePage) {
        $namespace = trim($this->normalizeId($namespace), ':');
        $sourcePage = preg_replace('/\.txt$/i', '', trim((string)$sourcePage));
        $sourcePage = trim(str_replace('\\', '/', $sourcePage));
        $sourcePage = trim(str_replace('/', ':', $sourcePage), ':');
        $sourcePage = $this->normalizeId($sourcePage);
        if ($sourcePage === '') $sourcePage = 'start';

        // Full DokuWiki ID given, e.g. :skilltest:start or skilltest:start.
        if (strpos($sourcePage, ':') !== false) return trim($sourcePage, ':');

        // Relative page given, e.g. start or start.txt.
        return trim($namespace . ':' . $sourcePage, ':');
    }

    private function normalizeId($id) {
        $id = trim(str_replace('\\', '/', (string)$id));
        $id = trim(str_replace('/', ':', $id), ':');
        if (function_exists('cleanID')) return cleanID($id);
        return strtolower($id);
    }

    private function pageFile($id) {
        global $conf;
        $id = trim($this->normalizeId($id), ':');
        if (function_exists('wikiFN')) return wikiFN($id);
        return rtrim($conf['datadir'], '/\\') . '/' . str_replace(':', '/', $id) . '.txt';
    }

    private function fileToPageId($path) {
        global $conf;
        $base = rtrim(realpath($conf['datadir']) ?: $conf['datadir'], '/\\');
        $real = realpath($path) ?: $path;
        $rel = substr($real, strlen($base) + 1);
        $rel = str_replace('\\', '/', $rel);
        if (substr($rel, -4) !== '.txt') return '';
        return trim(str_replace('/', ':', substr($rel, 0, -4)), ':');
    }

    private function idToMarkdownFilename($namespace, $id) {
        $rel = preg_replace('/^' . preg_quote($namespace, '/') . ':?/', '', $id);
        $rel = trim(str_replace(':', '/', $rel), '/');
        if ($rel === '') $rel = 'page';
        return $rel . '.md';
    }

    private function buildSkillMarkdown($namespace, $metadata, $body, $pages, $sourceId) {
        if ($metadata === '') {
            $metadata = "name: " . $this->safeName($namespace) . "\ndescription: Exported DokuWiki namespace as an AI skill.\nversion: 0.1.0\nsource: dokuwiki\nnamespace: " . $namespace;
        }
        $links = "\n\n## Knowledge files\n\n";
        foreach ($pages as $id => $file) {
            if ($id === $sourceId) continue;
            $links .= '- [' . $this->titleFromId($id) . '](' . $this->idToMarkdownFilename($namespace, $id) . ")\n";
        }
        return "---\n" . trim($metadata) . "\n---\n\n" . trim($body) . $links . "\n";
    }

    private function ensureFrontmatter($id, $namespace, $metadata, $body) {
        if ($metadata === '') {
            $metadata = "title: " . $this->titleFromId($id) . "\ntype: page\nsource: dokuwiki\ndokuwiki_id: " . $id . "\nnamespace: " . $namespace;
        }
        return "---\n" . trim($metadata) . "\n---\n\n" . trim($body) . "\n";
    }

    private function buildIndex($namespace, $files) {
        $out = "---\ntitle: Skill Index\ntype: index\nsource: dokuwiki\nnamespace: " . $namespace . "\ngenerator: SkillForge\n---\n\n# Skill Index\n\nThis package was generated from the DokuWiki namespace `" . $namespace . "`.\n\n## Files\n\n";
        foreach ($files as $file) {
            if ($file === 'index.md') continue;
            $out .= '- [' . $file . '](' . $file . ")\n";
        }
        return $out;
    }

    private function makeZipName($namespace) {
        $pattern = $this->getConf('zip_filename_pattern') ?: '{namespace}-skill-{date}.zip';
        $name = str_replace(array('{namespace}', '{date}'), array($this->safeName($namespace), date('Y-m-d')), $pattern);
        $name = preg_replace('/[^A-Za-z0-9._-]+/', '-', $name);
        if (!preg_match('/\.zip$/i', $name)) $name .= '.zip';
        return $name;
    }

    private function safeName($value) {
        $value = strtolower(str_replace(':', '-', $value));
        $value = preg_replace('/[^a-z0-9._-]+/', '-', $value);
        return trim($value, '-') ?: 'skillforge';
    }

    private function titleFromId($id) {
        $base = basename(str_replace(':', '/', $id));
        return ucwords(str_replace(array('_', '-'), ' ', $base));
    }
}
