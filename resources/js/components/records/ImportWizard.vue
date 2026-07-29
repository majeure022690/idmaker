<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import { api } from '../../lib/api';
import { fieldLabel } from '../../types/records';

const emit = defineEmits<{ (e: 'close'): void; (e: 'imported'): void }>();

type Step = 'upload' | 'map' | 'preview' | 'done';

const step = ref<Step>('upload');
const busy = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);

const importId = ref('');
const extension = ref('');
const headers = ref<string[]>([]);
const rowCount = ref(0);
const sampleRows = ref<string[][]>([]);
const mapping = reactive<Record<string, string | null>>({});
const uniqueField = ref('id_number');
const mode = ref<'create' | 'update' | 'create_or_update'>('create_or_update');

const previewRows = ref<Record<string, string | null>[]>([]);
const previewTotal = ref(0);
const issues = ref<string[]>([]);

const summary = ref<{ created: number; updated: number; skipped: number; errors: string[] } | null>(null);

const mappedFieldOptions = computed(() => {
    const keys = new Set<string>();
    Object.values(mapping).forEach((v) => v && keys.add(v));
    return Array.from(keys);
});

function pickFile(): void {
    fileInput.value?.click();
}

async function onFileSelected(event: Event): Promise<void> {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (!file) return;

    busy.value = true;
    try {
        const formData = new FormData();
        formData.append('file', file);
        const { data } = await api.post('/imports', formData);
        importId.value = data.import_id;
        extension.value = data.extension;
        headers.value = data.headers;
        rowCount.value = data.row_count;
        sampleRows.value = data.sample_rows;
        Object.assign(mapping, data.suggested_mapping);
        step.value = 'map';
    } finally {
        busy.value = false;
    }
}

async function goToPreview(): Promise<void> {
    busy.value = true;
    try {
        const { data } = await api.post(`/imports/${importId.value}/preview`, {
            extension: extension.value,
            mapping,
            unique_field: mode.value === 'create' ? null : uniqueField.value,
        });
        previewRows.value = data.preview_rows;
        previewTotal.value = data.total_rows;
        issues.value = data.issues;
        step.value = 'preview';
    } finally {
        busy.value = false;
    }
}

