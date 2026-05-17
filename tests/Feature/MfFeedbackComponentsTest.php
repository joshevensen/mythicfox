<?php

test('MfConfirmDialog mounts the PrimeVue ConfirmDialog primitive', function () {
    $source = file_get_contents(resource_path('js/components/MfConfirmDialog.vue'));

    expect($source)
        ->toContain("import ConfirmDialog from 'primevue/confirmdialog';")
        ->toContain('<ConfirmDialog');
});

test('useMfConfirm warns on banned generic verbs and routes severity for destructive actions', function () {
    $source = file_get_contents(resource_path('js/composables/useMfConfirm.ts'));

    expect($source)
        ->toContain("BANNED_VERBS = ['OK', 'Yes', 'Confirm', 'Continue']")
        ->toContain('console.warn')
        ->toContain("severity: options.destructive ? 'danger' : 'primary'")
        ->toContain("rejectLabel: 'Cancel'");
});

test('MfToast mounts top-right and useMfToast emits documented severity shorthands', function () {
    $toastSource = file_get_contents(resource_path('js/components/MfToast.vue'));
    $composableSource = file_get_contents(resource_path('js/composables/useMfToast.ts'));

    expect($toastSource)->toContain('position="top-right"');
    expect($composableSource)
        ->toContain("severity: 'success'")
        ->toContain("severity: 'error'")
        ->toContain("severity: 'info'")
        ->toContain('AUTO_DISMISS_MS = 4000');
});

test('useMfToast.error has no auto-dismiss life so it stays until user dismisses', function () {
    $source = file_get_contents(resource_path('js/composables/useMfToast.ts'));
    preg_match('/const error[\s\S]*?\};/', $source, $matches);

    expect($matches[0] ?? '')
        ->toContain("severity: 'error'")
        ->not->toContain('life: AUTO_DISMISS_MS');
});

test('MfErrorBanner shows Retry when onRetry is provided and Dismiss otherwise', function () {
    $source = file_get_contents(resource_path('js/components/MfErrorBanner.vue'));

    expect($source)
        ->toContain('onRetry?: () => void')
        ->toContain('v-if="onRetry"')
        ->toContain('Retry')
        ->toContain('Dismiss')
        ->toContain("(e: 'dismiss'): void")
        ->toContain('border-l-4 border-red-500');
});

test('AdminLayout mounts the real MfToast and MfConfirmDialog', function () {
    $source = file_get_contents(resource_path('js/layouts/AdminLayout.vue'));

    expect($source)
        ->toContain('<MfToast />')
        ->toContain('<MfConfirmDialog />');
});
