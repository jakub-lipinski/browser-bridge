const blockedSchemes = [
  'about:',
  'brave:',
  'chrome:',
  'devtools:',
  'edge:',
  'file:',
  'javascript:',
  'view-source:',
];

export function isInternalUrl(url?: string | null): boolean {
  if (!url) {
    return true;
  }

  const normalizedUrl = url.trim().toLowerCase();

  return blockedSchemes.some((scheme) => normalizedUrl.startsWith(scheme));
}

export function isSyncableUrl(url?: string | null): url is string {
  if (!url || isInternalUrl(url)) {
    return false;
  }

  try {
    const parsedUrl = new URL(url);

    return parsedUrl.protocol === 'http:' || parsedUrl.protocol === 'https:';
  } catch {
    return false;
  }
}
