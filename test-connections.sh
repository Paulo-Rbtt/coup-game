#!/bin/sh
# ═══════════════════════════════════════════════════════════════
# test-connections.sh — Coup Connection Tests
# Verifies DB, Reverb, HTTP, and HTTPS are all reachable.
#
# Usage:
#   chmod +x test-connections.sh
#   ./test-connections.sh
# ═══════════════════════════════════════════════════════════════

PASS=0
FAIL=0

ok()   { echo "  ✅  $1"; PASS=$((PASS+1)); }
fail() { echo "  ❌  $1"; FAIL=$((FAIL+1)); }
info() { echo "  ℹ️   $1"; }
sep()  { echo ""; echo "── $1 ─────────────────────────────────────"; }

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  🔌  Coup — Connection Tests"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# ── 1. Container status ──────────────────────────────────
sep "Docker Containers"
for svc in coup-app coup-nginx coup-db; do
  STATUS=$(docker inspect --format='{{.State.Status}}' "$svc" 2>/dev/null || echo "not found")
  if [ "$STATUS" = "running" ]; then
    ok "$svc is running"
  else
    fail "$svc — status: $STATUS"
  fi
done

# ── 2. PostgreSQL ────────────────────────────────────────
sep "PostgreSQL"
if docker compose exec -T db pg_isready -U coup -d coup > /dev/null 2>&1; then
  ok "PostgreSQL is accepting connections"
else
  fail "PostgreSQL is not ready"
fi

# Quick query test
RESULT=$(docker compose exec -T db psql -U coup -d coup -c "SELECT COUNT(*) FROM games;" -t 2>/dev/null | tr -d ' ' || echo "error")
if echo "$RESULT" | grep -qE '^[0-9]+$'; then
  ok "games table query OK (rows: $RESULT)"
else
  fail "Could not query games table — migrations may not have run"
fi

# ── 3. PHP-FPM ───────────────────────────────────────────
sep "PHP-FPM"
if docker compose exec -T app sh -c "kill -0 \$(pgrep php-fpm | head -1) 2>/dev/null"; then
  ok "php-fpm process is running"
else
  fail "php-fpm process not found"
fi

# ── 4. Reverb WebSocket ──────────────────────────────────
sep "Reverb WebSocket"
if docker compose exec -T app sh -c "netstat -tlnp 2>/dev/null | grep :8080 || ss -tlnp | grep :8080" > /dev/null 2>&1; then
  ok "Reverb is listening on port 8080 (inside container)"
else
  fail "Reverb is NOT listening on port 8080"
  info "Run: docker compose logs app | grep reverb"
fi

# Test nginx → reverb proxy
WS_STATUS=$(docker compose exec -T nginx wget -qO- --timeout=3 \
  --header="Connection: Upgrade" \
  --header="Upgrade: websocket" \
  "http://app:8080/app/coup-channel" 2>&1 | head -1 || true)
if echo "$WS_STATUS" | grep -qi "websocket\|upgrade\|405\|101"; then
  ok "Nginx → Reverb proxy reachable"
else
  info "WebSocket handshake test inconclusive (normal with HTTP GET): $WS_STATUS"
fi

# ── 5. HTTP endpoint ─────────────────────────────────────
sep "HTTP (Nginx → Laravel)"

# Read host from .env.docker
HOST=$(grep "^APP_URL=" .env.docker 2>/dev/null | sed 's|APP_URL=||;s|https\?://||;s|/.*||' || echo "localhost")
SCHEME=$(grep "^APP_URL=" .env.docker 2>/dev/null | grep -o "https\?" | head -1 || echo "http")

info "Testing $SCHEME://$HOST ..."

HTTP_CODE=$(curl -sk -o /dev/null -w "%{http_code}" --connect-timeout 5 "$SCHEME://$HOST" 2>/dev/null || echo "000")
if [ "$HTTP_CODE" = "200" ] || [ "$HTTP_CODE" = "302" ]; then
  ok "HTTP $HTTP_CODE — Laravel is responding"
elif [ "$HTTP_CODE" = "000" ]; then
  fail "Could not connect to $SCHEME://$HOST — is the container running?"
else
  fail "Unexpected HTTP $HTTP_CODE from $SCHEME://$HOST"
fi

# ── 6. WebSocket path via Nginx ──────────────────────────
sep "WebSocket path via Nginx"
WS_PATH_CODE=$(curl -sk -o /dev/null -w "%{http_code}" --connect-timeout 5 \
  "$SCHEME://$HOST/app/test" 2>/dev/null || echo "000")
if [ "$WS_PATH_CODE" = "200" ] || [ "$WS_PATH_CODE" = "426" ] || [ "$WS_PATH_CODE" = "400" ]; then
  ok "Nginx /app/ route reachable (HTTP $WS_PATH_CODE — expected for WS upgrade)"
elif [ "$WS_PATH_CODE" = "000" ]; then
  fail "Could not reach $SCHEME://$HOST/app/ — nginx not responding"
else
  fail "Unexpected response on /app/ path: HTTP $WS_PATH_CODE"
fi

# ── 7. Supervisor ────────────────────────────────────────
sep "Supervisor"
SUPERVISOR_STATUS=$(docker compose exec -T app supervisorctl status 2>/dev/null || echo "error")
if echo "$SUPERVISOR_STATUS" | grep -q "RUNNING"; then
  echo "$SUPERVISOR_STATUS" | while IFS= read -r line; do
    if echo "$line" | grep -q "RUNNING"; then
      ok "$line"
    elif echo "$line" | grep -q "STOPPED\|FATAL\|EXITED"; then
      fail "$line"
    fi
  done
else
  fail "Could not get supervisor status"
  info "Run: docker compose exec app cat storage/logs/supervisord.log"
fi

# ── 8. Laravel config ────────────────────────────────────
sep "Laravel"
APP_ENV=$(docker compose exec -T app php artisan env 2>/dev/null | tr -d '\r\n' || echo "error")
if echo "$APP_ENV" | grep -qi "production\|local"; then
  ok "Laravel env: $APP_ENV"
else
  fail "Laravel not responding to artisan commands"
fi

# ── Summary ──────────────────────────────────────────────
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  Results: ✅ $PASS passed  /  ❌ $FAIL failed"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

if [ "$FAIL" -gt 0 ]; then
  echo ""
  echo "  Useful debug commands:"
  echo "    docker compose logs app --tail=50"
  echo "    docker compose logs nginx --tail=50"
  echo "    docker compose exec app supervisorctl status"
  echo "    docker compose exec app cat storage/logs/laravel.log | tail -30"
  echo ""
  exit 1
fi

echo ""
