<?php

// Router for the PHP built-in dev server: serve existing public files, otherwise Laravel.
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$path = $_SERVER['DOCUMENT_ROOT'].$uri;

if ($uri !== '/' && is_file($path)) {
    return false;
}

require $_SERVER['DOCUMENT_ROOT'].'/index.php';