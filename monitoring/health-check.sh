#!/bin/sh

# Health monitoring script for JDC services
NTFY_URL="${NTFY_URL:-https://jardindeschefs.ca/ntfy/jdc-server}"
CHECK_INTERVAL="${CHECK_INTERVAL:-60}"

# Function to send notification
send_notification() {
    local title="$1"
    local message="$2"
    local priority="${3:-default}"
    
    curl -H "Title: $title" \
         -H "Priority: $priority" \
         -H "Tags: warning" \
         -d "$message" \
         "$NTFY_URL"
}

# Function to check service health
check_service() {
    local service_name="$1"
    local url="$2"
    local expected_status="${3:-200}"
    
    response=$(curl -s -o /dev/null -w "%{http_code}" "$url" || echo "000")
    
    if [ "$response" != "$expected_status" ]; then
        return 1
    fi
    return 0
}

# Track service states
declare -A service_down_count
service_down_count[nextjs1]=0
service_down_count[nextjs2]=0
service_down_count[traefik]=0

echo "Starting health monitoring..."
send_notification "JDC Health Monitor Started" "Monitoring services every ${CHECK_INTERVAL}s" "low"

while true; do
    # Check Next.js Instance 1
    if ! check_service "NextJS-1" "http://nextjs-1:3000/api/health"; then
        service_down_count[nextjs1]=$((service_down_count[nextjs1] + 1))
        if [ ${service_down_count[nextjs1]} -eq 3 ]; then
            send_notification "⚠️ NextJS Instance 1 Down" "NextJS-1 has been unresponsive for 3 checks" "high"
        fi
    else
        if [ ${service_down_count[nextjs1]} -ge 3 ]; then
            send_notification "✅ NextJS Instance 1 Recovered" "NextJS-1 is back online" "default"
        fi
        service_down_count[nextjs1]=0
    fi
    
    # Check Next.js Instance 2
    if ! check_service "NextJS-2" "http://nextjs-2:3000/api/health"; then
        service_down_count[nextjs2]=$((service_down_count[nextjs2] + 1))
        if [ ${service_down_count[nextjs2]} -eq 3 ]; then
            send_notification "⚠️ NextJS Instance 2 Down" "NextJS-2 has been unresponsive for 3 checks" "high"
        fi
    else
        if [ ${service_down_count[nextjs2]} -ge 3 ]; then
            send_notification "✅ NextJS Instance 2 Recovered" "NextJS-2 is back online" "default"
        fi
        service_down_count[nextjs2]=0
    fi
    
    # Check Traefik
    if ! check_service "Traefik" "http://traefik:80" "404"; then
        service_down_count[traefik]=$((service_down_count[traefik] + 1))
        if [ ${service_down_count[traefik]} -eq 3 ]; then
            send_notification "🚨 Traefik Load Balancer Down" "Load balancer is unresponsive!" "urgent"
        fi
    else
        if [ ${service_down_count[traefik]} -ge 3 ]; then
            send_notification "✅ Traefik Recovered" "Load balancer is back online" "default"
        fi
        service_down_count[traefik]=0
    fi
    
    # Check if both Next.js instances are down
    if [ ${service_down_count[nextjs1]} -ge 3 ] && [ ${service_down_count[nextjs2]} -ge 3 ]; then
        send_notification "🚨 CRITICAL: All NextJS Instances Down" "Both Next.js instances are unresponsive!" "urgent"
    fi
    
    sleep "$CHECK_INTERVAL"
done
