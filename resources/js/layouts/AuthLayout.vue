<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Bot, FolderGit2, ListChecks } from '@lucide/vue';
import AppLogoMark from '@/components/AppLogoMark.vue';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();

const brand = 'DeveloperTool';
const year = new Date().getFullYear();

const features = [
    {
        icon: Bot,
        title: 'AI-ügynökök, akik dolgoznak',
        text: 'Szakértő agentek viszik a feladataidat — háttérben, valós időben.',
    },
    {
        icon: ListChecks,
        title: 'Feladatok, amelyek haladnak',
        text: 'A feladatok maguktól lépnek tovább a megfelelő ügynökhöz.',
    },
    {
        icon: FolderGit2,
        title: 'Projektek egy helyen',
        text: 'Kódbázis, postafiók és csapat — egyetlen átlátható munkatérben.',
    },
];
</script>

<template>
    <div class="relative min-h-svh lg:grid lg:grid-cols-[1.05fr_1fr]">
        <!-- Branded showcase panel (desktop) -->
        <aside
            class="relative hidden overflow-hidden text-[var(--sidebar-foreground)] lg:flex lg:flex-col"
            style="background: var(--sidebar)"
        >
            <!-- Decorative gradients & grid -->
            <div
                class="pointer-events-none absolute inset-0"
                style="
                    background:
                        radial-gradient(
                            60rem 60rem at 15% 0%,
                            rgba(31, 99, 230, 0.45),
                            transparent 60%
                        ),
                        radial-gradient(
                            45rem 45rem at 100% 100%,
                            rgba(76, 134, 245, 0.35),
                            transparent 55%
                        );
                "
            />
            <div
                class="pointer-events-none absolute inset-0 opacity-[0.07]"
                style="
                    background-image:
                        linear-gradient(
                            to right,
                            #ffffff 1px,
                            transparent 1px
                        ),
                        linear-gradient(to bottom, #ffffff 1px, transparent 1px);
                    background-size: 3rem 3rem;
                    mask-image: radial-gradient(
                        ellipse at center,
                        black 40%,
                        transparent 75%
                    );
                "
            />
            <div
                class="pointer-events-none absolute -top-24 -left-24 h-96 w-96 rounded-full opacity-40 blur-3xl"
                style="background: #1f63e6"
            />

            <div class="relative z-10 flex h-full flex-col p-10 xl:p-14">
                <Link
                    :href="home()"
                    class="flex items-center gap-3 text-lg font-semibold tracking-tight text-white"
                >
                    <AppLogoMark class="size-10 text-sm" />
                    {{ brand }}
                </Link>

                <div class="my-auto max-w-md py-12">
                    <h2
                        class="text-4xl leading-tight font-semibold tracking-tight text-white xl:text-[2.75rem]"
                    >
                        Egy munkatér az ügynökeidnek és a feladataidnak.
                    </h2>
                    <p class="mt-5 text-base/relaxed text-[var(--sidebar-foreground)]/75">
                        Projektek, AI-ügynökök és feladatok — átlátható
                        felületen, valós időben együttműködve.
                    </p>

                    <ul class="mt-10 space-y-5">
                        <li
                            v-for="feature in features"
                            :key="feature.title"
                            class="flex items-start gap-4"
                        >
                            <span
                                class="mt-0.5 flex size-10 shrink-0 items-center justify-center rounded-xl bg-white/10 ring-1 ring-white/15"
                            >
                                <component
                                    :is="feature.icon"
                                    class="size-5 text-white"
                                />
                            </span>
                            <div>
                                <p class="font-medium text-white">
                                    {{ feature.title }}
                                </p>
                                <p
                                    class="mt-0.5 text-sm text-[var(--sidebar-foreground)]/65"
                                >
                                    {{ feature.text }}
                                </p>
                            </div>
                        </li>
                    </ul>
                </div>

                <p class="text-xs text-[var(--sidebar-foreground)]/50">
                    © {{ year }} AI {{ brand }}. Minden jog fenntartva.
                </p>
            </div>
        </aside>

        <!-- Form panel -->
        <main
            class="flex min-h-svh flex-col items-center justify-center bg-background px-6 py-10 sm:px-10"
        >
            <div class="w-full max-w-sm">
                <!-- Compact brand header (mobile) -->
                <Link
                    :href="home()"
                    class="mb-8 flex items-center justify-center gap-2.5 text-lg font-semibold tracking-tight text-foreground lg:hidden"
                >
                    <AppLogoMark class="size-10 text-sm" />
                    {{ brand }}
                </Link>

                <div class="flex flex-col gap-2 text-center lg:text-left">
                    <h1
                        v-if="title"
                        class="text-2xl font-semibold tracking-tight text-foreground"
                    >
                        {{ title }}
                    </h1>
                    <p
                        v-if="description"
                        class="text-sm text-muted-foreground"
                    >
                        {{ description }}
                    </p>
                </div>

                <div class="mt-8">
                    <slot />
                </div>
            </div>
        </main>
    </div>
</template>
