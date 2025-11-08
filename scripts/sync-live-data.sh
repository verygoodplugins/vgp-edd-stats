#!/bin/bash

###############################################################################
# VGP EDD Stats - Live Data Sync Script
#
# Syncs live EDD data to local development database with anonymization.
#
# Usage: ./scripts/sync-live-data.sh
#
# Requirements:
# - SSH access to live site
# - mysql command line tools installed locally
# - Local MySQL running (Local WP default)
###############################################################################

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
LIVE_SSH="master_jsrfyuefqf@104.238.130.1"
LIVE_WP_PATH="applications/urjxzpmdrd/public_html"
LOCAL_DB_HOST="127.0.0.1"
LOCAL_DB_USER="root"
LOCAL_DB_PASS="root123!"
LOCAL_DEV_DB="vgp_edd_dev"
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PLUGIN_DIR="$(dirname "$SCRIPT_DIR")"
DATA_DIR="$PLUGIN_DIR/data"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
DUMP_FILE="$DATA_DIR/edd-dump-$TIMESTAMP.sql"

# Tables to sync (minimum set required by queries)
# Note: We include select WP core tables that queries join against
# to enable local analytics without hitting the main WP DB.
EDD_TABLES=(
    # Core EDD data
    "wp_edd_customers"
    "wp_edd_orders"
    "wp_edd_subscriptions"
    "wp_edd_licenses"
    "wp_edd_license_activations"
    "wp_edd_customermeta"
    "wp_edd_order_items"
    "wp_edd_customer_email_addresses"

    # WP core tables referenced by queries
    "wp_posts"
    "wp_postmeta"
    "wp_comments"
)

###############################################################################
# Helper Functions
###############################################################################

print_step() {
    echo -e "${BLUE}▸ $1${NC}"
}

print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

print_error() {
    echo -e "${RED}✗ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠ $1${NC}"
}

cleanup_on_error() {
    print_error "Error occurred. Cleaning up..."
    [ -f "$DUMP_FILE" ] && rm -f "$DUMP_FILE"
    exit 1
}

trap cleanup_on_error ERR

###############################################################################
# Main Script
###############################################################################

echo ""
echo "═══════════════════════════════════════════════════════════"
echo "  VGP EDD Stats - Live Data Sync"
echo "═══════════════════════════════════════════════════════════"
echo ""

# Step 1: Check local MySQL connection
print_step "Checking local MySQL connection..."
if ! mysql -h"$LOCAL_DB_HOST" -u"$LOCAL_DB_USER" -p"$LOCAL_DB_PASS" -e "SELECT 1" > /dev/null 2>&1; then
    print_error "Cannot connect to local MySQL"
    print_error "Host: $LOCAL_DB_HOST, User: $LOCAL_DB_USER"
    exit 1
fi
print_success "Local MySQL connection OK"

# Step 2: Check SSH connection
print_step "Checking SSH connection to live site..."
if ! ssh -q -o BatchMode=yes -o ConnectTimeout=5 "$LIVE_SSH" exit > /dev/null 2>&1; then
    print_error "Cannot connect to live site via SSH"
    print_error "SSH: $LIVE_SSH"
    print_error "Please ensure SSH key authentication is set up"
    exit 1
fi
print_success "SSH connection OK"

# Step 3: Get live database credentials from wp-config.php
print_step "Reading live database credentials..."
LIVE_DB_CREDS=$(ssh "$LIVE_SSH" "cd $LIVE_WP_PATH && grep -E \"define\(.*'DB_\" wp-config.php | head -4")

LIVE_DB_NAME=$(echo "$LIVE_DB_CREDS" | grep DB_NAME | sed "s/.*['\"]DB_NAME['\"],[[:space:]]*['\"]//;s/['\"].*//")
LIVE_DB_USER=$(echo "$LIVE_DB_CREDS" | grep DB_USER | sed "s/.*['\"]DB_USER['\"],[[:space:]]*['\"]//;s/['\"].*//")
LIVE_DB_PASS=$(echo "$LIVE_DB_CREDS" | grep DB_PASSWORD | sed "s/.*['\"]DB_PASSWORD['\"],[[:space:]]*['\"]//;s/['\"].*//")
LIVE_DB_HOST=$(echo "$LIVE_DB_CREDS" | grep DB_HOST | sed "s/.*['\"]DB_HOST['\"],[[:space:]]*['\"]//;s/['\"].*//")

if [ -z "$LIVE_DB_NAME" ]; then
    print_error "Could not extract live database credentials"
    exit 1
fi

print_success "Live database: $LIVE_DB_NAME on $LIVE_DB_HOST"

# Step 4: Export EDD tables from live site
print_step "Exporting EDD tables from live site..."

