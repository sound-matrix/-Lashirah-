<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');
$file=__DIR__.'/../storage/site.json';
if (!is_file($file)) {echo '{"published":false}';exit;}
readfile($file);
