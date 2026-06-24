import './modules/initChromiumAdapter';
import './styles.css';
import {
  createBookmarkBackup,
  createBookmarkSyncProfile,
  fetchBookmarks,
  fetchBookmarkSyncProfiles,
  fetchDevices,
  previewBookmarkSyncProfile,
  runBookmarkSyncProfile,
  updateBookmarkSyncProfile,
} from './modules/apiClient';
import {
  applyNativeBookmarkSync,
  exportNativeBookmarkBackup,
  previewNativeBookmarkSync,
} from './modules/bookmarkSyncModes';
import { connectDevice, defaultDeviceName, detectPlatform } from './modules/device';
import { getBrowserAdapter } from './modules/browserAdapter';
import { getConfig, saveConfig } from './modules/storage';
import type {
  BookmarkSyncDirection,
  BookmarkSyncMode,
  BookmarkSyncPreviewData,
  BookmarkSyncProfilePayload,
  BookmarkSyncProfileResource,
  BookmarkSyncTargetScope,
  DeviceResource,
  ExtensionConfig,
} from './modules/types';

const form = document.querySelector<HTMLFormElement>('#options-form');
const apiUrlInput = document.querySelector<HTMLInputElement>('#api-url');
const apiTokenInput = document.querySelector<HTMLInputElement>('#api-token');
const deviceNameInput = document.querySelector<HTMLInputElement>('#device-name');
const browserNameInput = document.querySelector<HTMLInputElement>('#browser-name');
const platformInput = document.querySelector<HTMLInputElement>('#platform');
const connectButton = document.querySelector<HTMLButtonElement>('#connect-button');
const statusElement = document.querySelector<HTMLDivElement>('#status');
const bookmarkSyncProfileSelect = document.querySelector<HTMLSelectElement>('#bookmark-sync-profile');
const bookmarkSyncSourceSelect = document.querySelector<HTMLSelectElement>('#bookmark-sync-source');
const bookmarkSyncTargetSelect = document.querySelector<HTMLSelectElement>('#bookmark-sync-target');
const bookmarkSyncModeSelect = document.querySelector<HTMLSelectElement>('#bookmark-sync-mode');
const bookmarkSyncDirectionSelect = document.querySelector<HTMLSelectElement>('#bookmark-sync-direction');
const bookmarkSyncScopeSelect = document.querySelector<HTMLSelectElement>('#bookmark-sync-scope');
const bookmarkSyncSelectedFolderInput = document.querySelector<HTMLInputElement>('#bookmark-sync-selected-folder');
const bookmarkSyncAutoSelect = document.querySelector<HTMLSelectElement>('#bookmark-sync-auto');
const bookmarkSyncSaveButton = document.querySelector<HTMLButtonElement>('#bookmark-sync-save');
const bookmarkSyncPreviewButton = document.querySelector<HTMLButtonElement>('#bookmark-sync-preview');
const bookmarkSyncRunButton = document.querySelector<HTMLButtonElement>('#bookmark-sync-run');
const bookmarkSyncStatusElement = document.querySelector<HTMLDivElement>('#bookmark-sync-status');

let devices: DeviceResource[] = [];
let profiles: BookmarkSyncProfileResource[] = [];
let selectedProfile: BookmarkSyncProfileResource | null = null;
let lastPreview: BookmarkSyncPreviewData | null = null;

function requireElement<T>(element: T | null): T {
  if (!element) {
    throw new Error('BrowserBridge options page did not initialize.');
  }

  return element;
}

function setStatus(message: string): void {
  requireElement(statusElement).textContent = message;
}

function setBookmarkSyncStatus(message: string): void {
  requireElement(bookmarkSyncStatusElement).textContent = message;
}

async function readFormConfig(): Promise<ExtensionConfig> {
  const currentConfig = await getConfig();

  return {
    ...currentConfig,
    apiUrl: requireElement(apiUrlInput).value.trim(),
    apiToken: requireElement(apiTokenInput).value.trim(),
    deviceName: requireElement(deviceNameInput).value.trim() || defaultDeviceName(),
    browserName: requireElement(browserNameInput).value.trim() || 'Chrome',
    platform: requireElement(platformInput).value.trim() || detectPlatform(),
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
  requireElement(browserNameInput).value = config.browserName || 'Chrome';
  requireElement(platformInput).value = config.platform || detectPlatform();
  await loadBookmarkSyncState(config);
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
    .then(async (config) => {
      setStatus(`Connected as ${config.deviceName}. Device UUID: ${config.deviceUuid}`);
      await loadBookmarkSyncState(config);
    })
    .catch((error: unknown) => setStatus(error instanceof Error ? error.message : 'Unable to connect.'));
});

