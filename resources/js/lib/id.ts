/**
 * crypto.randomUUID() only exists in "secure contexts" (HTTPS, or the
 * localhost/127.0.0.1 exception) - accessing this app from another device
 * over plain HTTP via a LAN IP is not a secure context, so it's simply
 * undefined there and throws, silently breaking every "add element" action.
 * These IDs are just internal design-element identifiers, not
 * security-sensitive, so a Math.random()-based fallback is fine.
 */
export function generateId(): string {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }

    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
        const r = (Math.random() * 16) | 0;
        const v = c === 'x' ? r : (r & 0x3) | 0x8;
        return v.toString(16);
    });
}