# Build table list for mysqldump
TABLE_LIST=""
EXISTING_TABLES=()

# Check which tables exist on live site
for table in "${EDD_TABLES[@]}"; do
    TABLE_EXISTS=$(ssh "$LIVE_SSH" "mysql -h'$LIVE_DB_HOST' -u'$LIVE_DB_USER' -p'$LIVE_DB_PASS' -D'$LIVE_DB_NAME' -e \"SHOW TABLES LIKE '$table'\" 2>/dev/null | grep -c '$table' || echo '0'")

    if [ "$TABLE_EXISTS" -ge "1" ]; then
        EXISTING_TABLES+=("$table")
        TABLE_LIST="$TABLE_LIST $table"
        print_success "Found table: $table"
    else
        print_warning "Table not found (skipping): $table"
    fi
done

if [ ${#EXISTING_TABLES[@]} -eq 0 ]; then
    print_error "No EDD tables found on live site"
    exit 1
fi

# Step 5: Export and download dump file (streaming directly via SSH)
print_step "Exporting and downloading database dump..."
mkdir -p "$DATA_DIR"

ssh "$LIVE_SSH" "mysqldump -h'$LIVE_DB_HOST' -u'$LIVE_DB_USER' -p'$LIVE_DB_PASS' '$LIVE_DB_NAME' $TABLE_LIST 2>/dev/null" > "$DUMP_FILE" || {
    print_error "Failed to export database dump"
    rm -f "$DUMP_FILE"
    exit 1
}

DUMP_SIZE=$(du -h "$DUMP_FILE" | cut -f1)
print_success "Database dump downloaded ($DUMP_SIZE)"

# Step 6: Create/recreate local development database
print_step "Creating local development database..."

mysql -h"$LOCAL_DB_HOST" -u"$LOCAL_DB_USER" -p"$LOCAL_DB_PASS" -e "DROP DATABASE IF EXISTS $LOCAL_DEV_DB" 2>/dev/null
mysql -h"$LOCAL_DB_HOST" -u"$LOCAL_DB_USER" -p"$LOCAL_DB_PASS" -e "CREATE DATABASE $LOCAL_DEV_DB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci" || {
    print_error "Failed to create database: $LOCAL_DEV_DB"
    exit 1
}

print_success "Database created: $LOCAL_DEV_DB"

# Step 7: Import dump into local database
print_step "Importing data into local database..."

mysql -h"$LOCAL_DB_HOST" -u"$LOCAL_DB_USER" -p"$LOCAL_DB_PASS" "$LOCAL_DEV_DB" < "$DUMP_FILE" || {
    print_error "Failed to import data"
    exit 1
}

print_success "Data imported successfully"

# Step 8: Run anonymization transforms
print_step "Anonymizing customer data for privacy..."

ANON_SQL="$SCRIPT_DIR/anonymize-data.sql"
if [ -f "$ANON_SQL" ]; then
    mysql -h"$LOCAL_DB_HOST" -u"$LOCAL_DB_USER" -p"$LOCAL_DB_PASS" "$LOCAL_DEV_DB" < "$ANON_SQL" || {
        print_warning "Anonymization script had some issues but continuing..."
    }
    print_success "Customer data anonymized"
else
    print_warning "Anonymization script not found: $ANON_SQL"
fi

# Step 9: Record sync completion
SYNC_LOG="$DATA_DIR/last-sync.log"
cat > "$SYNC_LOG" <<EOF
Last sync completed: $(date)
Dump file: $DUMP_FILE
Tables synced: ${EXISTING_TABLES[@]}
Dump size: $DUMP_SIZE
EOF

print_success "Sync log updated: $SYNC_LOG"

# Step 10: Clean up old dumps (keep last 3)
print_step "Cleaning up old dump files..."
cd "$DATA_DIR"
ls -t edd-dump-*.sql 2>/dev/null | tail -n +4 | xargs -r rm -f
REMAINING_DUMPS=$(ls -1 edd-dump-*.sql 2>/dev/null | wc -l)
print_success "Kept $REMAINING_DUMPS most recent dump(s)"

echo ""
echo "═══════════════════════════════════════════════════════════"
echo -e "${GREEN}  ✓ Sync completed successfully!${NC}"
echo "═══════════════════════════════════════════════════════════"
echo ""
echo "Development database: $LOCAL_DEV_DB"
echo "Tables synced: ${#EXISTING_TABLES[@]}"
echo "Dump file: $DUMP_FILE"
echo ""
echo "Next steps:"
echo "1. Copy dev-config-sample.php to dev-config.php (if not already done)"
echo "2. The plugin will automatically use $LOCAL_DEV_DB when dev-config.php exists"
echo "3. Build and test: npm run build"
echo ""
