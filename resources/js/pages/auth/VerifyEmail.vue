<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

defineOptions({
    layout: {
        title: 'E-mail megerősítése',
        description:
            'Erősítsd meg az e-mail címed az imént elküldött linkre kattintva.',
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="E-mail megerősítése" />

    <div
        v-if="status === 'verification-link-sent'"
        class="mb-4 text-center text-sm font-medium text-green-600"
    >
        Új megerősítő linket küldtünk a regisztrációkor megadott e-mail címre.
    </div>

    <Form
        v-bind="send.form()"
        class="space-y-6 text-center"
        v-slot="{ processing }"
    >
        <Button :disabled="processing" variant="secondary">
            <Spinner v-if="processing" />
            Megerősítő e-mail újraküldése
        </Button>

        <TextLink :href="logout()" as="button" class="mx-auto block text-sm">
            Kijelentkezés
        </TextLink>
    </Form>
</template>
