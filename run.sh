#!/bin/bash
# 在项目根目录启动开发服务器（public 为根，静态资源可访问）
cd "$(dirname "$0")"
PORT=8000
if lsof -i :$PORT -t >/dev/null 2>&1; then
  echo "Port $PORT in use, trying 8080..."
  PORT=8080
fi
echo "Starting server at http://127.0.0.1:$PORT (login: http://127.0.0.1:$PORT/login)"
php -S 127.0.0.1:$PORT -t public public/router.php
