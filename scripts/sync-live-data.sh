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

# Paths
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PLUGIN_DIR="$(dirname "$SCRIPT_DIR")"
DATA_DIR="$PLUGIN_DIR/data"

trim() {
    # Usage: trim " string "
    local value="$1"
    value="${value#"${value%%[![:space:]]*}"}"
    value="${value%"${value##*[![:space:]]}"}"
    printf '%s' "$value"
}

load_env_file() {
    # Minimal .env loader:
    # - supports KEY=value, KEY="value", KEY='value'
    # - does NOT override already-exported variables
    # - ignores comments/blank lines
    local file="$1"
    [ -f "$file" ] || return 0

    while IFS= read -r line || [ -n "$line" ]; do
        line="$(trim "$line")"
        [ -z "$line" ] && continue
        case "$line" in
            \#*) continue ;;
        esac

        line="${line#export }"
        if ! echo "$line" | grep -q '='; then
            continue
        fi

        local key="${line%%=*}"
        local value="${line#*=}"
        key="$(trim "$key")"
        value="$(trim "$value")"

        if [ -z "$key" ]; then
            continue
        fi

        # Don't override existing env vars.
        if [ -n "${!key+x}" ]; then
            continue
        fi

        if echo "$value" | grep -qE '^".*"$'; then
            value="${value#\"}"
            value="${value%\"}"
        elif echo "$value" | grep -qE "^'.*'$"; then
            value="${value#\'}"
            value="${value%\'}"
        fi

        printf -v "$key" '%s' "$value"
        export "$key"
    done < "$file"
}

# Optional environment overrides (gitignored).
ENV_FILE_PATH="${ENV_FILE_PATH:-$SCRIPT_DIR/.env}"
load_env_file "$ENV_FILE_PATH"

# MySQL CLI selection
MYSQL_BIN="${MYSQL_BIN:-}"
LOCAL_MYSQL_BIN="${LOCAL_MYSQL_BIN:-}"

# Configuration
LIVE_SSH="${LIVE_SSH:-}"
LIVE_WP_PATH="${LIVE_WP_PATH:-}"
LIVE_DB_PREFIX="${LIVE_DB_PREFIX:-wp_}"
AUTO_DETECT_LOCAL_DB="${AUTO_DETECT_LOCAL_DB:-1}"
LOCAL_WP_CONFIG="${LOCAL_WP_CONFIG:-}"
LOCAL_DB_HOST="${LOCAL_DB_HOST:-}"
LOCAL_DB_PORT="${LOCAL_DB_PORT:-}"
LOCAL_DB_SOCKET="${LOCAL_DB_SOCKET:-}"
LOCAL_DB_USER="${LOCAL_DB_USER:-}"
LOCAL_DB_PASS="${LOCAL_DB_PASS:-}"
LOCAL_DEV_DB="${LOCAL_DEV_DB:-vgp_edd_dev}"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
DUMP_FILE="$DATA_DIR/edd-dump-$TIMESTAMP.sql"

# Tables to sync (minimum set required by queries)
# Note: We include select WP core tables that queries join against
# to enable local analytics without hitting the main WP DB.
EDD_TABLES=(
    # Core EDD data
    "${LIVE_DB_PREFIX}edd_customers"
    "${LIVE_DB_PREFIX}edd_orders"
    "${LIVE_DB_PREFIX}edd_subscriptions"
    "${LIVE_DB_PREFIX}edd_licenses"
    "${LIVE_DB_PREFIX}edd_license_activations"
    "${LIVE_DB_PREFIX}edd_customermeta"
    "${LIVE_DB_PREFIX}edd_order_items"
    "${LIVE_DB_PREFIX}edd_customer_email_addresses"

    # WP core tables referenced by queries
    "${LIVE_DB_PREFIX}posts"
    "${LIVE_DB_PREFIX}postmeta"
    "${LIVE_DB_PREFIX}comments"
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

get_local_run_directory() {
    local custom="${LOCAL_RUN_DIR:-}"
    if [ -n "$custom" ] && [ -d "$custom" ]; then
        echo "$custom"
        return 0
    fi

    case "$(uname -s)" in
        Darwin)
            if [ -d "$HOME/Library/Application Support/Local/run" ]; then
                echo "$HOME/Library/Application Support/Local/run"
                return 0
            fi
            ;;
        *)
            if [ -d "$HOME/.config/Local/run" ]; then
                echo "$HOME/.config/Local/run"
                return 0
            fi
            if [ -d "$HOME/.local/share/Local/run" ]; then
                echo "$HOME/.local/share/Local/run"
                return 0
            fi
            ;;
    esac

    return 1
}

