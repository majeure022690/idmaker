export function resolveStorageUrl(path: string): string {
    if (path.startsWith('http') || path.startsWith('/')) return path;
    return `/storage/${path}`;
}
