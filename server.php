<?php

/**
 * 开发服务器路由脚本：将所有请求转发到 public/index.php
 * 用于 php artisan serve 或 php -S 时正确解析 /login 等路由
 */
// server.php 位于项目根目录，public 在同级
$publicPath = __DIR__ . '/public';
if (!is_dir($publicPath)) {
    $publicPath = getcwd();
}

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');

if ($uri !== '/' && file_exists($publicPath . $uri) && !is_dir($publicPath . $uri)) {
    return false;
}

require $publicPath . '/index.php';
