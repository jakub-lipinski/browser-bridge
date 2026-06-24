# BrowserBridge

BrowserBridge is a production-oriented browser sync cloud built with Laravel. The first client target is a Chromium Manifest V3 extension, with Safari Web Extension support planned later.

The backend currently supports device registration, bookmark snapshots, tab snapshots, optional history upload, history search, and sending the current tab to a selected device.

BrowserBridge never syncs passwords, cookies, login sessions, localStorage, sessionStorage, or form data.

## Local Setup

```bash
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run build
```

Set a private API token in `.env`:

```dotenv
BROWSERBRIDGE_API_TOKEN=replace-with-a-long-random-token
```

Laravel Herd serves this project at:

```text
https://browserbridge.test/dashboard
```

## Migration Command

Run the BrowserBridge tables with:

```bash
php artisan migrate
```

For non-interactive deployment-style runs:

```bash
php artisan migrate --force
```

## API Examples

The API accepts either `Authorization: Bearer <token>` or `X-BrowserBridge-Token: <token>`.

```bash
export BASE="https://browserbridge.test"
export TOKEN="replace-with-a-long-random-token"
export SOURCE_DEVICE_UUID="11111111-1111-4111-8111-111111111111"
export TARGET_DEVICE_UUID="22222222-2222-4222-8222-222222222222"
export TAB_COMMAND_ID="1"
```

Register a device:

```bash
curl -k -X POST "$BASE/api/device/register" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "device_uuid": "11111111-1111-4111-8111-111111111111",
    "name": "Work Chrome",
    "browser": "chrome",
    "platform": "macos"
  }'
```

List devices:

```bash
curl -k "$BASE/api/devices" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json"
```

Upload a bookmark snapshot:

```bash
curl -k -X POST "$BASE/api/bookmarks/snapshot" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "device_uuid": "11111111-1111-4111-8111-111111111111",
    "items": [
      {"title": "Laravel", "url": "https://laravel.com/docs"},
      {"title": "Extensions", "url": "chrome://extensions"}
    ]
  }'
```

Upload a tab snapshot:

```bash
curl -k -X POST "$BASE/api/tabs/snapshot" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "device_uuid": "11111111-1111-4111-8111-111111111111",
    "tabs": [
      {"title": "BrowserBridge", "url": "https://example.com/browserbridge", "active": true}
    ]
  }'
```

List recent tab snapshots for a device:

```bash
curl -k "$BASE/api/tabs/snapshots?device_uuid=$SOURCE_DEVICE_UUID" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json"
```

Upload history batch. History sync must remain opt-in in clients; browsing history can contain sensitive private information.

```bash
curl -k -X POST "$BASE/api/history/batch" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "device_uuid": "11111111-1111-4111-8111-111111111111",
    "items": [
      {
        "url": "https://example.com/article",
        "title": "Example article",
        "visited_at": "2026-06-24T12:00:00Z"
      }
    ]
  }'
```

Search history:

```bash
curl -k "$BASE/api/history/search?device_uuid=$SOURCE_DEVICE_UUID&query=example&limit=10" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json"
```

Send a tab to another device:

```bash
curl -k -X POST "$BASE/api/tabs/send" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "source_device_uuid": "11111111-1111-4111-8111-111111111111",
    "target_device_uuid": "22222222-2222-4222-8222-222222222222",
    "url": "https://example.com/send-this-tab",
    "title": "Send this tab"
  }'
```

Fetch incoming tab commands:

```bash
curl -k "$BASE/api/tabs/incoming?device_uuid=$TARGET_DEVICE_UUID" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json"
```

Mark a tab command opened:

```bash
curl -k -X POST "$BASE/api/tabs/$TAB_COMMAND_ID/opened" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"device_uuid": "22222222-2222-4222-8222-222222222222"}'
```

Mark a tab command dismissed:

```bash
curl -k -X POST "$BASE/api/tabs/$TAB_COMMAND_ID/dismissed" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"device_uuid": "22222222-2222-4222-8222-222222222222"}'
```

## Privacy Guardrails

- History sync must be disabled by default in browser clients.
- History UI must warn that browsing history may contain sensitive private information.
- Browser extensions must not request permissions for cookies, passwords, sessions, localStorage, sessionStorage, or form data.
- Internal browser URLs such as `chrome://`, `edge://`, `about:`, and `file://` are ignored for snapshots and history, and rejected for send-tab commands.
- Payload columns are named `payload_json` and `encrypted_payload` so local JSON storage can later migrate to encrypted payload storage.

## Before Public Release

- Replace the single shared `.env` token with real multi-user authentication and device authorization.
- Encrypt browser data at rest and migrate reads/writes to `encrypted_payload`.
- Add per-user ownership to every sync table and dashboard query.
- Add rate limiting, request signing or token rotation, CORS policy, abuse monitoring, and audit logging.
- Build the Chromium extension and Safari Web Extension with explicit privacy permissions.
- Add history retention controls, deletion/export flows, and clearer consent UX.
- Add conflict resolution and deduplication for bookmark sync.
- Add production deployment configuration, backups, monitoring, and incident-response docs.

## Tests

```bash
php artisan test --compact
```
