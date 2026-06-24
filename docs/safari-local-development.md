# Safari Local Development

This milestone targets Chrome <-> Safari on macOS. It does not optimize around Chrome-to-Chrome sync, and Safari iOS is not required yet.

## Build the Safari Web Extension Bundle

```bash
cd /Users/jakub/Webdev/projekty-prywatne/browser-bridge/safari-extension
npm install
npm run build
```

The built Web Extension bundle is:

```text
/Users/jakub/Webdev/projekty-prywatne/browser-bridge/safari-extension/dist
```

## Create or Run the macOS Safari Wrapper

On macOS with Xcode installed, convert the built Web Extension bundle into a Safari Web Extension app:

```bash
xcrun safari-web-extension-converter /Users/jakub/Webdev/projekty-prywatne/browser-bridge/safari-extension/dist \
  --project-location /Users/jakub/Webdev/projekty-prywatne/browser-bridge/safari-extension/xcode \
  --app-name BrowserBridgeSafari \
  --bundle-identifier dev.browserbridge.safari
```

Then:

1. Open the generated Xcode project.
2. Select a local development signing team if Xcode asks.
3. Build and run the macOS app target.
4. Open Safari.
5. Go to Safari -> Settings -> Extensions.
6. Enable BrowserBridge Safari.
7. Open the BrowserBridge Safari options page.
8. Enter the BrowserBridge API URL, API token, and device name `Safari Mac`.
9. Click Save and register device.

Safari may ask for website access. Allow access for `browserbridge.test` and the pages you want to send as tabs.

## Local Backend

Laravel Herd should serve the backend at one of:

```text
http://browserbridge.test
https://browserbridge.test
```

Run migrations before connecting extensions:

```bash
cd /Users/jakub/Webdev/projekty-prywatne/browser-bridge
php artisan migrate
```

Make sure `.env` contains:

```dotenv
BROWSERBRIDGE_API_TOKEN=replace-with-a-long-random-token
```

## Chrome <-> Safari Test Path

1. Build Chrome extension: `cd chrome-extension && npm install && npm run build`.
2. Load `/Users/jakub/Webdev/projekty-prywatne/browser-bridge/chrome-extension/dist` in Chrome through `chrome://extensions` -> Load unpacked.
3. Build Safari extension: `cd safari-extension && npm install && npm run build`.
4. Convert/run the Safari extension in Xcode and enable it in Safari.
5. Connect Chrome as `Chrome PC` or `Chrome Mac`.
6. Connect Safari as `Safari Mac`.
7. Send the current tab from Chrome to Safari.
8. Open it in Safari from the incoming tab commands list.
9. Send the current tab from Safari to Chrome.
10. Open it in Chrome from the incoming tab commands list.
11. Enable history sync in Chrome.
12. Visit a few pages in Chrome.
13. Open Safari extension and search BrowserBridge History.
14. Add bookmarks in Chrome.
15. Run sync from the Chrome popup.
16. Open Safari extension and view BrowserBridge Bookmarks.
17. Search BrowserBridge Bookmarks and click a result to open it in Safari.

## Safari Limitations

Safari macOS support in this milestone focuses on cross-browser tab sending and shared BrowserBridge views.

- Native Safari bookmark writing is not implemented.
- Native Safari bookmark upload is not implemented.
- Native Safari history upload is not implemented.
- BrowserBridge Bookmarks are shown inside the extension UI from Chrome-uploaded snapshots.
- BrowserBridge History is shown inside the extension UI.
- Safari can search and open BrowserBridge History items uploaded by Chrome.
- Background polling may be less reliable in Safari than Chrome, so opening the popup and pressing Sync now is the fallback.
- Safari iOS support is a future milestone and may need different background execution and extension packaging choices.

BrowserBridge History is a shared history view inside the extension. Native Safari/Chrome history merging may be limited and is not guaranteed in this version.

BrowserBridge Bookmarks are shown inside the extension. Native Safari bookmark writing may be added later.

## Tab Sending Notes

The default incoming mode is Ask: received tabs appear in the popup and require Open or Dismiss. Auto-open is intentionally left for a later setting.

Chrome-to-Chrome is not the target flow for this milestone. Same-device tab commands are rejected unless `BROWSERBRIDGE_ALLOW_SAME_DEVICE_TAB_COMMANDS=true` is set for explicit debugging.
