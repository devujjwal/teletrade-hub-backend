#!/usr/bin/env bash
set -euo pipefail

# Idempotent cron setup for TeleTrade backend on OCI/Linux.
# Usage:
#   bash bin/setup-oci-cron.sh /absolute/path/to/teletrade-hub-backend

APP_DIR="${1:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
LOG_DIR="${APP_DIR}/storage/logs"
SYNC_SCRIPT="${APP_DIR}/cli/sync-stock.php"
ORDER_SCRIPT="${APP_DIR}/cli/create-vendor-orders.php"

SYNC_SCHEDULE="${SYNC_SCHEDULE:-0 3 * * *}"
ORDER_SCHEDULE="${ORDER_SCHEDULE:-0 17 * * *}"

resolve_php_bin() {
  local candidates=(
    "${PHP_BIN:-}"
    "/usr/local/bin/ea-php83"
    "/usr/local/bin/php"
    "/usr/bin/php"
  )

  for candidate in "${candidates[@]}"; do
    if [ -n "${candidate}" ] && [ -x "${candidate}" ]; then
      echo "${candidate}"
      return 0
    fi
  done

  if command -v php >/dev/null 2>&1; then
    command -v php
    return 0
  fi

  return 1
}

PHP_BIN="$(resolve_php_bin || true)"
if [ -z "${PHP_BIN}" ]; then
  echo "ERROR: Could not find a PHP binary. Set PHP_BIN explicitly and re-run."
  exit 1
fi

if [ ! -f "${SYNC_SCRIPT}" ]; then
  echo "ERROR: Sync script not found: ${SYNC_SCRIPT}"
  exit 1
fi

if [ ! -f "${ORDER_SCRIPT}" ]; then
  echo "ERROR: Vendor order script not found: ${ORDER_SCRIPT}"
  exit 1
fi

mkdir -p "${LOG_DIR}"

SYNC_JOB="${SYNC_SCHEDULE} ${PHP_BIN} ${SYNC_SCRIPT} >> ${LOG_DIR}/cron-sync.log 2>&1 # teletrade_sync_job"
ORDER_JOB="${ORDER_SCHEDULE} ${PHP_BIN} ${ORDER_SCRIPT} >> ${LOG_DIR}/cron-vendor-orders.log 2>&1 # teletrade_vendor_orders_job"

CURRENT_CRON="$(crontab -l 2>/dev/null || true)"

FILTERED_CRON="$(printf '%s\n' "${CURRENT_CRON}" | grep -v 'teletrade_sync_job' | grep -v 'teletrade_vendor_orders_job' || true)"

{
  if [ -n "${FILTERED_CRON}" ]; then
    printf '%s\n' "${FILTERED_CRON}"
  fi
  printf '%s\n' "${SYNC_JOB}"
  printf '%s\n' "${ORDER_JOB}"
} | crontab -

echo "Cron installed successfully."
echo "PHP binary: ${PHP_BIN}"
echo "App directory: ${APP_DIR}"
echo
echo "Active cron entries:"
crontab -l | grep -E 'teletrade_sync_job|teletrade_vendor_orders_job' || true
echo
echo "Manual test commands:"
echo "${PHP_BIN} ${SYNC_SCRIPT}"
echo "${PHP_BIN} ${ORDER_SCRIPT}"
