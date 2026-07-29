<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRecordsStore } from '../stores/records';
import { useTemplatesStore } from '../stores/templates';
import { api } from '../lib/api';
import { recordDisplayName } from '../types/records';
import type { GenerationBatch, LayoutPreview, PrintConfig } from '../types/print';

const records = useRecordsStore();
const templates = useTemplatesStore();

const templateId = ref<number | null>(null);
const batchName = ref('');
const generating = ref(false);
const generatedBatch = ref<GenerationBatch | null>(null);
const generateError = ref('');
const history = ref<GenerationBatch[]>([]);
const showReview = ref(false);

const config = reactive<PrintConfig>({
    paper: 'Legal',
    orientation: 'portrait',
    margin_top: 10,
    margin_bottom: 10,
    margin_left: 10,
    margin_right: 10,
    spacing_x: 3,
    spacing_y: 3,
    columns: null,
    rows: null,
    side: 'front',
});

const layout = ref<LayoutPreview | null>(null);
const layoutBusy = ref(false);

const selectedTemplate = computed(() => templates.items.find((t) => t.id === templateId.value) ?? null);
const selectedRecords = computed(() => records.items.filter((r) => records.selectedIds.has(r.id)));

async function refreshLayout(): Promise<void> {
    if (!templateId.value) {
        layout.value = null;
        return;
    }
    layoutBusy.value = true;
    try {
        const { data } = await api.post<LayoutPreview>('/generation-batches/layout-preview', {
            template_id: templateId.value,
            print_config: config,
        });
        layout.value = data;
    } finally {
        layoutBusy.value = false;
    }
}

let debounceTimer: ReturnType<typeof setTimeout> | undefined;
watch(
    [templateId, () => ({ ...config })],
    () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(refreshLayout, 250);
    },
    { deep: true },
);

const pagesNeeded = computed(() => {
    if (!layout.value || !layout.value.perPage) return 0;
    const sides = config.side === 'both' ? 2 : 1;
    return Math.ceil(records.selectedIds.size / layout.value.perPage) * sides;
});

const previewScale = computed(() => (layout.value ? 260 / layout.value.paperWidth : 1));

async function loadHistory(): Promise<void> {
    const { data } = await api.get<GenerationBatch[]>('/generation-batches');
    history.value = data;
}

async function generate(): Promise<void> {
    if (!templateId.value || records.selectedIds.size === 0) return;
    generating.value = true;
    generateError.value = '';
    generatedBatch.value = null;
    try {
        const { data } = await api.post<GenerationBatch>('/generation-batches', {
            name: batchName.value || `Batch ${new Date().toLocaleString()}`,
            template_id: templateId.value,
            record_ids: Array.from(records.selectedIds),
            print_config: config,
        });
        generatedBatch.value = data;
        await loadHistory();
    } catch (e: any) {
        generateError.value = e?.response?.data?.message ?? 'Generation failed.';
    } finally {
        generating.value = false;
    }
}

async function regenerate(batch: GenerationBatch): Promise<void> {
    await api.post(`/generation-batches/${batch.id}/regenerate`);
    await loadHistory();
}

async function deleteBatch(batch: GenerationBatch): Promise<void> {
    if (!confirm('Delete this batch from history?')) return;
    await api.delete(`/generation-batches/${batch.id}`);
    await loadHistory();
}

onMounted(async () => {
    await templates.fetchAll();
    if (templates.items.length) templateId.value = templates.items[0].id;
    if (!records.items.length) await records.fetchPage(1);
    await loadHistory();
});
</script>

