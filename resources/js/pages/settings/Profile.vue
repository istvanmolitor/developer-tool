<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import { Upload, Trash2 } from '@lucide/vue';
import { computed, onBeforeUnmount, ref } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import AgentAvatar from '@/components/AgentAvatar.vue';
import DeleteUser from '@/components/DeleteUser.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Profilbeállítások',
                href: edit(),
            },
        ],
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);

/** Preview of the picture just chosen, until the form is submitted. */
const pickedAvatar = ref<string | null>(null);
const removeAvatar = ref(false);

const avatarPreview = computed<string | null>(() => {
    if (pickedAvatar.value !== null) {
        return pickedAvatar.value;
    }

    return removeAvatar.value ? null : user.value.avatar_url;
});

function onAvatarChange(event: Event): void {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;

    releasePreview();

    removeAvatar.value = false;
    pickedAvatar.value = file === null ? null : URL.createObjectURL(file);
}

function onRemoveAvatar(): void {
    releasePreview();

    removeAvatar.value = true;
}

/** Drop the previews once the save landed; the fresh picture comes from props. */
function onSubmitted(): void {
    releasePreview();
    removeAvatar.value = false;
}

function releasePreview(): void {
    if (pickedAvatar.value !== null) {
        URL.revokeObjectURL(pickedAvatar.value);
        pickedAvatar.value = null;
    }
}

onBeforeUnmount(releasePreview);
</script>

<template>
    <Head title="Profilbeállítások" />

    <h1 class="sr-only">Profilbeállítások</h1>

    <div class="flex flex-col space-y-6">
        <Card>
            <CardHeader>
                <CardTitle>Profil</CardTitle>
                <CardDescription>A neved és az e-mail címed.</CardDescription>
            </CardHeader>
            <CardContent>
                <Form
            v-bind="ProfileController.update.form()"
            class="space-y-6"
            v-slot="{ errors, processing }"
            @success="onSubmitted"
        >
            <div class="grid gap-2">
                <Label>Profilkép</Label>
                <div class="flex items-center gap-3">
                    <AgentAvatar
                        :name="user.name || '?'"
                        :src="avatarPreview"
                        class="size-16"
                    />
                    <div class="flex flex-col items-start gap-1">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            as-child
                        >
                            <label for="avatar" class="cursor-pointer">
                                <Upload class="size-4" />
                                Kép feltöltése
                            </label>
                        </Button>
                        <Button
                            v-if="avatarPreview"
                            type="button"
                            variant="ghost"
                            size="sm"
                            class="text-destructive hover:text-destructive"
                            @click="onRemoveAvatar"
                        >
                            <Trash2 class="size-3.5" />
                            Kép törlése
                        </Button>
                    </div>
                    <input
                        id="avatar"
                        type="file"
                        name="avatar"
                        accept="image/jpeg,image/png,image/webp"
                        class="sr-only"
                        @change="onAvatarChange"
                    />
                    <input
                        type="hidden"
                        name="remove_avatar"
                        :value="removeAvatar ? '1' : ''"
                    />
                </div>
                <p class="text-xs text-muted-foreground">
                    JPG, PNG vagy WebP, legfeljebb 2 MB.
                </p>
                <InputError class="mt-2" :message="errors.avatar" />
            </div>

            <div class="grid gap-2">
                <Label for="name">Név</Label>
                <Input
                    id="name"
                    class="mt-1 block w-full"
                    name="name"
                    :default-value="user.name"
                    required
                    autocomplete="name"
                    placeholder="Teljes név"
                />
                <InputError class="mt-2" :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">E-mail cím</Label>
                <Input
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    name="email"
                    :default-value="user.email"
                    required
                    autocomplete="username"
                    placeholder="E-mail cím"
                />
                <InputError class="mt-2" :message="errors.email" />
            </div>

            <div v-if="page.props.mustVerifyEmail && !user.email_verified_at">
                <p class="-mt-4 text-sm text-muted-foreground">
                    Az e-mail címed nincs megerősítve.
                    <Link
                        :href="send()"
                        as="button"
                        class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                    >
                        Kattints ide a megerősítő e-mail újraküldéséhez.
                    </Link>
                </p>

                <div
                    v-if="page.props.status === 'verification-link-sent'"
                    class="mt-2 text-sm font-medium text-green-600"
                >
                    Új megerősítő linket küldtünk az e-mail címedre.
                </div>
            </div>

            <div class="flex items-center gap-4">
                <Button :disabled="processing" data-test="update-profile-button"
                    >Mentés</Button
                >
            </div>
                </Form>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Fiók törlése</CardTitle>
                <CardDescription>
                    A fiók és minden hozzá tartozó adat törlése.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <DeleteUser />
            </CardContent>
        </Card>
    </div>
</template>