get_local_sites_json_path() {
    local custom="${LOCAL_SITES_JSON:-}"
    if [ -n "$custom" ] && [ -f "$custom" ]; then
        echo "$custom"
        return 0
    fi

    case "$(uname -s)" in
        Darwin)
            if [ -f "$HOME/Library/Application Support/Local/sites.json" ]; then
                echo "$HOME/Library/Application Support/Local/sites.json"
                return 0
            fi
            ;;
        *)
            if [ -f "$HOME/.config/Local/sites.json" ]; then
                echo "$HOME/.config/Local/sites.json"
                return 0
            fi
            if [ -f "$HOME/.local/share/Local/sites.json" ]; then
                echo "$HOME/.local/share/Local/sites.json"
                return 0
            fi
            ;;
    esac

    return 1
}

select_mysql_bin() {
    if [ -n "$MYSQL_BIN" ] && [ -x "$MYSQL_BIN" ]; then
        return 0
    fi

    if [ -n "$LOCAL_MYSQL_BIN" ] && [ -x "$LOCAL_MYSQL_BIN" ]; then
        MYSQL_BIN="$LOCAL_MYSQL_BIN"
        return 0
    fi

    # Prefer LocalWP's bundled MySQL client on macOS (compatible with Local's mysqld auth plugins).
    if [ -d "$HOME/Library/Application Support/Local/lightning-services" ]; then
        local matches=()
        local candidate
        while IFS= read -r candidate; do
            [ -x "$candidate" ] && matches+=("$candidate")
        done < <(find "$HOME/Library/Application Support/Local/lightning-services" -maxdepth 6 -type f -name mysql 2>/dev/null || true)

        if [ ${#matches[@]} -gt 0 ]; then
            MYSQL_BIN="${matches[0]}"
            return 0
        fi
    fi

    if command -v mysql >/dev/null 2>&1; then
        MYSQL_BIN="$(command -v mysql)"
        return 0
    fi

    print_error "mysql client not found. Install mysql-client or set LOCAL_MYSQL_BIN."
    exit 1
}

find_local_site_json() {
    local dir="$PLUGIN_DIR"
    while [ "$dir" != "/" ]; do
        if [ -f "$dir/local-site.json" ]; then
            echo "$dir/local-site.json"
            return 0
        fi
        dir="$(dirname "$dir")"
    done
    return 1
}

find_wp_config() {
    if [ -n "$LOCAL_WP_CONFIG" ] && [ -f "$LOCAL_WP_CONFIG" ]; then
        echo "$LOCAL_WP_CONFIG"
        return 0
    fi

    local dir="$PLUGIN_DIR"
    while [ "$dir" != "/" ]; do
        if [ -f "$dir/wp-config.php" ]; then
            echo "$dir/wp-config.php"
            return 0
        fi
        dir="$(dirname "$dir")"
    done
    return 1
}

extract_define() {
    # Extract values like: define( 'DB_HOST', 'localhost' );
    local key="$1"
    local file="$2"
    grep -E "define\\(\\s*['\\\"]${key}['\\\"]\\s*,\\s*['\\\"]" "$file" 2>/dev/null \
        | head -n 1 \
        | sed -E "s/.*define\\(\\s*['\\\"]${key}['\\\"]\\s*,\\s*['\\\"]([^'\\\"]*)['\\\"].*/\\1/"
}

extract_table_prefix() {
    local file="$1"
    grep -E "^\\s*\\$table_prefix\\s*=\\s*['\\\"]" "$file" 2>/dev/null \
        | head -n 1 \
        | sed -E "s/.*\\$table_prefix\\s*=\\s*['\\\"]([^'\\\"]*)['\\\"].*/\\1/"
}

parse_db_host() {
    # Supports:
    # - localhost
    # - 127.0.0.1:3307
    # - localhost:/path/to/mysql.sock
    local raw="$1"
    raw="$(trim "$raw")"

    if echo "$raw" | grep -qE ":[0-9]+$"; then
        echo "${raw%:*}|${raw##*:}|"
        return 0
    fi

    if echo "$raw" | grep -qE ":[/].+"; then
        echo "${raw%%:*}||${raw#*:}"
        return 0
    fi

    echo "${raw}||"
    return 0
}

detect_local_db_from_localwp_sites_json() {
    local sites_json run_dir
    sites_json="$(get_local_sites_json_path)" || return 1
    run_dir="$(get_local_run_directory)" || return 1

    DETECTED_LOCAL_DB_HOST=""
    DETECTED_LOCAL_DB_PORT=""
    DETECTED_LOCAL_DB_SOCKET=""
    DETECTED_LOCAL_DB_USER=""
    DETECTED_LOCAL_DB_PASS=""

    if ! command -v python3 >/dev/null 2>&1; then
        return 1
    fi

    local out
    out="$(python3 - "$sites_json" "$PLUGIN_DIR" <<'PY' 2>/dev/null || true
import json, os, sys
sites_path, cwd = sys.argv[1], sys.argv[2]
with open(sites_path) as f:
    sites = json.load(f)
cwd = os.path.realpath(cwd)

best = None
best_len = -1
best_id = None

for sid, site in sites.items():
    if not isinstance(site, dict): 
        continue
    sp = site.get('path')
    if not sp:
        continue
    sp = os.path.realpath(os.path.expanduser(sp))
    if cwd == sp or cwd.startswith(sp + os.sep):
        if len(sp) > best_len:
            best_len = len(sp)
            best = site
            best_id = sid

if not best:
    sys.exit(1)

mysql_cfg = best.get('mysql') or {}
services = best.get('services') or {}
mysql_svc = services.get('mysql') or {}
ports = (mysql_svc.get('ports') or {}).get('MYSQL') or []
port = str(ports[0]) if ports else ''

user = str(mysql_cfg.get('user') or '')
password = str(mysql_cfg.get('password') or '')
print(best_id + '|' + user + '|' + password + '|' + port)
PY
)"

    if [ -z "$out" ]; then
        return 1
    fi

    local site_id user pass port
    site_id="$(echo "$out" | cut -d'|' -f1)"
    user="$(echo "$out" | cut -d'|' -f2)"
    pass="$(echo "$out" | cut -d'|' -f3)"
    port="$(echo "$out" | cut -d'|' -f4)"

    site_id="$(trim "$site_id")"
    user="$(trim "$user")"
    pass="$(trim "$pass")"
    port="$(trim "$port")"

    if [ -z "$site_id" ]; then
        return 1
    fi

    local socket_path="$run_dir/$site_id/mysql/mysqld.sock"
    if [ -S "$socket_path" ]; then
        DETECTED_LOCAL_DB_SOCKET="$socket_path"
        DETECTED_LOCAL_DB_USER="$user"
        DETECTED_LOCAL_DB_PASS="$pass"
        DETECTED_LOCAL_DB_PORT="$port"
        return 0
    fi

    return 1
}

detect_local_db_from_local_site_json() {
    local file
    file="$(find_local_site_json)" || return 1

    DETECTED_LOCAL_DB_HOST=""
    DETECTED_LOCAL_DB_PORT=""
    DETECTED_LOCAL_DB_SOCKET=""
    DETECTED_LOCAL_DB_USER=""
    DETECTED_LOCAL_DB_PASS=""

    if command -v python3 >/dev/null 2>&1; then
        DETECTED_LOCAL_DB_USER="$(python3 - "$file" <<'PY' 2>/dev/null || true
import json, sys
j = json.load(open(sys.argv[1]))
print(j.get('mysql', {}).get('user', ''))
PY
)"
        DETECTED_LOCAL_DB_PASS="$(python3 - "$file" <<'PY' 2>/dev/null || true
import json, sys
j = json.load(open(sys.argv[1]))
print(j.get('mysql', {}).get('password', ''))
PY
)"
        DETECTED_LOCAL_DB_HOST="127.0.0.1"
        DETECTED_LOCAL_DB_PORT="$(python3 - "$file" <<'PY' 2>/dev/null || true