function renderDeviceOptions(select: HTMLSelectElement, selectedId?: number): void {
  select.textContent = '';

  devices.forEach((device) => {
    const option = document.createElement('option');
    option.value = String(device.id);
    option.textContent = `${device.name} (${device.browser} on ${device.platform})`;
    option.selected = device.id === selectedId;
    select.append(option);
  });
}

function renderProfileOptions(): void {
  const select = requireElement(bookmarkSyncProfileSelect);
  select.textContent = '';

  const newOption = document.createElement('option');
  newOption.value = '';
  newOption.textContent = 'New profile';
  select.append(newOption);

  profiles.forEach((profile) => {
    const option = document.createElement('option');
    option.value = String(profile.id);
    option.textContent = profile.name;
    option.selected = selectedProfile?.id === profile.id;
    select.append(option);
  });
}

function fillProfileForm(profile: BookmarkSyncProfileResource | null, config: ExtensionConfig): void {
  const currentDevice = devices.find((device) => device.uuid === config.deviceUuid);
  const sourceDevice = profile?.source_device_id || devices.find((device) => device.uuid !== config.deviceUuid)?.id || currentDevice?.id;
  const targetDevice = profile?.target_device_id || currentDevice?.id || devices[0]?.id;

  renderDeviceOptions(requireElement(bookmarkSyncSourceSelect), sourceDevice);
  renderDeviceOptions(requireElement(bookmarkSyncTargetSelect), targetDevice);

  requireElement(bookmarkSyncModeSelect).value = profile?.mode || 'safe_folder';
  requireElement(bookmarkSyncDirectionSelect).value = profile?.direction || 'source_to_target';
  requireElement(bookmarkSyncScopeSelect).value = profile?.target_scope || 'browserbridge_folder';
  requireElement(bookmarkSyncSelectedFolderInput).value = profile?.selected_target_folder_id || '';
  requireElement(bookmarkSyncAutoSelect).value = profile?.auto_sync_interval_minutes ? String(profile.auto_sync_interval_minutes) : '';
  lastPreview = null;
}

async function loadBookmarkSyncState(config?: ExtensionConfig): Promise<void> {
  config = config ?? await getConfig();

  if (!config.apiUrl || !config.apiToken || !config.deviceUuid) {
    setBookmarkSyncStatus('Register this device before creating bookmark sync profiles.');
    return;
  }

  try {
    [devices, profiles] = await Promise.all([
      fetchDevices(config),
      fetchBookmarkSyncProfiles(config),
    ]);
    selectedProfile = profiles[0] || null;
    renderProfileOptions();
    fillProfileForm(selectedProfile, config);
    setBookmarkSyncStatus(profiles.length > 0 ? 'Bookmark sync profiles loaded.' : 'Create a profile, preview it, then run manually.');
  } catch (error) {
    setBookmarkSyncStatus(error instanceof Error ? error.message : 'Could not load bookmark sync profiles.');
  }
}

function profilePayload(): BookmarkSyncProfilePayload {
  const sourceDeviceId = Number(requireElement(bookmarkSyncSourceSelect).value);
  const targetDeviceId = Number(requireElement(bookmarkSyncTargetSelect).value);
  const mode = requireElement(bookmarkSyncModeSelect).value as BookmarkSyncMode;
  const direction = requireElement(bookmarkSyncDirectionSelect).value as BookmarkSyncDirection;
  const targetScope = requireElement(bookmarkSyncScopeSelect).value as BookmarkSyncTargetScope;
  const interval = requireElement(bookmarkSyncAutoSelect).value;
  const sourceDevice = devices.find((device) => device.id === sourceDeviceId);
  const targetDevice = devices.find((device) => device.id === targetDeviceId);

  return {
    name: `${sourceDevice?.name || 'Source'} → ${targetDevice?.name || 'Target'}`,
    source_device_id: sourceDeviceId,
    target_device_id: targetDeviceId,
    mode,
    direction,
    target_scope: targetScope,
    selected_target_folder_id: requireElement(bookmarkSyncSelectedFolderInput).value.trim() || null,
    auto_sync_enabled: interval !== '',
    auto_sync_interval_minutes: interval === '' ? null : Number(interval),
    is_active: true,
  };
}

async function saveBookmarkSyncProfile(): Promise<BookmarkSyncProfileResource> {
  const config = await saveFromForm();
  const payload = profilePayload();

  selectedProfile = selectedProfile
    ? await updateBookmarkSyncProfile(config, selectedProfile.id, payload)
    : await createBookmarkSyncProfile(config, payload);

  profiles = await fetchBookmarkSyncProfiles(config);
  renderProfileOptions();
  fillProfileForm(selectedProfile, config);

  return selectedProfile;
}