<template>
    <div class="mx-auto max-w-6xl px-6 py-10">
        <h1 class="mb-1 text-2xl font-semibold">Generate IDs</h1>
        <p class="mb-6 text-sm text-slate-500">Select a template and paper layout for your selected records, then generate a print-ready PDF.</p>

        <div class="mb-6 rounded-lg border border-slate-200 bg-white p-4">
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium">{{ records.selectedIds.size }} record(s) selected</span>
                <div class="flex gap-3 text-sm">
                    <router-link :to="{ name: 'records' }" class="text-slate-600 hover:underline">Change selection</router-link>
                    <button v-if="records.selectedIds.size" class="text-slate-600 hover:underline" @click="showReview = !showReview">
                        {{ showReview ? 'Hide' : 'Review' }} list
                    </button>
                </div>
            </div>
            <ul v-if="showReview" class="mt-3 max-h-40 overflow-y-auto text-sm">
                <li v-for="r in selectedRecords" :key="r.id" class="flex items-center justify-between border-b border-slate-100 py-1">
                    <span>{{ recordDisplayName(r) }}</span>
                    <button class="text-red-500 hover:underline" @click="records.removeFromSelection(r.id)">Remove</button>
                </li>
            </ul>
        </div>

        <div class="grid grid-cols-2 gap-6">
            <div class="flex flex-col gap-4">
                <label class="text-sm text-slate-600">
                    Template
                    <select v-model.number="templateId" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm">
                        <option v-for="t in templates.items" :key="t.id" :value="t.id">{{ t.name }} ({{ t.width }}×{{ t.height }}{{ t.unit }})</option>
                    </select>
                </label>

                <div class="grid grid-cols-2 gap-3">
                    <label class="text-sm text-slate-600">
                        Paper size
                        <select v-model="config.paper" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm">
                            <option value="A4">A4</option>
                            <option value="Letter">Letter</option>
                            <option value="Legal">Legal</option>
                            <option value="A5">A5</option>
                            <option value="Custom">Custom</option>
                        </select>
                    </label>
                    <label class="text-sm text-slate-600">
                        Orientation
                        <select v-model="config.orientation" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm">
                            <option value="portrait">Portrait</option>
                            <option value="landscape">Landscape</option>
                        </select>
                    </label>
                </div>

                <div v-if="config.paper === 'Custom'" class="grid grid-cols-2 gap-3">
                    <label class="text-sm text-slate-600">
                        Width (mm)
                        <input v-model.number="config.custom_width" type="number" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm" />
                    </label>
                    <label class="text-sm text-slate-600">
                        Height (mm)
                        <input v-model.number="config.custom_height" type="number" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm" />
                    </label>
                </div>

                <div>
                    <p class="mb-1 text-sm text-slate-600">Margins (mm)</p>
                    <div class="grid grid-cols-4 gap-2">
                        <input v-model.number="config.margin_top" type="number" title="Top" class="rounded border border-slate-300 px-2 py-1.5 text-sm" />
                        <input v-model.number="config.margin_bottom" type="number" title="Bottom" class="rounded border border-slate-300 px-2 py-1.5 text-sm" />
                        <input v-model.number="config.margin_left" type="number" title="Left" class="rounded border border-slate-300 px-2 py-1.5 text-sm" />
                        <input v-model.number="config.margin_right" type="number" title="Right" class="rounded border border-slate-300 px-2 py-1.5 text-sm" />
                    </div>
                </div>

                <div>
                    <p class="mb-1 text-sm text-slate-600">Spacing (mm)</p>
                    <div class="grid grid-cols-2 gap-2">
                        <input v-model.number="config.spacing_x" type="number" title="Horizontal" class="rounded border border-slate-300 px-2 py-1.5 text-sm" />
                        <input v-model.number="config.spacing_y" type="number" title="Vertical" class="rounded border border-slate-300 px-2 py-1.5 text-sm" />
                    </div>
                </div>

                <div>
                    <p class="mb-1 text-sm text-slate-600">IDs per page (leave blank to auto-fit as many as possible)</p>
                    <div class="grid grid-cols-2 gap-2">
                        <input v-model.number="config.columns" type="number" min="1" placeholder="Columns (auto)" class="rounded border border-slate-300 px-2 py-1.5 text-sm" />
                        <input v-model.number="config.rows" type="number" min="1" placeholder="Rows (auto)" class="rounded border border-slate-300 px-2 py-1.5 text-sm" />
                    </div>
                </div>

                <label v-if="selectedTemplate?.has_back" class="text-sm text-slate-600">
                    Sides
                    <select v-model="config.side" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm">
                        <option value="front">Front only</option>
                        <option value="back">Back only</option>
                        <option value="both">Front and back (separate page passes)</option>
                    </select>
                </label>

                <label class="text-sm text-slate-600">
                    Batch name
                    <input v-model="batchName" type="text" placeholder="e.g. July 2026 New Employees" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm" />
                </label>

                <button
                    class="rounded bg-slate-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-slate-700 disabled:opacity-50"
                    :disabled="generating || !templateId || records.selectedIds.size === 0 || layout?.fits === false"
                    @click="generate"
                >
                    {{ generating ? 'Generating…' : `Generate PDF (${records.selectedIds.size} IDs, ${pagesNeeded} page${pagesNeeded === 1 ? '' : 's'})` }}
                </button>

                <p v-if="generateError" class="text-sm text-red-600">{{ generateError }}</p>
                <div v-if="generatedBatch?.pdf_url" class="rounded border border-green-300 bg-green-50 p-3 text-sm">
                    PDF ready —
                    <a :href="generatedBatch.pdf_url" target="_blank" class="font-medium text-green-700 underline">Download / Print</a>
                </div>
            </div>

            <div>
                <p class="mb-2 text-sm font-medium text-slate-700">Print preview</p>
                <div v-if="layout && !layout.fits" class="rounded border border-red-300 bg-red-50 p-3 text-sm text-red-700">
                    {{ layout.error }}
                </div>
                <template v-else-if="layout">
                    <p class="mb-2 text-xs text-slate-500">
                        {{ layout.columns }} × {{ layout.rows }} = {{ layout.perPage }} IDs per page · {{ pagesNeeded }} page(s) total
                    </p>
                    <div
                        class="relative border border-slate-300 bg-white shadow-sm"
                        :style="{ width: layout.paperWidth * previewScale + 'px', height: layout.paperHeight * previewScale + 'px' }"
                    >
                        <div
                            v-for="(pos, i) in layout.positions"
                            :key="i"
                            class="absolute border border-slate-400 bg-slate-100"
                            :style="{
                                left: pos.x * previewScale + 'px',
                                top: pos.y * previewScale + 'px',
                                width: layout.cardWidth * previewScale + 'px',
                                height: layout.cardHeight * previewScale + 'px',
                            }"
                        />
                    </div>
                </template>
                <p v-else class="text-sm text-slate-400">Select a template to preview the layout.</p>
            </div>
        </div>

        <div class="mt-10">
            <h2 class="mb-3 text-lg font-semibold">Generation history</h2>
            <div v-if="history.length === 0" class="text-sm text-slate-400">No batches generated yet.</div>
            <table v-else class="w-full border-collapse rounded-lg border border-slate-200 bg-white text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="border-b border-slate-200 px-3 py-2 text-left">Batch</th>
                        <th class="border-b border-slate-200 px-3 py-2 text-left">Template</th>
                        <th class="border-b border-slate-200 px-3 py-2 text-left">Records</th>
                        <th class="border-b border-slate-200 px-3 py-2 text-left">Generated</th>
                        <th class="border-b border-slate-200 px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="batch in history" :key="batch.id" class="hover:bg-slate-50">
                        <td class="border-b border-slate-100 px-3 py-2">{{ batch.name }}</td>
                        <td class="border-b border-slate-100 px-3 py-2">{{ batch.template_name }}</td>
                        <td class="border-b border-slate-100 px-3 py-2">{{ batch.record_count }}</td>
                        <td class="border-b border-slate-100 px-3 py-2">{{ batch.generated_at ? new Date(batch.generated_at).toLocaleString() : '—' }}</td>
                        <td class="border-b border-slate-100 px-3 py-2 text-right">
                            <a v-if="batch.pdf_url" :href="batch.pdf_url" target="_blank" class="text-slate-600 hover:underline">Download</a>
                            <button class="ml-2 text-slate-600 hover:underline" @click="regenerate(batch)">Reprint</button>
                            <button class="ml-2 text-red-600 hover:underline" @click="deleteBatch(batch)">Delete</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
