export function autoWrapFieldTemplate(input: string): string {
    const trimmed = input.trim();
    if (trimmed === '' || trimmed.includes('{{')) return trimmed;

    const parts = trimmed
        .split(',')
        .map((p) => p.trim())
        .filter(Boolean);

    if (parts.length === 0) return trimmed;

    return parts.map((p) => `{{${p}}}`).join(', ');
}

/** Display text for a dynamic field's current value — wraps a bare key, leaves an existing template as-is. */
export function dynamicFieldPreviewText(field: string): string {
    return field.includes('{{') ? field : `{{${field}}}`;
}
