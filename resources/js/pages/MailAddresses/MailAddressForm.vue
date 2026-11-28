<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export type MailAddressFormFields = {
    mail: string;
    name: string;
    is_spam: boolean;
};

defineProps<{
    form: InertiaForm<MailAddressFormFields>;
    submitLabel: string;
    cancelHref: string;
}>();

const emit = defineEmits<{ submit: [] }>();
</script>

<template>
    <form class="flex max-w-xl flex-col gap-5" @submit.prevent="emit('submit')">
        <div class="flex flex-col gap-2">
            <Label for="mail">E-mail cím</Label>
            <Input
                id="mail"
                v-model="form.mail"
                type="email"
                placeholder="nev@pelda.hu"
                autocomplete="off"
            />
            <InputError :message="form.errors.mail" />
        </div>

        <div class="flex flex-col gap-2">
            <Label for="name">Név</Label>
            <Input
                id="name"
                v-model="form.name"
                type="text"
                placeholder="A címhez tartozó név (nem kötelező)"
                autocomplete="off"
            />
            <InputError :message="form.errors.name" />
        </div>

        <div class="flex flex-col gap-1">
            <div class="flex items-center gap-2">
                <Checkbox
                    id="is_spam"
                    :model-value="form.is_spam"
                    @update:model-value="(value) => (form.is_spam = value === true)"
                />
                <Label for="is_spam">Spam cím</Label>
            </div>
            <p class="pl-6 text-xs text-muted-foreground">
                Megjelölheted a címet spamként, hogy később kiszűrhető legyen.
            </p>
            <InputError class="pl-6" :message="form.errors.is_spam" />
        </div>

        <div class="flex items-center gap-2">
            <Button type="submit" :disabled="form.processing">{{
                submitLabel
            }}</Button>
            <Button type="button" variant="ghost" as-child>
                <Link :href="cancelHref">Mégse</Link>
            </Button>
        </div>
    </form>
</template>
