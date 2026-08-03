<?php
$sql = file_get_contents(__DIR__ . '/law_inci.sql');
$sql = preg_replace('/DELIMITER\s*\$\$/i', '', $sql);
$sql = str_replace('$$', '', $sql);
file_put_contents(__DIR__ . '/law_inci_import.sql', $sql);
echo "prepared\n";
