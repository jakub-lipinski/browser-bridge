import { registerDevice } from './apiClient';
import { getConfig, updateConfig } from './storage';
import type { ExtensionConfig } from './types';

export function detectPlatform(): string {
  const platform = navigator.userAgentData?.platform || navigator.platform || navigator.userAgent;
  const normalizedPlatform = platform.toLowerCase();

  if (normalizedPlatform.includes('mac')) {
    return 'macos';
  }

  if (normalizedPlatform.includes('win')) {
    return 'windows';
  }

  if (normalizedPlatform.includes('linux')) {
    return 'linux';
  }

  if (normalizedPlatform.includes('iphone') || normalizedPlatform.includes('ipad')) {
    return 'ios';
  }

  if (normalizedPlatform.includes('android')) {
    return 'android';
  }

  return 'unknown';
}

export function defaultDeviceName(): string {
  const platform = detectPlatform();

  return `Chrome on ${platform}`;
}

export async function connectDevice(config?: ExtensionConfig): Promise<ExtensionConfig> {
  const currentConfig = config ?? await getConfig();
  const platform = currentConfig.platform || detectPlatform();
  const deviceName = currentConfig.deviceName || defaultDeviceName();
  const browserName = currentConfig.browserName || 'Chrome';
  const device = await registerDevice({
    ...currentConfig,
    deviceName,
    browserName,
    platform,
  });

  return updateConfig({
    deviceUuid: device.uuid,
    deviceName: device.name,
    browserName: device.browser,
    platform: device.platform,
    lastError: null,
  });
}
