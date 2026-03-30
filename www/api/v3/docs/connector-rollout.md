# API v3 Connector Rollout

## Feature Flags

Use the following environment variables in every connector during rollout:

- `OPENXE_TRANSPORT=db` or `api_v3`
- `OPENXE_API_V3_BASE_URL=http://localhost:8081/www/api/v3/index.php`
- `OPENXE_API_V3_TOKEN=oxev3_...`
- `OPENXE_API_V3_TIMEOUT=30`

`OPENXE_TRANSPORT=db` keeps the current direct database integration active.
`OPENXE_TRANSPORT=api_v3` switches the connector to the new HTTP API.

## Suggested Migration Order

1. `OpenXE-CAMT-Importer`
2. `OpenXE-PayPal-Connector`
3. `OpenXE-Verbindlichkeiten-KI`
4. `OpenXE-Shop-Connector`

This order starts with the lowest write-complexity connectors and leaves the
shop flow, which has the largest payload surface and business logic overlap,
for last.

## Idempotency

Send an `Idempotency-Key` header for all POST, PUT and PATCH requests that may
be retried by the connector runtime, especially:

- `POST /bank-transaction-imports`
- `POST /payables`
- `POST /sales-orders`
- `POST /files`

Recommended key shape:

`<connector>:<entity>:<stable-external-id>`

Examples:

- `camt:transaction-import:2026-03-30:account-5`
- `paypal:transaction-import:2026-03-30:paypal-main`
- `payables:invoice:vendor-42:RE-2026-000123`
- `shop:sales-order:shopify:1000451`

## Smoke Checklist

- Verify `GET /me` with the created Bearer token.
- Verify required reference-data endpoints for the connector.
- Run one create/import call with `OPENXE_TRANSPORT=api_v3`.
- Repeat the same call with the same `Idempotency-Key` and confirm the result is stable.
- Compare created records against the previous DB-based connector output.

## Fallback

If a connector-specific issue occurs during rollout, switch only that connector
back to `OPENXE_TRANSPORT=db` while keeping the others on `api_v3`.
