<?php

test('first visit defaults to dark mode on the html element', function () {
    $response = $this->withUnencryptedCookies([])->get(route('login'));

    $response->assertOk();
    expect($response->getContent())->toContain('class="dark"');
});

test('saved appearance cookie of light suppresses the dark class', function () {
    $response = $this->withUnencryptedCookies(['appearance' => 'light'])->get(route('login'));

    $response->assertOk();
    expect($response->getContent())->not->toContain('class="dark"');
});
