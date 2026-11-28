<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import mailAddresses from '@/routes/mail-addresses';
import MailAddressForm from './MailAddressForm.vue';
import type { MailAddressFormFields } from './MailAddressForm.vue';

type MailAddress = {
    id: number;
    mail: string;
    name: string | null;
    is_spam: boolean;
};

const props = defineProps<{
    mailAddress: MailAddress;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'E-mail címek', href: mailAddresses.index().url }],
    },
});

const form = useForm<MailAddressFormFields>({
    mail: props.mailAddress.mail,
    name: props.mailAddress.name ?? '',
    is_spam: props.mailAddress.is_spam,
});

function submit(): void {
    form.put(mailAddresses.update(props.mailAddress.id).url);
}
</script>

<template>
    <Head :title="`${mailAddress.mail} — szerkesztés`" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <Heading title="E-mail cím szerkesztése" :description="mailAddress.mail" />
        <MailAddressForm
            :form="form"
            submit-label="Mentés"
            :cancel-href="mailAddresses.show(mailAddress.id).url"
            @submit="submit"
        />
    </div>
</template>
