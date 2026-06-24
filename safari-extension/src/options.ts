import './modules/initSafariAdapter';
import '../../chrome-extension/src/styles.css';
import { connectDevice, defaultDeviceName, detectPlatform } from '../../chrome-extension/src/modules/device';
import { getConfig, saveConfig } from '../../chrome-extension/src/modules/storage';
import type { ExtensionConfig } from '../../chrome-extension/src/modules/types';

const form = document.querySelector<HTMLFormElement>('#options-form');
const apiUrlInput = document.querySelector<HTMLInputElement>('#api-url');
const apiTokenInput = document.querySelector<HTMLInputElement>('#api-token');
const deviceNameInput = document.querySelector<HTMLInputElement>('#device-name');
const browserNameInput = document.querySelector<HTMLInputElement>('#browser-name');
const platformInput = document.querySelector<HTMLInputElement>('#platform');
const connectButton = document.querySelector<HTMLButtonElement>('#connect-button');
const statusElement = document.querySelector<HTMLDivElement>('#status');

function requireElement<T>(element: T | null): T {
  if (!element) {
    throw new Error('BrowserBridge Safari options page did not initialize.');
  }

  return element;
}

function setStatus(message: string): void {
  requireElement(statusElement).textContent = message;
}

async function readFormConfig(): Promise<ExtensionConfig> {
  const currentConfig = await getConfig();

  return {
    ...currentConfig,
    apiUrl: requireElement(apiUrlInput).value.trim(),
    apiToken: requireElement(apiTokenInput).value.trim(),
    deviceName: requireElement(deviceNameInput).value.trim() || defaultDeviceName(),
    browserName: 'safari',
    platform: detectPlatform(),
    sync: {
      ...currentConfig.sync,
      bookmarks: false,
      history: false,
    },
  };
}

async function saveFromForm(): Promise<ExtensionConfig> {
  const config = await readFormConfig();

  await saveConfig(config);

  return config;
}

async function initialize(): Promise<void> {
  const config = await getConfig();

  requireElement(apiUrlInput).value = config.apiUrl;
  requireElement(apiTokenInput).value = config.apiToken;
  requireElement(deviceNameInput).value = config.deviceName || defaultDeviceName();
  requireElement(browserNameInput).value = 'safari';
  requireElement(platformInput).value = detectPlatform();
}

requireElement(form).addEventListener('submit', (event) => {
  event.preventDefault();

  void saveFromForm()
    .then(() => setStatus('Settings saved.'))
    .catch((error: unknown) => setStatus(error instanceof Error ? error.message : 'Unable to save settings.'));
});

requireElement(connectButton).addEventListener('click', () => {
  void saveFromForm()
    .then((config) => connectDevice(config))
    .then((config) => setStatus(`Connected as ${config.deviceName}. Device UUID: ${config.deviceUuid}`))
    .catch((error: unknown) => setStatus(error instanceof Error ? error.message : 'Unable to connect.'));
});

void initialize().catch((error: unknown) => {
  setStatus(error instanceof Error ? error.message : 'Unable to initialize options.');
});
