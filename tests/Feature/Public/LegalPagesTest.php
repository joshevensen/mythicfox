<?php

test('anonymous GET /terms returns 200 and renders the public Terms Inertia page', function () {
    config()->set('app.asset_url', 'https://assets.test');

    $this->withHeader('X-Inertia', 'true')
        ->withHeader('X-Inertia-Version', hash('xxh128', 'https://assets.test'))
        ->get(route('terms'))
        ->assertOk()
        ->assertHeader('X-Inertia', 'true')
        ->assertJsonPath('component', 'public/Terms');
});

test('anonymous GET /privacy returns 200 and renders the public Privacy Inertia page', function () {
    config()->set('app.asset_url', 'https://assets.test');

    $this->withHeader('X-Inertia', 'true')
        ->withHeader('X-Inertia-Version', hash('xxh128', 'https://assets.test'))
        ->get(route('privacy'))
        ->assertOk()
        ->assertHeader('X-Inertia', 'true')
        ->assertJsonPath('component', 'public/Privacy');
});

test('the legal page stubs set page titles and placeholder copy', function () {
    $termsSource = file_get_contents(resource_path('js/pages/public/Terms.vue'));
    $privacySource = file_get_contents(resource_path('js/pages/public/Privacy.vue'));

    expect($termsSource)
        ->toContain('Terms of Service — Mythic Fox Games')
        ->toContain('Our full terms of service are coming soon')
        ->toContain('home().url');

    expect($privacySource)
        ->toContain('Privacy Policy — Mythic Fox Games')
        ->toContain('Our full privacy policy is coming soon')
        ->toContain('home().url');
});
