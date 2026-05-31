<?php

use Inertia\Testing\AssertableInertia as Assert;

test('the homepage route renders the public Home Inertia page', function () {
    $response = $this->get(route('home'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('public/Home'));
});

test('PublicLayout source provides the dark-brand shell and delegates Head to each page', function () {
    $source = file_get_contents(resource_path('js/layouts/PublicLayout.vue'));

    expect($source)
        ->toContain('bg-[#12100C]')
        ->toContain('<slot />')
        ->not->toContain("import { Head } from '@inertiajs/vue3'")
        ->not->toContain('title: string');
});

test('the public Home stub page sets the spec page title and description via the layout', function () {
    $source = file_get_contents(resource_path('js/pages/public/Home.vue'));

    expect($source)
        ->toContain('Mythic Fox Games — Buy & Sell Trading Card Games')
        ->toContain('Mythic Fox Games is your trusted source for buying and selling TCG singles');
});

test('PublicLayout includes the favicon link tags and theme color in the document head', function () {
    $response = $this->get(route('home'));

    $response->assertOk();

    $body = $response->getContent();

    expect($body)
        ->toContain('rel="icon"')
        ->toContain('href="/favicon-32x32.png"')
        ->toContain('href="/favicon-16x16.png"')
        ->toContain('href="/favicon.ico"')
        ->toContain('rel="apple-touch-icon"')
        ->toContain('href="/apple-touch-icon.png"')
        ->toContain('rel="manifest"')
        ->toContain('href="/site.webmanifest"')
        ->toContain('name="theme-color"')
        ->toContain('content="#EA5A1F"');
});

test('PublicLayout applies the dark class on the html element by default', function () {
    $response = $this->withUnencryptedCookies([])->get(route('home'));

    $response->assertOk();
    expect($response->getContent())->toContain('class="dark"');
});

test('brand color CSS variables are wired through Tailwind in app.css', function () {
    $css = file_get_contents(resource_path('css/app.css'));

    expect($css)
        ->toContain('--mf-orange: #ea5a1f')
        ->toContain('--mf-teal: #2e899b')
        ->toContain('--mf-brown: #5c2d0e')
        ->toContain('--mf-orange: #ff7b45')
        ->toContain('--color-mf-orange: var(--mf-orange)')
        ->toContain('--color-mf-teal: var(--mf-teal)')
        ->toContain('--color-mf-brown: var(--mf-brown)');
});

test('PrimeVue MythicFoxPreset pins the brand orange to the primary slot', function () {
    $preset = file_get_contents(resource_path('js/lib/primevue-preset.ts'));

    expect($preset)
        ->toContain('definePreset(Aura')
        ->toContain("500: 'var(--mf-orange)'");
});

test('app.ts wires PublicLayout for pages whose name starts with public/', function () {
    $source = file_get_contents(resource_path('js/app.ts'));

    expect($source)
        ->toContain("import PublicLayout from '@/layouts/PublicLayout.vue'")
        ->toContain("name.startsWith('public/')")
        ->toContain('return PublicLayout');
});
