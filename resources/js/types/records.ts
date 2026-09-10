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

export const NAME_FIELD_CANDIDATES = [
    'full_name',
    'name',
    'grantee_name',
    'employee_name',
    'beneficiary_name',
    'client_name',
    'recipient_name',
    'member_name',
    'student_name',
];
const IDENTIFIER_FIELD_CANDIDATES = ['id_number', 'household_id', 'employee_no', 'employee_id', 'member_id'];

export function recordDisplayName(record: IdRecord): string {
    for (const key of NAME_FIELD_CANDIDATES) {
        const value = record.data[key];
        if (value) return value;
    }

    const parts = [record.data.first_name, record.data.middle_name, record.data.last_name].filter(Boolean);
    if (parts.length) return parts.join(' ');

    for (const key of IDENTIFIER_FIELD_CANDIDATES) {
        const value = record.data[key];
        if (value) return value;
    }

    return `Record #${record.id}`;
}
