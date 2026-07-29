<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useTemplatesStore } from '../stores/templates';
import { SIZE_PRESETS, toMm } from '../lib/units';
import IconButton from '../components/IconButton.vue';
import PencilIcon from '../components/icons/PencilIcon.vue';
import DuplicateIcon from '../components/icons/DuplicateIcon.vue';
import TrashIcon from '../components/icons/TrashIcon.vue';
import type { Unit } from '../types/design';

const router = useRouter();
const templates = useTemplatesStore();
const showCreate = ref(false);
const creating = ref(false);

const form = reactive({
    name: '',
    presetIndex: 0,
    width: SIZE_PRESETS[0].width,
    height: SIZE_PRESETS[0].height,
    unit: SIZE_PRESETS[0].unit as Unit,
    hasBack: false,
});

function applyPreset(): void {
    const preset = SIZE_PRESETS[form.presetIndex];
    form.width = preset.width;
    form.height = preset.height;
    form.unit = preset.unit;
}

async function createTemplate(): Promise<void> {
    if (!form.name.trim()) return;
    creating.value = true;
    try {
        const widthMm = toMm(form.width, form.unit);
        const heightMm = toMm(form.height, form.unit);
        const template = await templates.create(form.name.trim(), widthMm, heightMm, form.unit, form.hasBack);
        showCreate.value = false;
        router.push({ name: 'designer', params: { id: template.id } });
    } finally {
        creating.value = false;
    }
}

async function remove(id: number): Promise<void> {
    if (!confirm('Delete this template? This cannot be undone.')) return;
    await templates.remove(id);
}

async function duplicate(id: number): Promise<void> {
    const template = templates.items.find((t) => t.id === id);
    if (!template) return;
    await templates.duplicate(template);
}

onMounted(() => templates.fetchAll());
</script>

<template>
    <div class="mx-auto max-w-5xl px-6 py-10">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold">ID Templates</h1>
                <p class="text-sm text-slate-500">Design reusable ID card layouts.</p>
            </div>
            <button
                class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700"
                @click="showCreate = true"
            >
                New Template
            </button>
        </div>

        <div v-if="templates.loading" class="text-sm text-slate-500">Loading…</div>

        <div v-else-if="templates.items.length === 0" class="rounded-lg border border-dashed border-slate-300 p-10 text-center text-slate-500">
            No templates yet. Create one to start designing.
        </div>

        <ul v-else class="divide-y divide-slate-200 rounded-lg border border-slate-200 bg-white">
            <li v-for="t in templates.items" :key="t.id" class="flex items-center justify-between px-4 py-3">
                <div>
                    <router-link :to="{ name: 'designer', params: { id: t.id } }" class="font-medium text-slate-900 hover:underline">
                        {{ t.name }}
                    </router-link>
                    <p class="text-xs text-slate-500">
                        {{ t.width }} × {{ t.height }} {{ t.unit }}
                        <span v-if="t.has_back">· front &amp; back</span>
                        · updated {{ new Date(t.updated_at).toLocaleString() }}
                    </p>
                </div>
                <div class="flex gap-1">
                    <router-link
                        :to="{ name: 'designer', params: { id: t.id } }"
                        title="Edit"
                        aria-label="Edit"
                        class="inline-flex h-8 w-8 items-center justify-center rounded border border-transparent text-blue-600 transition-colors hover:bg-blue-50"
                    >
                        <PencilIcon class="h-4 w-4" />
                    </router-link>
                    <IconButton label="Duplicate" @click="duplicate(t.id)"><DuplicateIcon class="h-4 w-4" /></IconButton>
                    <IconButton label="Delete" variant="danger" @click="remove(t.id)"><TrashIcon class="h-4 w-4" /></IconButton>
                </div>
            </li>
        </ul>

        <div v-if="showCreate" class="fixed inset-0 flex items-center justify-center bg-black/30 p-4">
            <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                <h2 class="mb-4 text-lg font-semibold">New Template</h2>

                <label class="mb-3 block text-sm">
                    <span class="mb-1 block text-slate-600">Name</span>
                    <input v-model="form.name" type="text" class="w-full rounded border border-slate-300 px-3 py-2" placeholder="e.g. Employee ID" />
                </label>

                <label class="mb-3 block text-sm">
                    <span class="mb-1 block text-slate-600">Preset</span>
                    <select v-model.number="form.presetIndex" class="w-full rounded border border-slate-300 px-3 py-2" @change="applyPreset">
                        <option v-for="(preset, index) in SIZE_PRESETS" :key="preset.label" :value="index">{{ preset.label }}</option>
                    </select>
                </label>

                <div class="mb-3 grid grid-cols-3 gap-2">
                    <label class="text-sm">
                        <span class="mb-1 block text-slate-600">Width</span>
                        <input v-model.number="form.width" type="number" step="0.1" class="w-full rounded border border-slate-300 px-3 py-2" />
                    </label>
                    <label class="text-sm">
                        <span class="mb-1 block text-slate-600">Height</span>
                        <input v-model.number="form.height" type="number" step="0.1" class="w-full rounded border border-slate-300 px-3 py-2" />
                    </label>
                    <label class="text-sm">
                        <span class="mb-1 block text-slate-600">Unit</span>
                        <select v-model="form.unit" class="w-full rounded border border-slate-300 px-3 py-2">
                            <option value="mm">mm</option>
                            <option value="cm">cm</option>
                            <option value="in">in</option>
                            <option value="px">px</option>
                        </select>
                    </label>
                </div>

                <label class="mb-6 flex items-center gap-2 text-sm">
                    <input v-model="form.hasBack" type="checkbox" />
                    <span>Include a back side</span>
                </label>

                <div class="flex justify-end gap-2">
                    <button class="rounded px-4 py-2 text-sm text-slate-600 hover:bg-slate-100" @click="showCreate = false">Cancel</button>
                    <button
                        class="rounded bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700 disabled:opacity-50"
                        :disabled="creating || !form.name.trim()"
                        @click="createTemplate"
                    >
                        Create &amp; Design
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
