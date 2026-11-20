<?php
declare(strict_types=1);
header('Content-Type: text/css; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');
$file=__DIR__.'/../storage/media.css';
if (is_file($file)) readfile($file);
