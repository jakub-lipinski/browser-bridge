# BrowserBridge

BrowserBridge is a production-oriented browser sync cloud built with Laravel. This milestone targets Chrome <-> Safari on macOS through a Laravel backend, with Chrome as the first full browser-data source and Safari as the first real cross-browser target.

The backend currently supports device registration, bookmark snapshots, tab snapshots, optional history upload, shared BrowserBridge history search, and sending the current tab to a selected device.

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
BROWSERBRIDGE_MAX_DEVICES=10
BROWSERBRIDGE_MAX_BOOKMARK_SNAPSHOT_PAYLOAD_BYTES=1048576
BROWSERBRIDGE_MAX_TAB_SNAPSHOT_PAYLOAD_BYTES=524288
BROWSERBRIDGE_MAX_HISTORY_BATCH_SIZE=500
BROWSERBRIDGE_MAX_PENDING_TAB_COMMANDS_PER_TARGET=100
```

Laravel Herd serves this project at:

```text
https://browserbridge.test/dashboard
```

The local API URL for extensions is usually one of:

```text
http://browserbridge.test
https://browserbridge.test
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

The API accepts only `Authorization: Bearer <token>`.

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
curl -k "$BASE/api/devices?device_uuid=$SOURCE_DEVICE_UUID" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json"
```

List bookmark snapshots:

```bash
curl -k "$BASE/api/bookmarks/snapshots?device_uuid=$TARGET_DEVICE_UUID" \
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
    "history_sync_enabled": true,
    "items": [
      {
        "url": "https://example.com/article",
        "title": "Example article",
        "visited_at": "2026-06-24T12:00:00Z"
      }
    ]
  }'
```

Search shared BrowserBridge history:

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
- Internal browser URLs such as `chrome://`, `edge://`, `brave://`, `about:`, `file://`, `view-source:`, `devtools://`, and `javascript:` are ignored for snapshots and history, and rejected for send-tab commands.
- Payload columns are named `payload_json` and `encrypted_payload` so local JSON storage can later migrate to encrypted payload storage.

## Before Public Release

- Replace the single shared `.env` token with real multi-user authentication and device authorization.
- Encrypt browser data at rest and migrate reads/writes to `encrypted_payload`.
- Add per-user ownership to every sync table and dashboard query.
- Add rate limiting, request signing or token rotation, CORS policy, abuse monitoring, and audit logging.
- Build and review the Chromium extension and Safari Web Extension with explicit privacy permissions.
- Add history retention controls, deletion/export flows, and clearer consent UX.
- Add conflict resolution and deduplication for bookmark sync.
- Add production deployment configuration, backups, monitoring, and incident-response docs.

## Tests

```bash
php artisan test --compact
```

## Chrome <-> Safari Manual Test Path

This milestone intentionally targets Chrome <-> Safari, not Chrome <-> Chrome. A second Chromium profile can still be useful for troubleshooting, but it is not the product flow.

1. Run the Laravel backend locally through Herd at `http://browserbridge.test` or `https://browserbridge.test`.
2. Run `php artisan migrate`.
3. Build the Chromium extension.
4. Load `/Users/jakub/Webdev/projekty-prywatne/browser-bridge/chrome-extension/dist` in Chrome through `chrome://extensions` -> Load unpacked.
5. Build the Safari Web Extension bundle.
6. Convert/run it in Xcode and enable it in Safari.
7. Connect Chrome as `Chrome PC` or `Chrome Mac`.
8. Connect Safari as `Safari Mac`.
9. Send the current tab from Chrome to Safari, then open it in Safari.
10. Send the current tab from Safari to Chrome, then open it in Chrome.
11. Enable history sync in Chrome, visit a few pages, then search BrowserBridge History in Safari.
12. Add bookmarks in Chrome, run sync, then view BrowserBridge Bookmarks in Safari.

## Chromium Extension

The first local Chromium extension lives in `chrome-extension`.

Install extension dependencies:

```bash
cd chrome-extension
npm install
```

Build the unpacked extension:

```bash
npm run build
```

Load it in Chrome:

1. Open `chrome://extensions`.
2. Enable Developer mode.
3. Click Load unpacked.
4. Select this directory:

```text
/Users/jakub/Webdev/projekty-prywatne/browser-bridge/chrome-extension/dist
```

Connect it to Laravel:

1. Make sure the Laravel backend has `BROWSERBRIDGE_API_TOKEN` set and migrations are run.
2. Open the BrowserBridge extension options page.
3. Enter the API URL, for example `http://browserbridge.test` or `https://browserbridge.test`.
4. Enter the same API token from `.env`.
5. Set a device name and click Save and register device.

Chrome capabilities in this milestone:

- Register as a BrowserBridge device.
- Send the current tab to Safari.
- Receive and open tabs sent by Safari.
- Upload open-tab snapshots.
- Upload bookmark snapshots.
- Upload browsing history only after explicit opt-in.
- Display BrowserBridge Bookmarks and BrowserBridge History from the backend.

## Safari Web Extension

The macOS Safari Web Extension bundle lives in `safari-extension`.

Install extension dependencies:

```bash
cd safari-extension
npm install
```

Build the web extension bundle:

```bash
npm run build
```

Prepared bundle path:

```text
/Users/jakub/Webdev/projekty-prywatne/browser-bridge/safari-extension/dist
```

Safari capabilities in this milestone:

- Register as a BrowserBridge device with browser `safari` and platform `macos`.
- Send the current active tab to Chrome when Safari exposes the tab URL/title.
- Receive and open tab commands from Chrome.
- Display BrowserBridge Bookmarks uploaded by Chrome.
- Display and search BrowserBridge History uploaded by Chrome.
- Show disabled/limited states for native Safari bookmark/history sync.

See [docs/safari-local-development.md](/Users/jakub/Webdev/projekty-prywatne/browser-bridge/docs/safari-local-development.md) for Xcode and Safari setup.

## BrowserBridge-Only Views

BrowserBridge History is a shared history view inside the extension. Native Safari/Chrome history merging may be limited and is not guaranteed in this version.

BrowserBridge Bookmarks are shown inside the extension. Native Safari bookmark writing may be added later.
