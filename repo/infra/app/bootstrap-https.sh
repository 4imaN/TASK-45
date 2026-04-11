#!/bin/bash
set -e

CERT_DIR="/var/www/storage/app/private/runtime/certs"
mkdir -p "$CERT_DIR"

if [ ! -f "$CERT_DIR/server.crt" ]; then
    echo "Generating self-signed certificate..."
    openssl req -x509 -nodes -days 365 \
        -newkey rsa:2048 \
        -keyout "$CERT_DIR/server.key" \
        -out "$CERT_DIR/server.crt" \
        -subj "/CN=localhost/O=CampusResourcePlatform" \
        2>/dev/null
    echo "Certificate generated."
else
    echo "Certificate already exists."
fi
