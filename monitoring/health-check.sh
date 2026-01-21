#!/bin/bash

# Health monitoring script for JDC services
NTFY_URL="${NTFY_URL:-https://jardindeschefs.ca/ntfy/jdc-server}"
NTFY_TOKEN="${NTFY_TOKEN}"
CHECK_INTERVAL="${CHECK_INTERVAL:-60}"

# Function to send notification
send_notification() {
  local title="$1"
  local message="$2"
  local priority="${3:-default}"

  # Add Authorization header if token exists
  if [ -n "$NTFY_TOKEN" ]; then
    curl -H "Title: $title" \
      -H "Priority: $priority" \
      -H "Tags: warning" \
      -H "Authorization: Bearer $NTFY_TOKEN" \
      -d "$message" \
      "$NTFY_URL"
  else
    curl -H "Title: $title" \
      -H "Priority: $priority" \
      -H "Tags: warning" \
      -d "$message" \
      "$NTFY_URL"
  fi
  }

# Returns: status_code:response_time_ms
# Example: "200:45" or "000:0" on failure
check_service_with_timing() {
  local url="$1"
  local max_time="${2:-5}"
  
  local start=$(date +%s%3N)
  local status=$(curl -s -o /dev/null -w "%{http_code}" --max-time "$max_time" "$url" 2>/dev/null || echo "000")
  local end=$(date +%s%3N)
  local duration=$((end - start))
  
  echo "${status}:${duration}"
}

# Track service states
declare -A service_down_count
declare -A service_slow_count
service_down_count[nextjs1]=0
service_down_count[nextjs2]=0
service_down_count[traefik]=0
service_slow_count[nextjs1]=0
service_slow_count[nextjs2]=0

# Thresholds
MAX_RESPONSE_TIME=3000  # 3 seconds = something is wrong
SLOW_THRESHOLD=1000      # 1 second = degraded performance

echo "Starting health monitoring..."
send_notification "JDC Health Monitor Started" "Monitoring services every ${CHECK_INTERVAL}s" "low"

while true; do

  # Check Next.js Instance 1
  result=$(check_service_with_timing "http://nextjs-1:3000/api/health" 5)
  status="${result%%:*}"
  response_time="${result##*:}"

  if [ "$status" != "200" ]; then
    service_down_count[nextjs1]=$((service_down_count[nextjs1] + 1))
    service_slow_count[nextjs1]=0
    if [ ${service_down_count[nextjs1]} -eq 3 ]; then
      send_notification "⚠️ NextJS-1 Down" "Status: $status (3 consecutive failures)" "high"
    fi
  elif [ "$response_time" -gt "$MAX_RESPONSE_TIME" ]; then
    # Responding but VERY slow - likely under attack or overloaded
    service_slow_count[nextjs1]=$((service_slow_count[nextjs1] + 1))
    if [ ${service_slow_count[nextjs1]} -eq 3 ]; then
      send_notification "🔥 NextJS-1 Critical Slowdown" "Response time: ${response_time}ms (3 consecutive)" "urgent"
    fi
  elif [ "$response_time" -gt "$SLOW_THRESHOLD" ]; then
    # Degraded but not critical
    service_slow_count[nextjs1]=$((service_slow_count[nextjs1] + 1))
    if [ ${service_slow_count[nextjs1]} -eq 5 ]; then
      send_notification "⚠️ NextJS-1 Slow" "Response time: ${response_time}ms (5 consecutive)" "default"
    fi
  else
    # Healthy
    if [ ${service_down_count[nextjs1]} -ge 3 ]; then
      send_notification "✅ NextJS-1 Recovered" "Back online, response: ${response_time}ms" "default"
    elif [ ${service_slow_count[nextjs1]} -ge 5 ]; then
      send_notification "✅ NextJS-1 Performance Recovered" "Response time: ${response_time}ms" "default"
    fi
    service_down_count[nextjs1]=0
    service_slow_count[nextjs1]=0
  fi

  # Check Next.js Instance 2 (same logic)
  result=$(check_service_with_timing "http://nextjs-2:3000/api/health" 5)
  status="${result%%:*}"
  response_time="${result##*:}"

  if [ "$status" != "200" ]; then
    service_down_count[nextjs2]=$((service_down_count[nextjs2] + 1))
    service_slow_count[nextjs2]=0
    if [ ${service_down_count[nextjs2]} -eq 3 ]; then
      send_notification "⚠️ NextJS-2 Down" "Status: $status (3 consecutive failures)" "high"
    fi
  elif [ "$response_time" -gt "$MAX_RESPONSE_TIME" ]; then
    service_slow_count[nextjs2]=$((service_slow_count[nextjs2] + 1))
    if [ ${service_slow_count[nextjs2]} -eq 3 ]; then
      send_notification "🔥 NextJS-2 Critical Slowdown" "Response time: ${response_time}ms (3 consecutive)" "urgent"
    fi
  elif [ "$response_time" -gt "$SLOW_THRESHOLD" ]; then
    service_slow_count[nextjs2]=$((service_slow_count[nextjs2] + 1))
    if [ ${service_slow_count[nextjs2]} -eq 5 ]; then
      send_notification "⚠️ NextJS-2 Slow" "Response time: ${response_time}ms (5 consecutive)" "default"
    fi
  else
    if [ ${service_down_count[nextjs2]} -ge 3 ]; then
      send_notification "✅ NextJS-2 Recovered" "Back online, response: ${response_time}ms" "default"
    elif [ ${service_slow_count[nextjs2]} -ge 5 ]; then
      send_notification "✅ NextJS-2 Performance Recovered" "Response time: ${response_time}ms" "default"
    fi
    service_down_count[nextjs2]=0
    service_slow_count[nextjs2]=0
  fi

  # Check Traefik
  result=$(check_service_with_timing "http://traefik:6969/ping" 5)
  status="${result%%:*}"

  if [ "$status" != "200" ]; then
    service_down_count[traefik]=$((service_down_count[traefik] + 1))
    if [ ${service_down_count[traefik]} -eq 3 ]; then
      send_notification "🚨 Traefik Down" "Load balancer unresponsive!" "urgent"
    fi
  else
    if [ ${service_down_count[traefik]} -ge 3 ]; then
      send_notification "✅ Traefik Recovered" "Load balancer back online" "default"
    fi
    service_down_count[traefik]=0
  fi

  # Critical: Both instances down or critically slow
  if { [ ${service_down_count[nextjs1]} -ge 3 ] || [ ${service_slow_count[nextjs1]} -ge 3 ]; } && \
    { [ ${service_down_count[nextjs2]} -ge 3 ] || [ ${service_slow_count[nextjs2]} -ge 3 ]; }; then
      send_notification "🚨 CRITICAL: Site Degraded" "Both instances down or critically slow!" "urgent"
  fi

  sleep "$CHECK_INTERVAL"
done
