<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import mailboxes from '@/routes/mailboxes';
import MailboxForm from './MailboxForm.vue';
import type { MailboxFormFields } from './MailboxForm.vue';

type Mailbox = Omit<MailboxFormFields, 'password' | 'from_name'> & {
    id: number;
    from_name: string | null;
};

const props = defineProps<{
    mailbox: Mailbox;
    encryptions: { value: string; label: string }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Postafiókok', href: mailboxes.index().url },
            { title: 'Szerkesztés', href: '' },
        ],
    },
});

const form = useForm<MailboxFormFields>({
    name: props.mailbox.name,
    email: props.mailbox.email,
    from_name: props.mailbox.from_name ?? '',
    username: props.mailbox.username,
    password: '',
    imap_host: props.mailbox.imap_host,
    imap_port: props.mailbox.imap_port,
    imap_encryption: props.mailbox.imap_encryption,
    imap_folder: props.mailbox.imap_folder,
    smtp_host: props.mailbox.smtp_host,
    smtp_port: props.mailbox.smtp_port,
    smtp_encryption: props.mailbox.smtp_encryption,
    is_active: props.mailbox.is_active,
});

function submit(): void {
    form.put(mailboxes.update(props.mailbox.id).url);
}
</script>

<template>
    <Head :title="`${mailbox.name} szerkesztése`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <Heading
            :title="`${mailbox.name} szerkesztése`"
            description="A jelszó mezőt üresen hagyva a tárolt jelszó megmarad."
        />

        <MailboxForm
            :form="form"
            :encryptions="encryptions"
            submit-label="Változtatások mentése"
            :cancel-href="mailboxes.show(mailbox.id).url"
            password-optional
            @submit="submit"
        />
    </div>
</template>
