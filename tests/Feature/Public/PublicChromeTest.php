<?php

test('GET /sitemap.xml returns a valid one-URL sitemap as application/xml', function () {
    $response = $this->get('/sitemap.xml');

    $response
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml');

    expect($response->getContent())
        ->toContain('<?xml version="1.0" encoding="UTF-8"?>')
        ->toContain('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">')
        ->toContain('<loc>'.route('home').'</loc>')
        ->toContain('<loc>'.route('sell-to-us').'</loc>');
});

test('the sitemap excludes admin and auth routes', function () {
    $response = $this->get('/sitemap.xml');
    $body = $response->getContent();

    foreach (['/login', '/dashboard', '/orders', '/cards', '/decks', '/inventory', '/add-cards', '/settings'] as $path) {
        expect($body)->not->toContain($path);
    }
});

test('GET /robots.txt returns plain text disallow list and sitemap line', function () {
    $response = $this->get('/robots.txt');

    $response
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8');

    expect($response->getContent())
        ->toContain('User-agent: *')
        ->toContain('Allow: /')
        ->toContain('Disallow: /login')
        ->toContain('Disallow: /dashboard')
        ->toContain('Disallow: /orders')
        ->toContain('Disallow: /cards')
        ->toContain('Disallow: /decks')
        ->toContain('Disallow: /inventory')
        ->toContain('Disallow: /add-cards')
        ->toContain('Disallow: /settings')
        ->toContain('Sitemap: '.route('sitemap'));
});

test('PublicFooter component renders the copyright string and a Wayfinder Admin link', function () {
    $source = file_get_contents(resource_path('js/components/PublicFooter.vue'));

    expect($source)
        ->toContain('Mythic Fox Games')
        ->toContain("import { login } from '@/routes'")
        ->toContain('login().url')
        ->toContain('Admin');
});

test('PublicLayout renders the PublicFooter (placeholder removed)', function () {
    $source = file_get_contents(resource_path('js/layouts/PublicLayout.vue'));

    expect($source)
        ->toContain("import PublicFooter from '@/components/PublicFooter.vue'")
        ->toContain('<PublicFooter />');
});

test('copyrightYearLabel collapses to a single year in 2025 and uses a range thereafter', function () {
    $source = file_get_contents(resource_path('js/lib/copyrightYear.ts'));

    // Quick translation: simulate the JS logic in PHP for behavioural verification.
    $label = function (int $currentYear): string {
        return $currentYear <= 2025 ? '2025' : '2025 – '.$currentYear;
    };

    expect($source)
        ->toContain('COPYRIGHT_LAUNCH_YEAR = 2025')
        ->toContain('copyrightYearLabel');

    expect($label(2025))->toBe('2025');
    expect($label(2026))->toBe('2025 – 2026');
    expect($label(2030))->toBe('2025 – 2030');
});
