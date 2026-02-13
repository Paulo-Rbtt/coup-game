#!/bin/sh
set -e

echo "🔒 Coup Nginx — SSL Setup"

SSL_DIR="/etc/nginx/ssl"
CERT_FILE="$SSL_DIR/cert.pem"
KEY_FILE="$SSL_DIR/key.pem"

mkdir -p "$SSL_DIR"

# ── Generate self-signed certificate if missing ──────────
if [ ! -f "$CERT_FILE" ] || [ ! -f "$KEY_FILE" ]; then
  echo "📜 Generating self-signed SSL certificate..."

  openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
    -keyout "$KEY_FILE" \
    -out "$CERT_FILE" \
    -subj "/C=BR/ST=State/L=City/O=Coup/CN=${SSL_DOMAIN:-localhost}" \
    -addext "subjectAltName=DNS:${SSL_DOMAIN:-localhost},DNS:www.${SSL_DOMAIN:-localhost},DNS:localhost"

  chmod 644 "$CERT_FILE"
  chmod 600 "$KEY_FILE"

  echo "✅ Self-signed certificate created"
  echo "   Valid for: ${SSL_DOMAIN:-localhost}"
  echo "   Expires: 365 days"
  echo ""
  echo "⚠️  IMPORTANT: Self-signed certificates will show security warnings in browsers."
  echo "   For production, use Let's Encrypt (see README.md)"
else
  echo "✅ SSL certificate found"
fi

echo "🚀 Starting Nginx..."
exec nginx -g 'daemon off;'
