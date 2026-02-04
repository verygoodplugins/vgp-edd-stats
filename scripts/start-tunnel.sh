#!/bin/bash

###############################################################################
# VGP EDD Stats - SSH Tunnel for Remote MySQL
#
# Creates an SSH tunnel to access the live MySQL database directly.
# Much simpler than syncing data locally - always uses fresh production data.
#
# Usage:
#   ./scripts/start-tunnel.sh          # Start tunnel (foreground)
#   ./scripts/start-tunnel.sh start    # Start tunnel (background)
#   ./scripts/start-tunnel.sh stop     # Stop background tunnel
#   ./scripts/start-tunnel.sh status   # Check if tunnel is running
#
###############################################################################

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PID_FILE="$SCRIPT_DIR/.tunnel.pid"

# Load config from .env
load_env() {
    local env_file="$SCRIPT_DIR/.env"
    if [ ! -f "$env_file" ]; then
        echo -e "${RED}Error: scripts/.env not found${NC}"
        echo "Copy scripts/.env.example to scripts/.env and configure it"
        exit 1
    fi
    
    # Source the env file (simple key=value format)
    while IFS='=' read -r key value; do
        # Skip comments and empty lines
        [[ "$key" =~ ^#.*$ ]] && continue
        [[ -z "$key" ]] && continue
        
        # Remove quotes from value
        value="${value%\"}"
        value="${value#\"}"
        value="${value%\'}"
        value="${value#\'}"
        
        # Export if not already set
        if [ -z "${!key}" ]; then
            export "$key=$value"
        fi
    done < "$env_file"
}

load_env

# Configuration
SSH_HOST="${LIVE_SSH:-}"
LOCAL_PORT="${TUNNEL_LOCAL_PORT:-3307}"
REMOTE_MYSQL_HOST="${REMOTE_MYSQL_HOST:-localhost}"
REMOTE_MYSQL_PORT="${REMOTE_MYSQL_PORT:-3306}"

if [ -z "$SSH_HOST" ]; then
    echo -e "${RED}Error: LIVE_SSH not configured in scripts/.env${NC}"
    exit 1
fi

check_tunnel() {
    if [ -f "$PID_FILE" ]; then
        local pid=$(cat "$PID_FILE")
        if ps -p "$pid" > /dev/null 2>&1; then
            return 0  # Running
        fi
    fi
    
    # Also check if port is in use
    if lsof -i ":$LOCAL_PORT" > /dev/null 2>&1; then
        return 0  # Port in use (tunnel likely running)
    fi
    
    return 1  # Not running
}

start_tunnel_foreground() {
    echo -e "${BLUE}Starting SSH tunnel to remote MySQL...${NC}"
    echo ""
    echo "  Local:  localhost:$LOCAL_PORT"
    echo "  Remote: $SSH_HOST → $REMOTE_MYSQL_HOST:$REMOTE_MYSQL_PORT"
    echo ""
    echo -e "${YELLOW}Press Ctrl+C to stop the tunnel${NC}"
    echo ""
    
    ssh -N -L "$LOCAL_PORT:$REMOTE_MYSQL_HOST:$REMOTE_MYSQL_PORT" "$SSH_HOST"
}

start_tunnel_background() {
    if check_tunnel; then
        echo -e "${YELLOW}Tunnel already running on port $LOCAL_PORT${NC}"
        return 0
    fi
    
    echo -e "${BLUE}Starting SSH tunnel in background...${NC}"
    
    # Start tunnel in background
    ssh -f -N -L "$LOCAL_PORT:$REMOTE_MYSQL_HOST:$REMOTE_MYSQL_PORT" "$SSH_HOST" \
        -o ExitOnForwardFailure=yes \
        -o ServerAliveInterval=60 \
        -o ServerAliveCountMax=3
    
    # Find and save PID
    sleep 1
    local pid=$(pgrep -f "ssh.*-L.*$LOCAL_PORT.*$SSH_HOST" | head -1)
    
    if [ -n "$pid" ]; then
        echo "$pid" > "$PID_FILE"
        echo -e "${GREEN}✓ Tunnel started (PID: $pid)${NC}"
        echo ""
        echo "  Local:  localhost:$LOCAL_PORT"
        echo "  Remote: $SSH_HOST → $REMOTE_MYSQL_HOST:$REMOTE_MYSQL_PORT"
        echo ""
        echo "Run './scripts/start-tunnel.sh stop' to close the tunnel"
    else
        echo -e "${RED}Failed to start tunnel${NC}"
        exit 1
    fi
}

stop_tunnel() {
    if [ -f "$PID_FILE" ]; then
        local pid=$(cat "$PID_FILE")
        if ps -p "$pid" > /dev/null 2>&1; then
            echo -e "${BLUE}Stopping tunnel (PID: $pid)...${NC}"
            kill "$pid" 2>/dev/null || true
            rm -f "$PID_FILE"
            echo -e "${GREEN}✓ Tunnel stopped${NC}"
            return 0
        fi
        rm -f "$PID_FILE"
    fi
    
    # Try to find and kill any matching tunnel process
    local pids=$(pgrep -f "ssh.*-L.*$LOCAL_PORT.*$SSH_HOST" 2>/dev/null || true)
    if [ -n "$pids" ]; then
        echo -e "${BLUE}Stopping tunnel processes...${NC}"
        echo "$pids" | xargs kill 2>/dev/null || true
        echo -e "${GREEN}✓ Tunnel stopped${NC}"
    else
        echo -e "${YELLOW}No tunnel running${NC}"
    fi
}

show_status() {
    if check_tunnel; then
        echo -e "${GREEN}✓ Tunnel is running on port $LOCAL_PORT${NC}"
        
        if [ -f "$PID_FILE" ]; then
            echo "  PID: $(cat "$PID_FILE")"
        fi
        
        # Test MySQL connection through tunnel
        if command -v mysql > /dev/null 2>&1; then
            echo ""
            echo -e "${BLUE}Testing MySQL connection...${NC}"
            if mysql -h 127.0.0.1 -P "$LOCAL_PORT" -u root -e "SELECT 1" > /dev/null 2>&1; then
                echo -e "${GREEN}✓ MySQL connection OK${NC}"
            else
                echo -e "${YELLOW}⚠ MySQL connection failed (credentials may differ)${NC}"
            fi
        fi
    else
        echo -e "${RED}✗ Tunnel is not running${NC}"
        echo ""
        echo "Start it with: ./scripts/start-tunnel.sh start"
    fi
}

# Main
case "${1:-}" in
    start)
        start_tunnel_background
        ;;
    stop)
        stop_tunnel
        ;;
    status)
        show_status
        ;;
    "")
        # No argument = foreground mode
        start_tunnel_foreground
        ;;
    *)
        echo "Usage: $0 [start|stop|status]"
        echo ""
        echo "  (no args)  Start tunnel in foreground (Ctrl+C to stop)"
        echo "  start      Start tunnel in background"
        echo "  stop       Stop background tunnel"
        echo "  status     Check if tunnel is running"
        exit 1
        ;;
esac