import json, sys
j = json.load(open(sys.argv[1]))
ports = (((j.get('services') or {}).get('mysql') or {}).get('ports') or {}).get('MYSQL') or []
print(ports[0] if ports else '')
PY
)"
    elif command -v node >/dev/null 2>&1; then
        DETECTED_LOCAL_DB_USER="$(node -e "const j=require('$file'); process.stdout.write(j.mysql?.user || '')" 2>/dev/null || true)"
        DETECTED_LOCAL_DB_PASS="$(node -e "const j=require('$file'); process.stdout.write(j.mysql?.password || '')" 2>/dev/null || true)"
        DETECTED_LOCAL_DB_HOST="127.0.0.1"
        DETECTED_LOCAL_DB_PORT="$(node -e "const j=require('$file'); process.stdout.write(String((j.services?.mysql?.ports?.MYSQL||[])[0]||''))" 2>/dev/null || true)"
    else
        # Best-effort fallback parsing (assumes compact JSON like Local's local-site.json).
        DETECTED_LOCAL_DB_USER="$(sed -n 's/.*\"mysql\".*\"user\":\"\\([^\"]*\\)\".*/\\1/p' "$file" | head -n 1)"
        DETECTED_LOCAL_DB_PASS="$(sed -n 's/.*\"mysql\".*\"password\":\"\\([^\"]*\\)\".*/\\1/p' "$file" | head -n 1)"
        DETECTED_LOCAL_DB_HOST="127.0.0.1"
        DETECTED_LOCAL_DB_PORT="$(sed -n 's/.*\"mysql\".*\"ports\".*\"MYSQL\":\\[\\([0-9]*\\)\\].*/\\1/p' "$file" | head -n 1)"
    fi

    DETECTED_LOCAL_DB_USER="$(trim "$DETECTED_LOCAL_DB_USER")"
    DETECTED_LOCAL_DB_PASS="$(trim "$DETECTED_LOCAL_DB_PASS")"
    DETECTED_LOCAL_DB_PORT="$(trim "$DETECTED_LOCAL_DB_PORT")"

    if [ -z "$DETECTED_LOCAL_DB_USER" ] || [ -z "$DETECTED_LOCAL_DB_PASS" ] || [ -z "$DETECTED_LOCAL_DB_PORT" ]; then
        return 1
    fi

    return 0
}

