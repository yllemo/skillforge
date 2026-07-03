<?php
/**
 * Minimal ZIP writer using the STORE method only.
 * This avoids requiring ext-zip or PclZip. Suitable for generated Markdown packages.
 */
class SkillForge_StoredZipWriter {
    private $entries = array();

    public function addFileFromString($path, $data) {
        $path = str_replace('\\', '/', trim($path, '/'));
        if ($path === '') return;
        $this->entries[] = array('path' => $path, 'data' => (string)$data);
    }

    public function addFile($pathInZip, $filePath) {
        if (is_readable($filePath)) {
            $this->addFileFromString($pathInZip, file_get_contents($filePath));
        }
    }

    public function save($targetFile) {
        $fh = fopen($targetFile, 'wb');
        if (!$fh) return false;

        $central = '';
        $offset = 0;

        foreach ($this->entries as $entry) {
            $name = $entry['path'];
            $data = $entry['data'];
            $crc = crc32($data);
            if ($crc < 0) $crc += 4294967296;
            $size = strlen($data);
            list($time, $date) = $this->dosDateTime();

            $local = "PK\x03\x04";
            $local .= pack('v', 20); // version needed
            $local .= pack('v', 0);  // flags
            $local .= pack('v', 0);  // compression: store
            $local .= pack('v', $time);
            $local .= pack('v', $date);
            $local .= pack('V', $crc);
            $local .= pack('V', $size);
            $local .= pack('V', $size);
            $local .= pack('v', strlen($name));
            $local .= pack('v', 0);
            $local .= $name;

            fwrite($fh, $local);
            fwrite($fh, $data);

            $central .= "PK\x01\x02";
            $central .= pack('v', 20); // version made by
            $central .= pack('v', 20); // version needed
            $central .= pack('v', 0);
            $central .= pack('v', 0);
            $central .= pack('v', $time);
            $central .= pack('v', $date);
            $central .= pack('V', $crc);
            $central .= pack('V', $size);
            $central .= pack('V', $size);
            $central .= pack('v', strlen($name));
            $central .= pack('v', 0); // extra length
            $central .= pack('v', 0); // comment length
            $central .= pack('v', 0); // disk start
            $central .= pack('v', 0); // internal attrs
            $central .= pack('V', 0); // external attrs
            $central .= pack('V', $offset);
            $central .= $name;

            $offset += strlen($local) + $size;
        }

        $centralOffset = $offset;
        fwrite($fh, $central);
        $centralSize = strlen($central);

        $end = "PK\x05\x06";
        $end .= pack('v', 0);
        $end .= pack('v', 0);
        $end .= pack('v', count($this->entries));
        $end .= pack('v', count($this->entries));
        $end .= pack('V', $centralSize);
        $end .= pack('V', $centralOffset);
        $end .= pack('v', 0);
        fwrite($fh, $end);
        fclose($fh);
        return true;
    }

    private function dosDateTime() {
        $t = getdate();
        $time = (($t['hours'] & 0x1f) << 11) | (($t['minutes'] & 0x3f) << 5) | (($t['seconds'] / 2) & 0x1f);
        $date = ((($t['year'] - 1980) & 0x7f) << 9) | (($t['mon'] & 0x0f) << 5) | ($t['mday'] & 0x1f);
        return array($time, $date);
    }
}
