<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import Heading from '@/components/Heading.vue';
import { edit } from '@/routes/appearance';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Megjelenési beállítások',
                href: edit(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Megjelenési beállítások" />

    <h1 class="sr-only">Megjelenési beállítások</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Megjelenési beállítások"
            description="A fiók megjelenési beállításainak módosítása"
        />
        <AppearanceTabs />
    </div>
</template>
