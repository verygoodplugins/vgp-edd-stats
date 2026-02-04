#!/bin/bash

###############################################################################
# VGP EDD Stats - Get Remote Database Credentials
#
# Fetches the database credentials from the live site's wp-config.php
# so you can add them to your dev-config.php
#
# Usage: ./scripts/get-remote-credentials.sh
###############################################################################

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

# Load config
if [ -f "$SCRIPT_DIR/.env" ]; then
    while IFS='=' read -r key value; do
        [[ "$key" =~ ^#.*$ ]] && continue
        [[ -z "$key" ]] && continue
        value="${value%\"}"
        value="${value#\"}"
        if [ -z "${!key}" ]; then
            export "$key=$value"
        fi
    done < "$SCRIPT_DIR/.env"
fi

SSH_HOST="${LIVE_SSH:-}"
WP_PATH="${LIVE_WP_PATH:-}"

if [ -z "$SSH_HOST" ] || [ -z "$WP_PATH" ]; then
    echo "Error: Configure LIVE_SSH and LIVE_WP_PATH in scripts/.env"
    exit 1
fi

echo "Fetching credentials from $SSH_HOST..."
echo ""

# Get credentials from remote wp-config.php
CREDS=$(ssh "$SSH_HOST" "cd $WP_PATH && grep -E \"define\(.*'DB_\" wp-config.php | head -4")

DB_NAME=$(echo "$CREDS" | grep DB_NAME | sed "s/.*['\"]DB_NAME['\"],[[:space:]]*['\"]//;s/['\"].*//")
DB_USER=$(echo "$CREDS" | grep DB_USER | sed "s/.*['\"]DB_USER['\"],[[:space:]]*['\"]//;s/['\"].*//")
DB_PASS=$(echo "$CREDS" | grep DB_PASSWORD | sed "s/.*['\"]DB_PASSWORD['\"],[[:space:]]*['\"]//;s/['\"].*//")
DB_HOST=$(echo "$CREDS" | grep DB_HOST | sed "s/.*['\"]DB_HOST['\"],[[:space:]]*['\"]//;s/['\"].*//")

echo "Add these to your dev-config.php:"
echo ""
echo "define( 'VGP_EDD_DEV_DB_HOST', '127.0.0.1' );"
echo "define( 'VGP_EDD_DEV_DB_PORT', 3307 );"
echo "define( 'VGP_EDD_DEV_DB_NAME', '$DB_NAME' );"
echo "define( 'VGP_EDD_DEV_DB_USER', '$DB_USER' );"
echo "define( 'VGP_EDD_DEV_DB_PASSWORD', '$DB_PASS' );"
echo ""
echo "Remote MySQL host: $DB_HOST"
