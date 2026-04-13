<?php

/**
 * 开发服务器路由：静态文件从 public 提供，其余请求交给 Laravel
 * 使用方式：在项目根目录执行 php -S 127.0.0.1:8000 -t public public/router.php
 */
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$path = __DIR__ . $uri;

if ($uri !== '/' && file_exists($path) && !is_dir($path)) {
    return false; // 让内置服务器直接提供静态文件（css/js 等）
}

require __DIR__ . '/index.php';
