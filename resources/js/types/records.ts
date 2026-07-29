export interface IdRecord {
    id: number;
    data: Record<string, string | null>;
    photo_path: string | null;
    signature_path: string | null;
    created_at: string;
    updated_at: string;
    deleted_at?: string | null;
}

export interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

export const STANDARD_FIELD_LABELS: Record<string, string> = {
    id_number: 'ID Number',
    full_name: 'Full Name',
    first_name: 'First Name',
    middle_name: 'Middle Name',
    last_name: 'Last Name',
    position: 'Position',
    office: 'Office',
    department: 'Department',
    date_of_birth: 'Date of Birth',
    address: 'Address',
    contact_number: 'Contact Number',
    date_issued: 'Date Issued',
    expiration_date: 'Expiration Date',
};

export function fieldLabel(key: string): string {
    if (STANDARD_FIELD_LABELS[key]) return STANDARD_FIELD_LABELS[key];
    return key
        .split('_')
        .map((w) => w.charAt(0).toUpperCase() + w.slice(1))
        .join(' ');
}
