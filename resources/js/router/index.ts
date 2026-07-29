import { createRouter, createWebHistory } from 'vue-router';

const router = createRouter({
    history: createWebHistory(),
    routes: [
        {
            path: '/',
            name: 'templates',
            component: () => import('../views/TemplatesListView.vue'),
        },
        {
            path: '/templates/:id',
            name: 'designer',
            component: () => import('../views/DesignerView.vue'),
            props: true,
            meta: { fullscreen: true },
        },
        {
            path: '/records',
            name: 'records',
            component: () => import('../views/IdRecordsView.vue'),
        },
        {
            path: '/records/trash',
            name: 'records-trash',
            component: () => import('../views/TrashView.vue'),
        },
        {
            path: '/generate',
            name: 'generate',
            component: () => import('../views/GenerateView.vue'),
        },
    ],
});

export default router;