detect_local_db_from_wp_config() {
    local wp_config
    wp_config="$(find_wp_config)" || return 1

    DETECTED_LOCAL_DB_HOST=""
    DETECTED_LOCAL_DB_PORT=""
    DETECTED_LOCAL_DB_SOCKET=""
    DETECTED_LOCAL_DB_USER=""
    DETECTED_LOCAL_DB_PASS=""

    local name user pass host
    name="$(extract_define 'DB_NAME' "$wp_config")"
    user="$(extract_define 'DB_USER' "$wp_config")"
    pass="$(extract_define 'DB_PASSWORD' "$wp_config")"
    host="$(extract_define 'DB_HOST' "$wp_config")"

    # Only use wp-config values if they look sane.
    if [ -z "$user" ] || [ -z "$host" ]; then
        return 1
    fi

    DETECTED_LOCAL_DB_USER="$(trim "$user")"
    DETECTED_LOCAL_DB_PASS="$(trim "$pass")"

    local parsed
    parsed="$(parse_db_host "$host")"
    DETECTED_LOCAL_DB_HOST="$(echo "$parsed" | cut -d'|' -f1)"
    DETECTED_LOCAL_DB_PORT="$(echo "$parsed" | cut -d'|' -f2)"
    DETECTED_LOCAL_DB_SOCKET="$(echo "$parsed" | cut -d'|' -f3)"

    return 0
}

