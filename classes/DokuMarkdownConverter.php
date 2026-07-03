<?php
class SkillForge_DokuMarkdownConverter {
    public function convert($text) {
        $text = $this->stripMetadataBlocks($text);
        $text = preg_replace('/^======\s*(.*?)\s*======\s*$/m', '# $1', $text);
        $text = preg_replace('/^=====\s*(.*?)\s*=====\s*$/m', '## $1', $text);
        $text = preg_replace('/^====\s*(.*?)\s*====\s*$/m', '### $1', $text);
        $text = preg_replace('/^===\s*(.*?)\s*===\s*$/m', '#### $1', $text);
        $text = preg_replace('/^==\s*(.*?)\s*==\s*$/m', '##### $1', $text);
        $text = preg_replace('/\*\*(.*?)\*\*/s', '**$1**', $text);
        $text = preg_replace('/\/\/(.*?)\/\//s', '*$1*', $text);
        $text = preg_replace('/__([^_]+)__/', '<u>$1</u>', $text);
        $text = preg_replace_callback('/<code(?:\s+([^>]+))?>(.*?)<\/code>/is', function($m) {
            $lang = isset($m[1]) ? trim($m[1]) : '';
            return "```" . $lang . "\n" . trim($m[2]) . "\n```";
        }, $text);
        $text = preg_replace('/<file(?:\s+[^>]*)?>(.*?)<\/file>/is', "```\n$1\n```", $text);
        $text = preg_replace_callback('/\[\[([^\]|]+)\|([^\]]+)\]\]/', function($m) {
            return '[' . $m[2] . '](' . $this->pageIdToMd($m[1]) . ')';
        }, $text);
        $text = preg_replace_callback('/\[\[([^\]]+)\]\]/', function($m) {
            return '[' . $m[1] . '](' . $this->pageIdToMd($m[1]) . ')';
        }, $text);
        $text = preg_replace_callback('/\{\{([^\}|]+)(?:\|([^\}]+))?\}\}/', function($m) {
            $alt = isset($m[2]) ? trim($m[2]) : '';
            return '![' . $alt . '](media/' . basename(trim($m[1])) . ')';
        }, $text);
        return trim($text) . "\n";
    }

    public function extractMetadata($text) {
        if (preg_match('/<frontmatter>(.*?)<\/frontmatter>/is', $text, $m)) return trim($m[1]);
        if (preg_match('/<skillmeta>(.*?)<\/skillmeta>/is', $text, $m)) return trim($m[1]);
        if (preg_match('/^---\s*\R(.*?)\R---\s*\R/s', $text, $m)) return trim($m[1]);
        return '';
    }

    private function stripMetadataBlocks($text) {
        $text = preg_replace('/<frontmatter>.*?<\/frontmatter>\s*/is', '', $text);
        $text = preg_replace('/<skillmeta>.*?<\/skillmeta>\s*/is', '', $text);
        $text = preg_replace('/^---\s*\R.*?\R---\s*\R/s', '', $text);
        return $text;
    }

    private function pageIdToMd($id) {
        if (preg_match('/^https?:\/\//i', $id)) return $id;
        $id = preg_replace('/#[^#]*$/', '', $id);
        $id = trim(str_replace(':', '/', $id), '/');
        return basename($id) . '.md';
    }
}
