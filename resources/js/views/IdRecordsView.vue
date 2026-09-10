<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useRecordsStore } from '../stores/records';
import { fieldLabel, recordDisplayName, NAME_FIELD_CANDIDATES } from '../types/records';
import RecordFormModal from '../components/records/RecordFormModal.vue';
import ImportWizard from '../components/records/ImportWizard.vue';
import IconButton from '../components/IconButton.vue';
import PencilIcon from '../components/icons/PencilIcon.vue';
import TrashIcon from '../components/icons/TrashIcon.vue';
import type { IdRecord } from '../types/records';

const router = useRouter();
const store = useRecordsStore();
const showForm = ref(false);
const showImport = ref(false);
const editingRecord = ref<IdRecord | null>(null);
const searchField = ref('');
const searchText = ref('');

const NAME_FIELDS = new Set([...NAME_FIELD_CANDIDATES, 'first_name', 'middle_name', 'last_name']);

const COLUMN_PREF_KEY = 'idmaker.records.visibleColumns';
function loadColumnPrefs(): string[] | null {
    try {
        const raw = localStorage.getItem(COLUMN_PREF_KEY);
        return raw ? JSON.parse(raw) : null;
    } catch {
        return null;
    }
}
function saveColumnPrefs(cols: string[] | null): void {
    try {
        if (cols) localStorage.setItem(COLUMN_PREF_KEY, JSON.stringify(cols));
        else localStorage.removeItem(COLUMN_PREF_KEY);
    } catch {
    }
}

const customColumns = ref<string[] | null>(loadColumnPrefs());
const showColumnPicker = ref(false);
const columnPickerEl = ref<HTMLElement | null>(null);
function handleDocumentClick(e: MouseEvent): void {
    if (showColumnPicker.value && columnPickerEl.value && !columnPickerEl.value.contains(e.target as Node)) {
        showColumnPicker.value = false;
    }
}

const autoColumns = computed(() => {
    const cols = new Set<string>();
    for (const r of store.items) {
        for (const [k, v] of Object.entries(r.data)) {
            if (!NAME_FIELDS.has(k) && v !== null && v !== '') cols.add(k);
        }
    }
    return Array.from(cols).slice(0, 4);
});

const displayColumns = computed(() => customColumns.value ?? autoColumns.value);
const availableFields = computed(() => store.fields.filter((f) => !NAME_FIELDS.has(f)));
const hasAnyPhoto = computed(() => store.items.some((r) => r.photo_path));

function toggleColumn(field: string): void {
    const base = customColumns.value ?? [...autoColumns.value];
    customColumns.value = base.includes(field) ? base.filter((f) => f !== field) : [...base, field];
    saveColumnPrefs(customColumns.value);
}

function resetColumns(): void {
    customColumns.value = null;
    saveColumnPrefs(null);
}

const GEO_CASCADE_FIELDS = ['region', 'province', 'municipality', 'barangay'];
const GEO_CASCADE_VALUE = GEO_CASCADE_FIELDS.join(',');
const geoCascadeAvailable = computed(() => GEO_CASCADE_FIELDS.every((f) => store.usedFields.includes(f)));

async function refresh(): Promise<void> {
    await store.fetchPage(1);
}

function openCreate(): void {
    editingRecord.value = null;
    showForm.value = true;
}

function openEdit(record: IdRecord): void {
    editingRecord.value = record;
    showForm.value = true;
}

async function onSaved(): Promise<void> {
    showForm.value = false;
    await refresh();
}

async function remove(record: IdRecord): Promise<void> {
    if (!confirm('Move this record to Trash? You can restore it later.')) return;
    await store.remove(record.id);
}

async function removeSelected(): Promise<void> {
    if (!confirm(`Move ${store.selectedCount} selected record(s) to Trash? You can restore them later from the Trash page.`)) return;
    await store.removeSelected();
    await refresh();
}

function onSortFieldChange(): void {
    refresh();
}

function toggleSortDir(): void {
    store.sortDir = store.sortDir === 'asc' ? 'desc' : 'asc';
    refresh();
}

const sortDirLabel = computed(() => {
    if (store.sortBy === 'id') return store.sortDir === 'desc' ? '↓ Newest first' : '↑ Oldest first';
    return store.sortDir === 'asc' ? '↑ A–Z' : '↓ Z–A';
});

function goToGenerate(): void {
    router.push({ name: 'generate' });
}

let searchTimeout: ReturnType<typeof setTimeout> | undefined;
watch(
    [searchField, searchText],
    ([field, text]) => {
        store.search = field ? '' : text;
        store.filters = field && text ? { [field]: text } : {};
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(refresh, 300);
    },
);

onMounted(async () => {
    document.addEventListener('click', handleDocumentClick);
    await store.fetchFields();
    await refresh();
});

onBeforeUnmount(() => {
    document.removeEventListener('click', handleDocumentClick);
});
</script>

