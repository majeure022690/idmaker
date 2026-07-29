<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { api } from '../lib/api';
import type { IdRecord, Paginated } from '../types/records';

const items = ref<IdRecord[]>([]);
const total = ref(0);
const loading = ref(false);

async function refresh(): Promise<void> {
    loading.value = true;
    try {
        const { data } = await api.get<Paginated<IdRecord>>('/id-records/trashed', { params: { per_page: 100 } });
        items.value = data.data;
        total.value = data.total;
    } finally {
        loading.value = false;
    }
}

function recordLabel(r: IdRecord): string {
    const candidates = ['full_name', 'grantee_name', 'name', 'id_number', 'household_id'];
    for (const key of candidates) {
        if (r.data[key]) return String(r.data[key]);
    }
    const firstValue = Object.values(r.data).find((v) => v);
    return firstValue ? String(firstValue) : `Record #${r.id}`;
}

async function restore(record: IdRecord): Promise<void> {
    await api.post(`/id-records/${record.id}/restore`);
    await refresh();
}

async function forceDelete(record: IdRecord): Promise<void> {
    if (!confirm(`Permanently delete "${recordLabel(record)}"? This cannot be undone.`)) return;
    await api.delete(`/id-records/${record.id}/force`);
    await refresh();
}

async function emptyTrash(): Promise<void> {
    if (!confirm(`Permanently delete all ${total.value} record(s) in trash? This cannot be undone.`)) return;
    await api.post('/id-records/trash/empty');
    await refresh();
}

onMounted(refresh);
</script>

<template>
    <div class="mx-auto max-w-5xl px-6 py-10">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold">Trash</h1>
                <p class="text-sm text-slate-500">{{ total }} deleted record(s). Restore them or delete permanently.</p>
            </div>
            <div class="flex gap-2">
                <router-link :to="{ name: 'records' }" class="rounded border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50">
                    ← Back to Records
                </router-link>
                <button
                    class="rounded border border-red-300 px-4 py-2 text-sm text-red-600 hover:bg-red-50 disabled:opacity-40"
                    :disabled="total === 0"
                    @click="emptyTrash"
                >
                    Empty trash
                </button>
            </div>
        </div>

        <div v-if="loading" class="text-sm text-slate-500">Loading…</div>
        <div v-else-if="items.length === 0" class="rounded-lg border border-dashed border-slate-300 p-10 text-center text-slate-500">
            Trash is empty.
        </div>

        <table v-else class="w-full border-collapse rounded-lg border border-slate-200 bg-white text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="border-b border-slate-200 px-3 py-2 text-left">Record</th>
                    <th class="border-b border-slate-200 px-3 py-2 text-left">Deleted</th>
                    <th class="border-b border-slate-200 px-3 py-2"></th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="record in items" :key="record.id" class="hover:bg-slate-50">
                    <td class="border-b border-slate-100 px-3 py-2">{{ recordLabel(record) }}</td>
                    <td class="border-b border-slate-100 px-3 py-2 text-slate-500">
                        {{ record.deleted_at ? new Date(record.deleted_at).toLocaleString() : '' }}
                    </td>
                    <td class="border-b border-slate-100 px-3 py-2 text-right">
                        <button class="text-slate-600 hover:underline" @click="restore(record)">Restore</button>
                        <button class="ml-2 text-red-600 hover:underline" @click="forceDelete(record)">Delete permanently</button>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
