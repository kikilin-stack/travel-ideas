<?php

/**
 * 后备入口：当请求 /login 时，若被当作静态路径处理，则由此文件加载 Laravel
 * 请求仍会以 path=/login 进入应用，从而匹配登录路由
 */
require __DIR__ . '/index.php';
