<?php
$legacyFile = BASE_PATH . '/' . $legacyPath;
if (!is_file($legacyFile)) {
    throw new RuntimeException('Halaman modul tidak ditemukan.');
}
require $legacyFile;
