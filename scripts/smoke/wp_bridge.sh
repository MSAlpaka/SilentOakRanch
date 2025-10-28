#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
PROJECT_ROOT=$(cd "${SCRIPT_DIR}/../.." && pwd)

ENV_FILE=${ENV_FILE:-"${PROJECT_ROOT}/backend/.env.staging"}
BASE_URL=${BASE_URL:-"http://localhost:8000"}
STALL_UNIT_ID=${STALL_UNIT_ID:-""}
PAYMENT_STATUS=${PAYMENT_STATUS:-"paid"}
PAYMENT_REFERENCE=${PAYMENT_REFERENCE:-"PAYPAL-SANDBOX-REFERENCE"}
PAYMENT_AMOUNT=${PAYMENT_AMOUNT:-"75.00"}

load_env() {
    local env_file="$1"
    if [[ ! -f "$env_file" ]]; then
        return
    fi

    python3 - "$env_file" <<'PY'
import shlex
import sys
from pathlib import Path

env_path = Path(sys.argv[1])
if not env_path.exists():
    raise SystemExit(0)

for raw in env_path.read_text().splitlines():
    raw = raw.strip()
    if not raw or raw.startswith('#') or '=' not in raw:
        continue
    key, value = raw.split('=', 1)
    key = key.strip()
    value = value.strip()
    if value and value[0] in {'"', "'"} and value[-1] == value[0]:
        value = value[1:-1]
    print(f'{key}={shlex.quote(value)}')
PY
}

export_env_from_file() {
    local env_file="$1"
    while IFS= read -r line; do
        if [[ -z "$line" ]]; then
            continue
        fi
        export "$line"
    done < <(load_env "$env_file")
}

export_env_from_file "$ENV_FILE"

for var in WP_BRIDGE_KEY WP_BRIDGE_SECRET WORDPRESS_WEBHOOK_TOKEN; do
    if [[ -z "${!var:-}" ]]; then
        echo "[error] Environment variable $var is required." >&2
        echo "        Provide it via the environment or in $ENV_FILE." >&2
        exit 1
    fi
done

resolve_stall_unit_id() {
    if [[ -n "$STALL_UNIT_ID" ]]; then
        echo "$STALL_UNIT_ID"
        return
    fi

    local response
    if ! response=$(curl -fsS "${BASE_URL}/api/stall-units"); then
        echo ""; return
    fi

    python3 - <<'PY'
import json
import sys

data = json.load(sys.stdin)
if isinstance(data, list):
    for item in data:
        if isinstance(item, dict) and 'id' in item:
            print(item['id'])
            break
PY <<<"$response"
}

STALL_UNIT_ID=$(resolve_stall_unit_id)
if [[ -z "$STALL_UNIT_ID" ]]; then
    echo "[error] Unable to determine stall unit id. Set STALL_UNIT_ID explicitly." >&2
    exit 1
fi

export STALL_UNIT_ID PAYMENT_STATUS PAYMENT_REFERENCE PAYMENT_AMOUNT

build_request_payload() {
    local path="$1"
    python3 - "$path" <<'PY'
import datetime as dt
import json
import os
import sys
import uuid
import hmac
import hashlib

path = sys.argv[1]
secret = os.environ['WP_BRIDGE_SECRET']
now = dt.datetime.utcnow()
timestamp = now.strftime('%Y-%m-%dT%H:%M:%SZ')
start = now + dt.timedelta(minutes=15)
end = start + dt.timedelta(minutes=45)

if path == '/api/wp/bookings':
    payload = {
        'uuid': str(uuid.uuid4()),
        'resource': 'arena',
        'slot_start': start.strftime('%Y-%m-%dT%H:%M:%SZ'),
        'slot_end': end.strftime('%Y-%m-%dT%H:%M:%SZ'),
        'price': '75.00',
        'status': 'confirmed',
        'stall_unit_id': int(os.environ['STALL_UNIT_ID']),
        'email': 'sandbox.rider@example.com',
        'name': 'Sandbox Rider',
        'horse_name': 'Testing Breeze',
    }
else:
    payload = {
        'booking_id': int(os.environ['BOOKING_ID']),
        'status': os.environ.get('PAYMENT_STATUS', 'paid'),
        'payment_reference': os.environ.get('PAYMENT_REFERENCE', ''),
        'amount': os.environ.get('PAYMENT_AMOUNT', ''),
    }

body = json.dumps(payload, separators=(',', ':'))
message = '|'.join([
    'POST',
    path,
    timestamp,
    body,
])
signature = hmac.new(secret.encode(), message.encode(), hashlib.sha256).hexdigest()
print(timestamp)
print(body)
print(signature)
PY
}

send_signed_request() {
    local path="$1"
    mapfile -t payload_data < <(build_request_payload "$path")
    local timestamp="${payload_data[0]}"
    local request_body="${payload_data[1]}"
    local signature="${payload_data[2]}"

    local tmp_response
    tmp_response=$(mktemp)
    trap 'rm -f "$tmp_response"' RETURN

    local status
    status=$(curl \
        -sS \
        -o "$tmp_response" \
        -w '%{http_code}' \
        -X POST "${BASE_URL}${path}" \
        -H 'Content-Type: application/json' \
        -H 'Accept: application/json' \
        -H "Authorization: Bearer ${WORDPRESS_WEBHOOK_TOKEN}" \
        -H "X-SOR-Key: ${WP_BRIDGE_KEY}" \
        -H "X-SOR-Date: ${timestamp}" \
        -H "X-SOR-Signature: ${signature}" \
        --data "$request_body")

    local response_body
    response_body=$(cat "$tmp_response")
    rm -f "$tmp_response"
    trap - RETURN

    if [[ "$status" != "200" && "$status" != "201" ]]; then
        echo "[error] Request to ${path} failed with status ${status}" >&2
        printf '%s\n' "$response_body" >&2
        exit 1
    fi

    printf '%s' "$response_body"
}

response=$(send_signed_request '/api/wp/bookings')
BOOKING_ID=$(python3 - <<'PY'
import json
import sys

data = json.load(sys.stdin)
if not data.get('ok'):
    raise SystemExit('Booking creation failed')
print(data['id'])
PY <<<"$response")
export BOOKING_ID

payment_response_file=$(mktemp)
trap 'rm -f "${payment_response_file}"' EXIT
send_signed_request '/api/wp/payments/confirm' > "${payment_response_file}"
python3 - <<'PY'
import json
import sys

response = json.load(open(sys.argv[1]))
if not response.get('ok'):
    raise SystemExit('Payment confirmation failed')
print('Booking ID:', response['id'])
print('Status:', response['status'])
PY "${payment_response_file}"
