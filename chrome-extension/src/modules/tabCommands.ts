import {
  fetchIncomingTabCommands,
  markTabCommandDismissed,
  markTabCommandOpened,
  sendTabCommand,
} from './apiClient';
import { getBrowserAdapter } from './browserAdapter';
import type { ExtensionConfig, TabCommandResource, TabSnapshotItem } from './types';
import { isSyncableUrl } from './urlFilter';

export async function getIncomingCommands(config: ExtensionConfig): Promise<TabCommandResource[]> {
  return fetchIncomingTabCommands(config);
}

export async function openIncomingCommand(config: ExtensionConfig, command: TabCommandResource): Promise<void> {
  if (!isSyncableUrl(command.url)) {
    await markTabCommandDismissed(config, command.id);

    return;
  }

  await getBrowserAdapter().openTab(command.url);
  await markTabCommandOpened(config, command.id);
}

export async function dismissIncomingCommand(config: ExtensionConfig, commandId: number): Promise<void> {
  await markTabCommandDismissed(config, commandId);
}

export async function sendCurrentTabToDevice(
  config: ExtensionConfig,
  targetDeviceUuid: string,
  tab: TabSnapshotItem,
): Promise<void> {
  if (!isSyncableUrl(tab.url)) {
    throw new Error('The current tab URL cannot be synced.');
  }

  await sendTabCommand(config, targetDeviceUuid, {
    url: tab.url,
    title: tab.title,
  });
}
