# BrowserBridge Chromium Extension

## Commands

```bash
npm install
npm run build
```

Load unpacked extension path:

```text
/Users/jakub/Webdev/projekty-prywatne/browser-bridge/chrome-extension/dist
```

## Local Setup

1. Build the Laravel backend and set `BROWSERBRIDGE_API_TOKEN`.
2. Run `php artisan migrate`.
3. Open `chrome://extensions`.
4. Enable Developer mode.
5. Click Load unpacked and select `chrome-extension/dist`.
6. Open the BrowserBridge options page and enter:
   - API URL, for example `http://browserbridge.test`
   - API token
   - Device name
   - Browser name, defaults to Chrome
   - Platform, auto-filled when possible

This extension is the first full BrowserBridge data source. It is meant to pair with the Safari Web Extension for the current milestone, not to define a Chrome-to-Chrome product flow.

## Chrome Capabilities

- Register as a BrowserBridge device.
- Send the current tab to Safari.
- Receive and open tabs sent by Safari.
- Upload bookmark snapshots.
- Upload open-tab snapshots.
- Upload browsing history only after explicit opt-in.
- Display BrowserBridge Bookmarks and BrowserBridge History from the backend.

## Privacy

History sync is off by default. The extension does not request cookies or password/session/form-data permissions.

Browsing history can contain sensitive private information such as medical, financial, work-related, personal or adult websites. BrowserBridge never syncs passwords, cookies, login sessions or form data. Enable this only if you understand what browsing history sync means.
