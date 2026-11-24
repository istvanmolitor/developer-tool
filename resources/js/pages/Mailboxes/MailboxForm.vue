<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type Option = { value: string; label: string };

export type MailboxFormFields = {
    name: string;
    email: string;
    from_name: string;
    username: string;
    password: string;
    imap_host: string;
    imap_port: number;
    imap_encryption: string;
    imap_folder: string;
    smtp_host: string;
    smtp_port: number;
    smtp_encryption: string;
    is_active: boolean;
};

const props = defineProps<{
    form: InertiaForm<MailboxFormFields>;
    encryptions: Option[];
    submitLabel: string;
    cancelHref: string;
    passwordOptional?: boolean;
}>();

const emit = defineEmits<{ submit: [] }>();

function encryptionLabel(value: string): string | undefined {
    return props.encryptions.find((option) => option.value === value)?.label;
}
</script>

<template>
    <form
        class="flex max-w-2xl flex-col gap-6"
        @submit.prevent="emit('submit')"
    >
        <div class="flex flex-col gap-2">
            <Label for="name">Név</Label>
            <Input
                id="name"
                v-model="form.name"
                type="text"
                placeholder="AI agent 1"
                autocomplete="off"
            />
            <InputError :message="form.errors.name" />
        </div>

        <fieldset class="flex flex-col gap-4">
            <legend class="mb-2 text-sm font-semibold">Fiók</legend>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="flex flex-col gap-2">
                    <Label for="email">E-mail cím</Label>
                    <Input
                        id="email"
                        v-model="form.email"
                        type="email"
                        placeholder="agent@example.com"
                        autocomplete="off"
                    />
                    <InputError :message="form.errors.email" />
                </div>

                <div class="flex flex-col gap-2">
                    <Label for="from_name"
                        >Feladó neve
                        <span class="text-muted-foreground"
                            >(opcionális)</span
                        ></Label
                    >
                    <Input
                        id="from_name"
                        v-model="form.from_name"
                        type="text"
                        autocomplete="off"
                    />
                    <InputError :message="form.errors.from_name" />
                </div>

                <div class="flex flex-col gap-2">
                    <Label for="username">Felhasználónév</Label>
                    <Input
                        id="username"
                        v-model="form.username"
                        type="text"
                        placeholder="agent@example.com"
                        autocomplete="off"
                    />
                    <InputError :message="form.errors.username" />
                </div>

                <div class="flex flex-col gap-2">
                    <Label for="password">Jelszó</Label>
                    <Input
                        id="password"
                        v-model="form.password"
                        type="password"
                        autocomplete="new-password"
                        :placeholder="
                            passwordOptional
                                ? 'Hagyd üresen a megtartáshoz'
                                : ''
                        "
                    />
                    <InputError :message="form.errors.password" />
                </div>
            </div>
        </fieldset>

        <fieldset class="flex flex-col gap-4">
            <legend class="mb-2 text-sm font-semibold">
                Bejövő levelek (IMAP)
            </legend>

            <div class="grid gap-4 sm:grid-cols-[1fr_7rem]">
                <div class="flex flex-col gap-2">
                    <Label for="imap_host">Szerver</Label>
                    <Input
                        id="imap_host"
                        v-model="form.imap_host"
                        type="text"
                        placeholder="imap.example.com"
                        autocomplete="off"
                    />
                    <InputError :message="form.errors.imap_host" />
                </div>
                <div class="flex flex-col gap-2">
                    <Label for="imap_port">Port</Label>
                    <Input
                        id="imap_port"
                        v-model.number="form.imap_port"
                        type="number"
                        min="1"
                        max="65535"
                    />
                    <InputError :message="form.errors.imap_port" />
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="flex flex-col gap-2">
                    <Label for="imap_encryption">Titkosítás</Label>
                    <Select v-model="form.imap_encryption">
                        <SelectTrigger id="imap_encryption" class="w-full">
                            <SelectValue>{{
                                encryptionLabel(form.imap_encryption)
                            }}</SelectValue>
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in encryptions"
                                :key="option.value"
                                :value="option.value"
                                >{{ option.label }}</SelectItem
                            >
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.imap_encryption" />
                </div>
                <div class="flex flex-col gap-2">
                    <Label for="imap_folder">Mappa</Label>
                    <Input
                        id="imap_folder"
                        v-model="form.imap_folder"
                        type="text"
                        placeholder="INBOX"
                        autocomplete="off"
                    />
                    <InputError :message="form.errors.imap_folder" />
                </div>
            </div>
        </fieldset>

        <fieldset class="flex flex-col gap-4">
            <legend class="mb-2 text-sm font-semibold">
                Kimenő levelek (SMTP)
            </legend>

            <div class="grid gap-4 sm:grid-cols-[1fr_7rem]">
                <div class="flex flex-col gap-2">
                    <Label for="smtp_host">Szerver</Label>
                    <Input
                        id="smtp_host"
                        v-model="form.smtp_host"
                        type="text"
                        placeholder="smtp.example.com"
                        autocomplete="off"
                    />
                    <InputError :message="form.errors.smtp_host" />
                </div>
                <div class="flex flex-col gap-2">
                    <Label for="smtp_port">Port</Label>
                    <Input
                        id="smtp_port"
                        v-model.number="form.smtp_port"
                        type="number"
                        min="1"
                        max="65535"
                    />
                    <InputError :message="form.errors.smtp_port" />
                </div>
            </div>

            <div class="flex flex-col gap-2 sm:max-w-[calc(50%-0.5rem)]">
                <Label for="smtp_encryption">Titkosítás</Label>
                <Select v-model="form.smtp_encryption">
                    <SelectTrigger id="smtp_encryption" class="w-full">
                        <SelectValue>{{
                            encryptionLabel(form.smtp_encryption)
                        }}</SelectValue>
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="option in encryptions"
                            :key="option.value"
                            :value="option.value"
                            >{{ option.label }}</SelectItem
                        >
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.smtp_encryption" />
            </div>
        </fieldset>

        <div class="flex items-center gap-2">
            <Checkbox
                id="is_active"
                :model-value="form.is_active"
                @update:model-value="
                    (value) => (form.is_active = value === true)
                "
            />
            <Label for="is_active">Aktív (az ütemezett letöltés része)</Label>
        </div>

        <div class="flex items-center gap-2">
            <Button type="submit" :disabled="form.processing">{{
                submitLabel
            }}</Button>
            <Button type="button" variant="ghost" as-child>
                <a :href="cancelHref">Mégse</a>
            </Button>
        </div>
    </form>
</template>
