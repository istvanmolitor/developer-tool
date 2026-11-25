<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { edit, update } from '@/routes/app-settings';

const props = defineProps<{
    settings: {
        auto_start_tasks: boolean;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Beállítások', href: edit().url }],
    },
});

const form = useForm({
    auto_start_tasks: props.settings.auto_start_tasks,
});

function submit(): void {
    form.patch(update().url, { preserveScroll: true });
}
</script>

<template>
    <Head title="Beállítások" />

    <div class="px-4 py-6">
        <Heading
            title="Beállítások"
            description="Az alkalmazás általános beállításainak kezelése"
        />

        <form class="max-w-2xl space-y-6" @submit.prevent="submit">
            <div class="flex flex-col gap-1">
                <div class="flex items-center gap-2">
                    <Checkbox
                        id="auto_start_tasks"
                        :model-value="form.auto_start_tasks"
                        @update:model-value="
                            (value) => (form.auto_start_tasks = value === true)
                        "
                    />
                    <Label for="auto_start_tasks"
                        >Feladatok automatikus indítása</Label
                    >
                </div>
                <p class="pl-6 text-xs text-muted-foreground">
                    Bekapcsolva minden új feladat automatikus indítással
                    (auto_run) jön létre, így a hozzárendelt agent manuális
                    indítás nélkül megkapja.
                </p>
            </div>

            <Button type="submit" :disabled="form.processing">Mentés</Button>
        </form>
    </div>
</template>
