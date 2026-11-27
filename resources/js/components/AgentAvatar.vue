<script setup lang="ts">
import { computed } from "vue";
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";

const props = withDefaults(
    defineProps<{
        name: string;
        src?: string | null;
        class?: string;
    }>(),
    { src: null, class: "size-16" },
);

/** Up to two initials, shown while there is no picture. */
const initials = computed(() =>
    props.name
        .split(/[\s\-_]+/)
        .filter((part) => part !== "")
        .slice(0, 2)
        .map((part) => part.charAt(0).toLocaleUpperCase("hu"))
        .join(""),
);
</script>

<template>
    <Avatar :class="props.class">
        <AvatarImage v-if="src" :src="src" :alt="name" />
        <AvatarFallback class="bg-muted font-medium text-muted-foreground">
            {{ initials }}
        </AvatarFallback>
    </Avatar>
</template>