<template>
    <div class="mx-auto max-w-6xl px-6 py-10">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold">ID Records</h1>
                <p class="text-sm text-slate-500">{{ store.total }} record(s) · reusable across any template.</p>
            </div>
            <div class="flex gap-2">
                <router-link :to="{ name: 'records-trash' }" class="rounded border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50">Trash</router-link>
                <button class="rounded border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50" @click="showImport = true">Import CSV/Excel</button>
                <button class="rounded bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700" @click="openCreate">New Record</button>
            </div>
        </div>

        <div class="mb-4 flex flex-wrap items-center gap-2">
            <select v-model="searchField" class="rounded border border-slate-300 px-2 py-1.5 text-sm">
                <option value="">All fields</option>
                <option v-for="f in store.fields" :key="f" :value="f">{{ fieldLabel(f) }}</option>
            </select>
            <input
                v-model="searchText"
                type="text"
                :placeholder="searchField ? `Search ${fieldLabel(searchField)}…` : 'Search records…'"
                class="w-64 rounded border border-slate-300 px-3 py-1.5 text-sm"
            />

            <span class="ml-2 text-sm text-slate-400">Sort by</span>
            <select v-model="store.sortBy" class="rounded border border-slate-300 px-2 py-1.5 text-sm" @change="onSortFieldChange">
                <option value="id">Date added</option>
                <option v-if="geoCascadeAvailable" :value="GEO_CASCADE_VALUE">Region → Province → Municipality → Barangay</option>
                <option v-for="f in store.fields" :key="f" :value="f">{{ fieldLabel(f) }}</option>
            </select>
            <button class="rounded border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50" @click="toggleSortDir">
                {{ sortDirLabel }}
            </button>

            <div ref="columnPickerEl" class="relative ml-auto">
                <button
                    class="rounded border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50"
                    @click="showColumnPicker = !showColumnPicker"
                >
                    Columns ▾
                </button>
                <div
                    v-if="showColumnPicker"
                    class="absolute right-0 z-10 mt-1 w-64 rounded border border-slate-200 bg-white p-3 text-sm shadow-lg"
                >
                    <p class="mb-2 text-xs font-medium text-slate-400">Extra columns to show (Name is always shown)</p>
                    <label v-for="f in availableFields" :key="f" class="flex items-center gap-2 py-0.5">
                        <input type="checkbox" :checked="displayColumns.includes(f)" @change="toggleColumn(f)" />
                        {{ fieldLabel(f) }}
                    </label>
                    <button class="mt-2 text-xs text-slate-500 hover:underline" @click="resetColumns">Reset to default</button>
                </div>
            </div>
        </div>

        <div class="mb-3 flex items-center gap-3 rounded border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
            <span class="font-medium">{{ store.selectedCount }} selected</span>
            <button class="text-slate-600 hover:underline" @click="store.selectAllVisible()">Select all visible</button>
            <button class="text-slate-600 hover:underline" @click="store.selectAllMatching()">Select all {{ store.total }} matching</button>
            <button class="text-slate-600 hover:underline" @click="store.clearSelection()">Clear</button>
            <button
                class="text-red-600 hover:underline disabled:pointer-events-none disabled:opacity-40"
                :disabled="store.selectedCount === 0"
                @click="removeSelected"
            >
                Delete selected
            </button>
            <button
                class="ml-auto rounded bg-slate-900 px-4 py-1.5 font-medium text-white hover:bg-slate-700 disabled:opacity-40"
                :disabled="store.selectedCount === 0"
                @click="goToGenerate"
            >
                Generate IDs ({{ store.selectedCount }})
            </button>
        </div>

        <div v-if="store.loading" class="text-sm text-slate-500">Loading…</div>

        <div v-else-if="store.items.length === 0" class="rounded-lg border border-dashed border-slate-300 p-10 text-center text-slate-500">
            No records yet. Add one manually or import a CSV/Excel file.
        </div>

        <table v-else class="w-full border-collapse rounded-lg border border-slate-200 bg-white text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="border-b border-slate-200 px-3 py-2 text-left"></th>
                    <th class="border-b border-slate-200 px-3 py-2 text-left">Name</th>
                    <th v-for="col in displayColumns" :key="col" class="border-b border-slate-200 px-3 py-2 text-left">{{ fieldLabel(col) }}</th>
                    <th v-if="hasAnyPhoto" class="border-b border-slate-200 px-3 py-2 text-left">Photo</th>
                    <th class="border-b border-slate-200 px-3 py-2"></th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="record in store.items" :key="record.id" class="hover:bg-slate-50">
                    <td class="border-b border-slate-100 px-3 py-2">
                        <input type="checkbox" :checked="store.selectedIds.has(record.id)" @change="store.toggleSelected(record.id)" />
                    </td>
                    <td class="border-b border-slate-100 px-3 py-2 font-medium">{{ recordDisplayName(record) }}</td>
                    <td v-for="col in displayColumns" :key="col" class="border-b border-slate-100 px-3 py-2">{{ record.data[col] ?? '' }}</td>
                    <td v-if="hasAnyPhoto" class="border-b border-slate-100 px-3 py-2 text-xs text-slate-400">{{ record.photo_path ? '✓' : '—' }}</td>
                    <td class="border-b border-slate-100 px-3 py-2 text-right">
                        <IconButton label="Edit" @click="openEdit(record)"><PencilIcon class="h-4 w-4" /></IconButton>
                        <IconButton label="Delete" variant="danger" @click="remove(record)"><TrashIcon class="h-4 w-4" /></IconButton>
                    </td>
                </tr>
            </tbody>
        </table>

        <div v-if="store.lastPage > 1" class="mt-4 flex justify-center gap-2 text-sm">
            <button
                class="rounded border border-slate-300 px-3 py-1 disabled:opacity-40"
                :disabled="store.currentPage <= 1"
                @click="store.fetchPage(store.currentPage - 1)"
            >
                Previous
            </button>
            <span class="px-2 py-1 text-slate-500">Page {{ store.currentPage }} of {{ store.lastPage }}</span>
            <button
                class="rounded border border-slate-300 px-3 py-1 disabled:opacity-40"
                :disabled="store.currentPage >= store.lastPage"
                @click="store.fetchPage(store.currentPage + 1)"
            >
                Next
            </button>
        </div>

        <RecordFormModal v-if="showForm" :record="editingRecord" :available-fields="store.fields" @close="showForm = false" @saved="onSaved" />
        <ImportWizard v-if="showImport" @close="showImport = false; refresh(); store.fetchFields()" @imported="refresh(); store.fetchFields()" />
    </div>
</template>
