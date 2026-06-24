import { registerDevice } from './apiClient';
import { getBrowserAdapter } from './browserAdapter';
import { getConfig, updateConfig } from './storage';
import type { ExtensionConfig } from './types';

export function detectPlatform(): string {
  return getBrowserAdapter().getPlatform();
}

export function defaultDeviceName(): string {
  const platform = detectPlatform();
  const browser = getBrowserAdapter().getBrowserName() === 'safari' ? 'Safari' : 'Chrome';

  return `${browser} on ${platform}`;
}

export async function connectDevice(config?: ExtensionConfig): Promise<ExtensionConfig> {
  const currentConfig = config ?? await getConfig();
  const platform = currentConfig.platform || detectPlatform();
  const deviceName = currentConfig.deviceName || defaultDeviceName();
  const browserName = getBrowserAdapter().getBrowserName();
  const runtimeCapabilities = await getBrowserAdapter()
    .getCapabilityAudit()
    .catch(() => undefined);
  const device = await registerDevice({
    ...currentConfig,
    deviceName,
    browserName,
    platform,
    runtimeCapabilities,
  });

  return updateConfig({
    deviceUuid: device.uuid,
    deviceName: device.name,
    browserName: device.browser,
    platform: device.platform,
    runtimeCapabilities,
    lastError: null,
  });
}