async function commit(): Promise<void> {
    busy.value = true;
    try {
        const { data } = await api.post(`/imports/${importId.value}/commit`, {
            extension: extension.value,
            mapping,
            mode: mode.value,
            unique_field: mode.value === 'create' ? null : uniqueField.value,
        });
        summary.value = data;
        step.value = 'done';
        emit('imported');
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <div class="fixed inset-0 z-20 flex items-center justify-center bg-black/30 p-4">
        <div class="flex max-h-[90vh] w-full max-w-3xl flex-col rounded-lg bg-white shadow-xl">
            <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                <h2 class="text-lg font-semibold">Import CSV / Excel</h2>
                <button class="text-slate-400 hover:text-slate-600" @click="emit('close')">✕</button>
            </div>

            <div class="flex-1 overflow-y-auto px-6 py-4">
                <div v-if="step === 'upload'" class="flex flex-col items-center justify-center gap-3 py-16">
                    <p class="text-sm text-slate-500">Upload a CSV or Excel file. Column names don't need to match anything — you'll map them next.</p>
                    <button
                        class="rounded bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700 disabled:opacity-50"
                        :disabled="busy"
                        @click="pickFile"
                    >
                        {{ busy ? 'Reading file…' : 'Choose file' }}
                    </button>
                    <input ref="fileInput" type="file" accept=".csv,.txt,.xlsx,.xls,.ods" class="hidden" @change="onFileSelected" />
                </div>

                <div v-else-if="step === 'map'" class="flex flex-col gap-4">
                    <p class="text-sm text-slate-500">{{ rowCount }} rows detected. Map each column, or leave it "Ignore".</p>

                    <div class="max-h-64 overflow-y-auto rounded border border-slate-200">
                        <table class="w-full text-xs">
                            <thead class="sticky top-0 bg-slate-50">
                                <tr>
                                    <th class="border-b border-slate-200 px-2 py-1.5 text-left">Column</th>
                                    <th class="border-b border-slate-200 px-2 py-1.5 text-left">Sample</th>
                                    <th class="border-b border-slate-200 px-2 py-1.5 text-left">Maps to</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(header, i) in headers" :key="header + i" class="border-b border-slate-100">
                                    <td class="px-2 py-1.5 font-medium text-slate-700">{{ header || '(blank)' }}</td>
                                    <td class="px-2 py-1.5 text-slate-500">{{ sampleRows[0]?.[i] ?? '' }}</td>
                                    <td class="px-2 py-1.5">
                                        <input
                                            v-model="mapping[header]"
                                            type="text"
                                            placeholder="Ignore this column"
                                            class="w-full rounded border border-slate-300 px-2 py-1"
                                        />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <label class="text-sm text-slate-600">
                            Import mode
                            <select v-model="mode" class="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm">
                                <option value="create">Create new records only</option>
                                <option value="update">Update existing records only</option>
                                <option value="create_or_update">Create or update</option>
                            </select>
                        </label>
                        <label v-if="mode !== 'create'" class="text-sm text-slate-600">
                            Unique field (to match existing records)
                            <select v-model="uniqueField" class="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm">
                                <option v-for="f in mappedFieldOptions" :key="f" :value="f">{{ fieldLabel(f) }}</option>
                            </select>
                        </label>
                    </div>
                </div>

                <div v-else-if="step === 'preview'" class="flex flex-col gap-4">
                    <div v-if="issues.length" class="rounded border border-amber-300 bg-amber-50 p-3 text-xs text-amber-800">
                        <p class="mb-1 font-medium">{{ issues.length }} issue(s) found:</p>
                        <ul class="list-inside list-disc">
                            <li v-for="(issue, i) in issues.slice(0, 10)" :key="i">{{ issue }}</li>
                        </ul>
                        <p v-if="issues.length > 10">…and {{ issues.length - 10 }} more.</p>
                    </div>

                    <p class="text-sm text-slate-500">Previewing {{ previewRows.length }} of {{ previewTotal }} rows:</p>
                    <div class="max-h-72 overflow-auto rounded border border-slate-200">
                        <table class="w-full text-xs">
                            <thead class="sticky top-0 bg-slate-50">
                                <tr>
                                    <th v-for="f in mappedFieldOptions" :key="f" class="border-b border-slate-200 px-2 py-1.5 text-left">{{ fieldLabel(f) }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(row, i) in previewRows" :key="i" class="border-b border-slate-100">
                                    <td v-for="f in mappedFieldOptions" :key="f" class="px-2 py-1.5">{{ row[f] ?? '' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div v-else-if="step === 'done' && summary" class="flex flex-col items-center gap-2 py-12 text-center">
                    <p class="text-lg font-medium text-slate-900">Import complete</p>
                    <p class="text-sm text-slate-600">{{ summary.created }} created · {{ summary.updated }} updated · {{ summary.skipped }} skipped</p>
                    <div v-if="summary.errors.length" class="mt-2 max-h-32 overflow-y-auto rounded border border-amber-200 bg-amber-50 p-2 text-left text-xs text-amber-800">
                        <p v-for="(e, i) in summary.errors" :key="i">{{ e }}</p>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-2 border-t border-slate-200 px-6 py-4">
                <button v-if="step === 'map'" class="rounded px-4 py-2 text-sm text-slate-600 hover:bg-slate-100" @click="step = 'upload'">Back</button>
                <button v-if="step === 'preview'" class="rounded px-4 py-2 text-sm text-slate-600 hover:bg-slate-100" @click="step = 'map'">Back</button>

                <button v-if="step === 'map'" class="rounded bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700" :disabled="busy" @click="goToPreview">
                    Preview
                </button>
                <button v-if="step === 'preview'" class="rounded bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700 disabled:opacity-50" :disabled="busy" @click="commit">
                    {{ busy ? 'Importing…' : `Import ${previewTotal} records` }}
                </button>
                <button v-if="step === 'done'" class="rounded bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700" @click="emit('close')">
                    Done
                </button>
            </div>
        </div>
    </div>
</template>
