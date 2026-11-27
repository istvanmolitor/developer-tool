<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
        <Card>
            <CardHeader>
                <CardTitle>Megjelenés</CardTitle>
                <CardDescription>
                    Világos, sötét vagy a rendszer szerinti téma.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <AppearanceTabs />
            </CardContent>
        </Card>
    </div>
</template>