function formatPreview(prefix: string, preview: BookmarkSyncPreviewData): string {
  return `${prefix}. Add: ${preview.add_count} | Update: ${preview.update_count} | Move: ${preview.move_count} | Delete: ${preview.delete_count} | Skip: ${preview.skip_count}`;
}

async function previewProfile(): Promise<BookmarkSyncPreviewData> {
  const config = await saveFromForm();
  const profile = await saveBookmarkSyncProfile();
  const sourceBookmarks = await fetchBookmarks(config, profile.source_device_id);
  const [serverPreview, nativePreview] = await Promise.all([
    previewBookmarkSyncProfile(config, profile.id),
    previewNativeBookmarkSync(profile, sourceBookmarks),
  ]);

  lastPreview = {
    ...nativePreview,
    run_id: serverPreview.run_id,
    warnings: [...serverPreview.warnings, ...nativePreview.warnings].filter((warning, index, warnings) => warnings.indexOf(warning) === index),
  };
  setBookmarkSyncStatus(formatPreview('Preview ready', lastPreview));

  return lastPreview;
}

async function runProfile(): Promise<void> {
  const config = await saveFromForm();
  const profile = await saveBookmarkSyncProfile();
  const preview = lastPreview || await previewProfile();

  if (profile.mode === 'mirror') {
    const confirmed = confirm('Mirror may delete or move bookmarks in the selected destination scope. BrowserBridge will create a backup first. Continue?');

    if (!confirmed) {
      setBookmarkSyncStatus('Mirror cancelled.');
      return;
    }
  }

  const sourceBookmarks = await fetchBookmarks(config, profile.source_device_id);
  const backupPayload = profile.mode === 'mirror' ? await exportNativeBookmarkBackup() : undefined;

  if (profile.mode === 'mirror' && backupPayload) {
    await createBookmarkBackup(config, backupPayload, preview.run_id);
  }

  const result = await applyNativeBookmarkSync(profile, sourceBookmarks);
  const run = await runBookmarkSyncProfile(config, profile.id, {
    confirm_mirror: profile.mode === 'mirror',
    backup_created: profile.mode === 'mirror',
    operation_log: result.operationLog,
    result: {
      native_preview: result.preview,
      backup_recorded: profile.mode === 'mirror' && Boolean(backupPayload),
    },
  });

  setBookmarkSyncStatus(`Run complete. Added: ${run.added_count} | Updated: ${run.updated_count} | Moved: ${run.moved_count} | Deleted: ${run.deleted_count} | Skipped: ${run.skipped_count}`);
  await loadBookmarkSyncState(config);
}

requireElement(bookmarkSyncProfileSelect).addEventListener('change', () => {
  const profileId = Number(requireElement(bookmarkSyncProfileSelect).value);
  selectedProfile = profiles.find((profile) => profile.id === profileId) || null;

  void getConfig()
    .then((config) => fillProfileForm(selectedProfile, config))
    .catch((error: unknown) => setBookmarkSyncStatus(error instanceof Error ? error.message : 'Unable to select profile.'));
});

requireElement(bookmarkSyncSaveButton).addEventListener('click', () => {
  void saveBookmarkSyncProfile()
    .then(() => setBookmarkSyncStatus('Bookmark sync profile saved.'))
    .catch((error: unknown) => setBookmarkSyncStatus(error instanceof Error ? error.message : 'Unable to save bookmark sync profile.'));
});

requireElement(bookmarkSyncPreviewButton).addEventListener('click', () => {
  if (!getBrowserAdapter().supportsNativeBookmarkWrite()) {
    setBookmarkSyncStatus('Native bookmark writing is not available in this browser.');
    return;
  }

  setBookmarkSyncStatus('Preparing preview...');
  void previewProfile().catch((error: unknown) => setBookmarkSyncStatus(error instanceof Error ? error.message : 'Unable to preview bookmark sync.'));
});

requireElement(bookmarkSyncRunButton).addEventListener('click', () => {
  if (!getBrowserAdapter().supportsNativeBookmarkWrite()) {
    setBookmarkSyncStatus('Native bookmark writing is not available in this browser.');
    return;
  }

  setBookmarkSyncStatus('Running bookmark sync...');
  void runProfile().catch((error: unknown) => setBookmarkSyncStatus(error instanceof Error ? error.message : 'Unable to run bookmark sync.'));
});

void initialize().catch((error: unknown) => {
  setStatus(error instanceof Error ? error.message : 'Unable to initialize options.');
});
