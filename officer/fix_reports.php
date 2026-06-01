<?php
$file = __DIR__ . '/reports.php';
$lines = file($file, FILE_IGNORE_NEW_LINES);
$keepTo = 0;
foreach ($lines as $i => $line) {
    if (strpos($line, "require '_nav_end.php'") !== false) {
        $keepTo = $i;
        break;
    }
}
if ($keepTo === 0) { echo 'Marker not found!'; exit; }
$kept = array_slice($lines, 0, $keepTo + 1);
file_put_contents($file, implode("\n", $kept) . "\n");
echo 'Done. Kept ' . ($keepTo + 1) . ' lines.';
unlink(__FILE__);
