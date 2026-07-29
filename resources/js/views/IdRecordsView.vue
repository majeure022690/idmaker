<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useRecordsStore } from '../stores/records';
import { fieldLabel } from '../types/records';
import RecordFormModal from '../components/records/RecordFormModal.vue';
import ImportWizard from '../components/records/ImportWizard.vue';
import type { IdRecord } from '../types/records';

const router = useRouter();
const store = useRecordsStore();
const showForm = ref(false);
const showImport = ref(false);
const editingRecord = ref<IdRecord | null>(null);
const filterField = ref('');
const filterValue = ref('');

const displayColumns = computed(() => {
    const cols = new Set<string>();
    for (const r of store.items) Object.keys(r.data).forEach((k) => cols.add(k));
    return Array.from(cols).slice(0, 5);
});

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

function applyFilter(): void {
    store.filters = filterField.value && filterValue.value ? { [filterField.value]: filterValue.value } : {};
    refresh();
}

function goToGenerate(): void {
    router.push({ name: 'generate' });
}

let searchTimeout: ReturnType<typeof setTimeout> | undefined;
watch(
    () => store.search,
    () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(refresh, 300);
    },
);

onMounted(async () => {
    await Promise.all([store.fetchFields(), refresh()]);
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
            <input v-model="store.search" type="text" placeholder="Search records…" class="w-64 rounded border border-slate-300 px-3 py-1.5 text-sm" />
            <select v-model="filterField" class="rounded border border-slate-300 px-2 py-1.5 text-sm">
                <option value="">Filter by field…</option>
                <option v-for="f in store.fields" :key="f" :value="f">{{ fieldLabel(f) }}</option>
            </select>
            <input
                v-model="filterValue"
                type="text"
                placeholder="Value"
                class="w-40 rounded border border-slate-300 px-2 py-1.5 text-sm"
                @keydown.enter="applyFilter"
            />
            <button class="rounded border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50" @click="applyFilter">Apply</button>
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
                    <th v-for="col in displayColumns" :key="col" class="border-b border-slate-200 px-3 py-2 text-left">{{ fieldLabel(col) }}</th>
                    <th class="border-b border-slate-200 px-3 py-2 text-left">Photo</th>
                    <th class="border-b border-slate-200 px-3 py-2"></th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="record in store.items" :key="record.id" class="hover:bg-slate-50">
                    <td class="border-b border-slate-100 px-3 py-2">
                        <input type="checkbox" :checked="store.selectedIds.has(record.id)" @change="store.toggleSelected(record.id)" />
                    </td>
                    <td v-for="col in displayColumns" :key="col" class="border-b border-slate-100 px-3 py-2">{{ record.data[col] ?? '' }}</td>
                    <td class="border-b border-slate-100 px-3 py-2 text-xs text-slate-400">{{ record.photo_path ? '✓' : '—' }}</td>
                    <td class="border-b border-slate-100 px-3 py-2 text-right">
                        <button class="text-slate-600 hover:underline" @click="openEdit(record)">Edit</button>
                        <button class="ml-2 text-red-600 hover:underline" @click="remove(record)">Delete</button>
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
