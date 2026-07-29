import { defineStore } from 'pinia';
import { api } from '../lib/api';
import type { CardSide, TemplateRecord, TemplateSummary, Unit } from '../types/design';

function emptySide(): CardSide {
    return { background: { type: 'color', value: '#FFFFFF' }, elements: [] };
}

export const useTemplatesStore = defineStore('templates', {
    state: () => ({
        items: [] as TemplateSummary[],
        loading: false,
    }),

    actions: {
        async fetchAll(): Promise<void> {
            this.loading = true;
            try {
                const { data } = await api.get<TemplateSummary[]>('/templates');
                this.items = data;
            } finally {
                this.loading = false;
            }
        },

        async create(name: string, width: number, height: number, unit: Unit, hasBack: boolean): Promise<TemplateRecord> {
            const { data } = await api.post<TemplateRecord>('/templates', {
                name,
                width,
                height,
                unit,
                has_back: hasBack,
                front_design: emptySide(),
                back_design: hasBack ? emptySide() : null,
            });
            this.items.unshift(data);
            return data;
        },

        async fetch(id: number): Promise<TemplateRecord> {
            const { data } = await api.get<TemplateRecord>(`/templates/${id}`);
            return data;
        },

        async remove(id: number): Promise<void> {
            await api.delete(`/templates/${id}`);
            this.items = this.items.filter((t) => t.id !== id);
        },

        async duplicate(template: TemplateSummary): Promise<TemplateRecord> {
            const full = await this.fetch(template.id);
            const { data } = await api.post<TemplateRecord>('/templates', {
                name: `${full.name} (copy)`,
                width: Number(full.width),
                height: Number(full.height),
                unit: full.unit,
                has_back: full.has_back,
                front_design: full.front_design,
                back_design: full.back_design,
            });
            this.items.unshift(data);
            return data;
        },
    },
});
