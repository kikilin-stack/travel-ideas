#!/bin/bash
# Start Laravel dev server from project root (document root: public, router for static files).
cd "$(dirname "$0")"

PORT=8000
if lsof -i :$PORT -t >/dev/null 2>&1; then
  echo "Port $PORT in use, trying 8080..."
  PORT=8080
fi

# Listen on all interfaces so other devices on the same network can connect.
HOST=0.0.0.0

LAN_IP=""
for IFACE in en0 en1 en2; do
  IP=$(ipconfig getifaddr "$IFACE" 2>/dev/null)
  if [ -n "$IP" ]; then
    LAN_IP="$IP"
    break
  fi
done

echo ""
echo "Server listening on all interfaces (port $PORT)."
echo "  This machine:  http://127.0.0.1:$PORT"
if [ -n "$LAN_IP" ]; then
  echo "  Same Wi‑Fi/LAN: http://${LAN_IP}:$PORT"
else
  echo "  Same Wi‑Fi/LAN: http://<your-LAN-IP>:$PORT  (run: ipconfig getifaddr en0)"
fi
echo "  Login page:     http://127.0.0.1:$PORT/login"
echo ""
echo "If others cannot connect: allow incoming TCP $PORT in macOS Firewall / Security settings."
echo ""

php -S "${HOST}:$PORT" -t public public/router.php