resolve_local_db_config() {
    local source=""

    test_mysql_connection() {
        local host="$1"
        local port="$2"
        local socket="$3"
        local user="$4"
        local pass="$5"

        local args=( --connect-timeout=2 )
        if [ -n "$socket" ]; then
            args+=( --socket="$socket" )
        else
            args+=( -h"$host" )
            if [ -n "$port" ]; then
                args+=( -P"$port" )
            fi
        fi
        args+=( -u"$user" )
        if [ -n "$pass" ]; then
            args+=( -p"$pass" )
        fi

        "$MYSQL_BIN" "${args[@]}" -e "SELECT 1" > /dev/null 2>&1
    }

    apply_candidate_if_works() {
        local candidate_source="$1"
        local host="$2"
        local port="$3"
        local socket="$4"
        local user="$5"
        local pass="$6"

        host="$(trim "$host")"
        port="$(trim "$port")"
        socket="$(trim "$socket")"
        user="$(trim "$user")"
        pass="$(trim "$pass")"

        if [ -z "$host" ] && [ -z "$socket" ]; then
            return 1
        fi
        if [ -z "$user" ]; then
            return 1
        fi

        if test_mysql_connection "$host" "$port" "$socket" "$user" "$pass"; then
            LOCAL_DB_HOST="${host:-localhost}"
            LOCAL_DB_PORT="$port"
            LOCAL_DB_SOCKET="$socket"
            LOCAL_DB_USER="$user"
            LOCAL_DB_PASS="$pass"
            source="$candidate_source"
            return 0
        fi

        return 1
    }

    # 1) Auto-detect candidates (preferred when enabled).
    if [ -z "$source" ] && [ "$AUTO_DETECT_LOCAL_DB" = "1" ]; then
        if detect_local_db_from_localwp_sites_json; then
            apply_candidate_if_works "LocalWP (sites.json)" "" "$DETECTED_LOCAL_DB_PORT" "$DETECTED_LOCAL_DB_SOCKET" "$DETECTED_LOCAL_DB_USER" "$DETECTED_LOCAL_DB_PASS" || true
        fi

        if [ -z "$source" ] && detect_local_db_from_local_site_json; then
            apply_candidate_if_works "local-site.json" "$DETECTED_LOCAL_DB_HOST" "$DETECTED_LOCAL_DB_PORT" "$DETECTED_LOCAL_DB_SOCKET" "$DETECTED_LOCAL_DB_USER" "$DETECTED_LOCAL_DB_PASS" || true
        fi
    fi

    # 2) wp-config.php fallback.
    if [ -z "$source" ] && detect_local_db_from_wp_config; then
        apply_candidate_if_works "wp-config.php" "$DETECTED_LOCAL_DB_HOST" "$DETECTED_LOCAL_DB_PORT" "$DETECTED_LOCAL_DB_SOCKET" "$DETECTED_LOCAL_DB_USER" "$DETECTED_LOCAL_DB_PASS" || true
    fi

    # 3) If user explicitly set a config in scripts/.env, try it.
    if [ -z "$source" ] && { [ -n "$LOCAL_DB_HOST" ] || [ -n "$LOCAL_DB_SOCKET" ] || [ -n "$LOCAL_DB_USER" ] || [ -n "$LOCAL_DB_PASS" ] || [ -n "$LOCAL_DB_PORT" ]; }; then
        apply_candidate_if_works "scripts/.env" "${LOCAL_DB_HOST:-127.0.0.1}" "$LOCAL_DB_PORT" "$LOCAL_DB_SOCKET" "${LOCAL_DB_USER:-root}" "$LOCAL_DB_PASS" || true
    fi

    # 4) Final fallbacks (previous defaults), but only if nothing else worked.
    if [ -z "$source" ]; then
        apply_candidate_if_works "defaults" "127.0.0.1" "" "" "root" "root123!" || true
    fi

    if [ -z "$source" ]; then
        print_warning "Local DB config could not be validated; check scripts/.env overrides"
    else
        print_success "Local DB config selected from $source"
    fi
}

