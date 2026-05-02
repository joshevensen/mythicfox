<?php

test('useMoney formats cents to USD and renders an em-dash for null', function () {
    $source = file_get_contents(resource_path('js/composables/useMoney.ts'));

    expect($source)
        ->toContain("style: 'currency'")
        ->toContain("currency: 'USD'")
        ->toContain("return '—';")
        ->toContain('formatter.format(cents / 100)');
});

test('useDate formats date and datetime with Intl en-US', function () {
    $source = file_get_contents(resource_path('js/composables/useDate.ts'));

    expect($source)
        ->toContain("'en-US'")
        ->toContain("month: 'short'")
        ->toContain("day: 'numeric'")
        ->toContain("year: 'numeric'")
        ->toContain('hour12: true');
});

test('MfStatusPill maps the documented status pairs to brand-free Tailwind colors', function () {
    $source = file_get_contents(resource_path('js/components/MfStatusPill.vue'));

    expect($source)
        ->toContain("status === 'Completed - Paid'")
        ->toContain('bg-emerald-500')
        ->toContain('bg-amber-500')
        ->toContain('bg-red-500')
        ->toContain('bg-slate-200')
        ->toContain('pi-check')
        ->toContain('pi-clock')
        ->toContain('pi-times');
});

test('MfMoney binds to useMoney and respects align prop', function () {
    $source = file_get_contents(resource_path('js/components/MfMoney.vue'));

    expect($source)
        ->toContain('useMoney')
        ->toContain('formatCents(props.cents)')
        ->toContain("align === 'right' ? 'text-right' : 'text-left'");
});

test('MfCardIdentity normalizes card and orderItem shapes into one identity object', function () {
    $source = file_get_contents(resource_path('js/components/MfCardIdentity.vue'));

    expect($source)
        ->toContain('card?: CardShape')
        ->toContain('orderItem?: OrderItemShape')
        ->toContain('compact?: boolean');
});

test('MfMonospaceId renders the value in a monospace font', function () {
    $source = file_get_contents(resource_path('js/components/MfMonospaceId.vue'));

    expect($source)->toContain('font-mono');
});
