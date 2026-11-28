<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import mailAddresses from '@/routes/mail-addresses';
import MailAddressForm from './MailAddressForm.vue';
import type { MailAddressFormFields } from './MailAddressForm.vue';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'E-mail címek', href: mailAddresses.index().url },
            { title: 'Új', href: mailAddresses.create().url },
        ],
    },
});

const form = useForm<MailAddressFormFields>({
    mail: '',
    name: '',
    is_spam: false,
});

function submit(): void {
    form.post(mailAddresses.store().url);
}
</script>

<template>
    <Head title="Új e-mail cím" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <Heading
            title="Új e-mail cím"
            description="Vedd fel kézzel egy e-mail címet a listába."
        />
        <MailAddressForm
            :form="form"
            submit-label="Hozzáadás"
            :cancel-href="mailAddresses.index().url"
            @submit="submit"
        />
    </div>
</template>
