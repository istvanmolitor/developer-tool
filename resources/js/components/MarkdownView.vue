<script setup lang="ts">
/**
 * Renders Markdown that has already been converted to safe HTML on the server
 * (e.g. Laravel's `Str::markdown(..., ['html_input' => 'strip'])`, which strips
 * raw HTML). Because the source HTML is sanitized server-side, injecting it with
 * `v-html` here is safe.
 *
 * Use this anywhere a stored Markdown document needs to be shown formatted.
 */
defineProps<{
    html: string;
}>();
</script>

<template>
    <div class="markdown-body" v-html="html" />
</template>

<style scoped>
.markdown-body {
    color: var(--foreground);
    font-size: 0.9rem;
    line-height: 1.7;
    word-wrap: break-word;
}

.markdown-body :deep(> *:first-child) {
    margin-top: 0;
}
.markdown-body :deep(> *:last-child) {
    margin-bottom: 0;
}

.markdown-body :deep(h1),
.markdown-body :deep(h2),
.markdown-body :deep(h3),
.markdown-body :deep(h4),
.markdown-body :deep(h5),
.markdown-body :deep(h6) {
    font-weight: 600;
    line-height: 1.3;
    margin-top: 1.75em;
    margin-bottom: 0.75em;
}
.markdown-body :deep(h1) {
    font-size: 1.6em;
    padding-bottom: 0.3em;
    border-bottom: 1px solid var(--border);
}
.markdown-body :deep(h2) {
    font-size: 1.3em;
    padding-bottom: 0.3em;
    border-bottom: 1px solid var(--border);
}
.markdown-body :deep(h3) {
    font-size: 1.15em;
}
.markdown-body :deep(h4) {
    font-size: 1em;
}

.markdown-body :deep(p),
.markdown-body :deep(ul),
.markdown-body :deep(ol),
.markdown-body :deep(blockquote),
.markdown-body :deep(table),
.markdown-body :deep(pre) {
    margin-top: 0;
    margin-bottom: 1em;
}

.markdown-body :deep(ul),
.markdown-body :deep(ol) {
    padding-left: 1.5em;
}
.markdown-body :deep(ul) {
    list-style: disc;
}
.markdown-body :deep(ol) {
    list-style: decimal;
}
.markdown-body :deep(li) {
    margin-bottom: 0.25em;
}
.markdown-body :deep(li > ul),
.markdown-body :deep(li > ol) {
    margin-top: 0.25em;
    margin-bottom: 0;
}

.markdown-body :deep(a) {
    color: var(--primary);
    text-decoration: underline;
    text-underline-offset: 2px;
}

.markdown-body :deep(strong) {
    font-weight: 600;
}
.markdown-body :deep(em) {
    font-style: italic;
}

.markdown-body :deep(code) {
    font-family: var(--font-mono, ui-monospace, monospace);
    font-size: 0.85em;
    background: var(--muted);
    padding: 0.15em 0.4em;
    border-radius: var(--radius-sm);
}
.markdown-body :deep(pre) {
    background: var(--muted);
    padding: 0.9em 1em;
    border-radius: var(--radius-md);
    overflow-x: auto;
}
.markdown-body :deep(pre code) {
    background: transparent;
    padding: 0;
    font-size: 0.85em;
}

.markdown-body :deep(blockquote) {
    border-left: 3px solid var(--border);
    padding-left: 1em;
    color: var(--muted-foreground);
}

.markdown-body :deep(hr) {
    border: 0;
    border-top: 1px solid var(--border);
    margin: 1.5em 0;
}

.markdown-body :deep(table) {
    width: auto;
    border-collapse: collapse;
    display: block;
    overflow-x: auto;
}
.markdown-body :deep(th),
.markdown-body :deep(td) {
    border: 1px solid var(--border);
    padding: 0.4em 0.75em;
    text-align: left;
}
.markdown-body :deep(th) {
    background: var(--muted);
    font-weight: 600;
}

.markdown-body :deep(img) {
    max-width: 100%;
    height: auto;
    border-radius: var(--radius-md);
}
</style>
