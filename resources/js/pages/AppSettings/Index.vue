<script setup lang="ts">
import { Head, useForm } from "@inertiajs/vue3";
import Heading from "@/components/Heading.vue";
import InputError from "@/components/InputError.vue";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { edit, update } from "@/routes/app-settings";
import { index as promptRuns } from "@/routes/prompt-runs";

const props = defineProps<{
    settings: {
        auto_start_tasks: boolean;
        pause_prompt_runs: boolean;
        daily_cost_limit_usd: string | null;
        weekly_cost_limit_usd: string | null;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: "Beállítások", href: edit().url }],
    },
});

const form = useForm({
    auto_start_tasks: props.settings.auto_start_tasks,
    pause_prompt_runs: props.settings.pause_prompt_runs,
    daily_cost_limit_usd: props.settings.daily_cost_limit_usd ?? "",
    weekly_cost_limit_usd: props.settings.weekly_cost_limit_usd ?? "",
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
            description="Az alkalmazás általános beállításai."
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

            <div class="flex flex-col gap-1">
                <div class="flex items-center gap-2">
                    <Checkbox
                        id="pause_prompt_runs"
                        :model-value="form.pause_prompt_runs"
                        @update:model-value="
                            (value) => (form.pause_prompt_runs = value === true)
                        "
                    />
                    <Label for="pause_prompt_runs"
                        >Következő futtatások megállítása</Label
                    >
                </div>
                <p class="pl-6 text-xs text-muted-foreground">
                    Bekapcsolva a sorban álló promptok nem futnak le, de
                    megmaradnak. Vészfék egy elszabadult ciklus megállításához;
                    a sort a
                    <a :href="promptRuns().url" class="underline">Prompt sor</a>
                    felületen is kezelheted.
                </p>
            </div>

            <div class="flex flex-col gap-3">
                <div>
                    <p class="text-sm font-medium">Költségkeret</p>
                    <p class="text-xs text-muted-foreground">
                        A promptok költése ezekhez mérve látszik a
                        vezérlőpulton. Üresen hagyva nincs limit.
                    </p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="flex flex-col gap-2">
                        <Label for="daily_cost_limit_usd">
                            Napi limit (USD)
                        </Label>
                        <Input
                            id="daily_cost_limit_usd"
                            v-model="form.daily_cost_limit_usd"
                            type="number"
                            min="0"
                            step="0.01"
                            inputmode="decimal"
                            placeholder="pl. 10"
                        />
                        <InputError
                            :message="form.errors.daily_cost_limit_usd"
                        />
                    </div>

                    <div class="flex flex-col gap-2">
                        <Label for="weekly_cost_limit_usd">
                            Heti limit (USD)
                        </Label>
                        <Input
                            id="weekly_cost_limit_usd"
                            v-model="form.weekly_cost_limit_usd"
                            type="number"
                            min="0"
                            step="0.01"
                            inputmode="decimal"
                            placeholder="pl. 50"
                        />
                        <InputError
                            :message="form.errors.weekly_cost_limit_usd"
                        />
                    </div>
                </div>
            </div>

            <Button type="submit" :disabled="form.processing">Mentés</Button>
        </form>
    </div>
</template>
