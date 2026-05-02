<script setup lang="ts">
import MfConfirmDialog from '@/components/MfConfirmDialog.vue';
import MfPageContainer from '@/components/MfPageContainer.vue';
import MfToast from '@/components/MfToast.vue';
import MfTopNav from '@/components/MfTopNav.vue';
</script>

<template>
    <div class="min-h-screen bg-background text-foreground">
        <MfTopNav />
        <main>
            <MfPageContainer>
                <slot />
            </MfPageContainer>
        </main>
        <MfToast />
        <MfConfirmDialog />
    </div>
</template>
