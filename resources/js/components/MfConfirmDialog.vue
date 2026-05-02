<script setup lang="ts">
import ConfirmDialog from 'primevue/confirmdialog';
</script>

<template>
    <ConfirmDialog
        :pt="{
            root: { class: 'mf-confirm-dialog' },
            footer: { class: 'flex justify-between gap-2' },
        }"
    />
</template>
