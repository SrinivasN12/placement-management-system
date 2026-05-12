<?php
// One-time file copy helper — run once then delete
$src  = 'C:/Users/srini/.gemini/antigravity/brain/e478883a-272e-4278-be4c-46377cf8eb86/media__1778606984053.jpg';
$dest = __DIR__ . '/logo.jpg';
if (!file_exists($dest)) {
    if (copy($src, $dest)) {
        echo 'Logo copied successfully to ' . $dest;
    } else {
        echo 'Failed to copy. Check path: ' . $src;
    }
} else {
    echo 'Logo already exists at ' . $dest;
}