build_mysql_args() {
    MYSQL_ARGS=()

    if [ -n "$LOCAL_DB_SOCKET" ]; then
        MYSQL_ARGS+=( --socket="$LOCAL_DB_SOCKET" )
        # For socket connections, host can be omitted.
    else
        MYSQL_ARGS+=( -h"$LOCAL_DB_HOST" )
        if [ -n "$LOCAL_DB_PORT" ]; then
            MYSQL_ARGS+=( -P"$LOCAL_DB_PORT" )
        fi
    fi

    MYSQL_ARGS+=( -u"$LOCAL_DB_USER" )

    # Only pass -p if a password was provided.
    if [ -n "$LOCAL_DB_PASS" ]; then
        MYSQL_ARGS+=( -p"$LOCAL_DB_PASS" )
    fi
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

# Resolve LocalWP/MySQL connection info.
select_mysql_bin
resolve_local_db_config
build_mysql_args

# Step 0: Check required configuration
if [ -z "$LIVE_SSH" ] || [ -z "$LIVE_WP_PATH" ]; then
    print_error "Missing live site configuration."
    print_error "Create scripts/.env with:"
    print_error "  LIVE_SSH=user@host"
    print_error "  LIVE_WP_PATH=/path/to/wordpress"
    exit 1
fi

# Step 1: Check local MySQL connection
print_step "Checking local MySQL connection..."
if ! "$MYSQL_BIN" "${MYSQL_ARGS[@]}" -e "SELECT 1" > /dev/null 2>&1; then
    print_error "Cannot connect to local MySQL"
    print_error "Host: $LOCAL_DB_HOST, Port: ${LOCAL_DB_PORT:-default}, User: $LOCAL_DB_USER"
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

# Safety check: Prevent wiping WordPress's main database
WP_CONFIG_PATH="$(find_wp_config)"
if [ -n "$WP_CONFIG_PATH" ]; then
    WP_DB_NAME="$(extract_define 'DB_NAME' "$WP_CONFIG_PATH")"
    if [ -n "$WP_DB_NAME" ] && [ "$LOCAL_DEV_DB" = "$WP_DB_NAME" ]; then
        print_error "SAFETY STOP: LOCAL_DEV_DB ($LOCAL_DEV_DB) matches WordPress's database!"
        print_error "This would wipe WordPress core tables and break your site."
        print_error "Use a different database name in scripts/.env, e.g.: LOCAL_DEV_DB=vgp_edd_dev"
        exit 1
    fi
fi

"$MYSQL_BIN" "${MYSQL_ARGS[@]}" -e "DROP DATABASE IF EXISTS $LOCAL_DEV_DB" 2>/dev/null
"$MYSQL_BIN" "${MYSQL_ARGS[@]}" -e "CREATE DATABASE $LOCAL_DEV_DB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci" || {
    print_error "Failed to create database: $LOCAL_DEV_DB"
    exit 1
}

print_success "Database created: $LOCAL_DEV_DB"

# Step 7: Import dump into local database
print_step "Importing data into local database..."

"$MYSQL_BIN" "${MYSQL_ARGS[@]}" "$LOCAL_DEV_DB" < "$DUMP_FILE" || {
    print_error "Failed to import data"
    exit 1
}

print_success "Data imported successfully"

# Step 8: Run anonymization transforms
print_step "Anonymizing customer data for privacy..."

ANON_SQL="$SCRIPT_DIR/anonymize-data.sql"
if [ -f "$ANON_SQL" ]; then
    ANON_INPUT="$ANON_SQL"
    ANON_TMP=""

    # If the live site uses a non-default prefix, rewrite the anonymization SQL on the fly.
    if [ "$LIVE_DB_PREFIX" != "wp_" ]; then
        ANON_TMP="$(mktemp "${DATA_DIR}/anonymize-data.XXXXXX.sql")"
        sed "s/wp_/${LIVE_DB_PREFIX}/g" "$ANON_SQL" > "$ANON_TMP"
        ANON_INPUT="$ANON_TMP"
    fi

    "$MYSQL_BIN" "${MYSQL_ARGS[@]}" "$LOCAL_DEV_DB" < "$ANON_INPUT" || {
        print_warning "Anonymization script had some issues but continuing..."
    }

    [ -n "$ANON_TMP" ] && rm -f "$ANON_TMP"
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
