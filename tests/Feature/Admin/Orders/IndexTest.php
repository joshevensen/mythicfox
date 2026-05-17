<?php

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('unauthenticated visit redirects to login', function () {
    auth()->logout();

    $this->get(route('orders.index'))->assertRedirect(route('login'));
});

test('authenticated visit returns 200 and renders Orders/Index with paginator shape', function () {
    Order::factory()->count(3)->create([
        'order_date' => Carbon::now()->subDays(2),
    ]);

    $this->get(route('orders.index', ['date_window' => '90']))->assertOk()->assertInertia(
        fn ($page) => $page
            ->component('Orders/Index')
            ->has('orders.data', 3)
            ->where('orders.meta.per_page', 50)
            ->where('orders.meta.current_page', 1)
            ->where('orders.meta.total', 3)
            ->has('meta.statuses')
            ->has('meta.date_windows', 6)
            ->where('meta.date_windows.0.value', '30')
            ->where('meta.date_windows.0.label', 'Last 30 Days')
    );
});

test('default sort is order_date desc', function () {
    Order::factory()->create([
        'tcgplayer_order_number' => 'OLDER-ORDER',
        'order_date' => Carbon::now()->subDays(10),
    ]);
    Order::factory()->create([
        'tcgplayer_order_number' => 'NEWER-ORDER',
        'order_date' => Carbon::now()->subDays(1),
    ]);

    $this->get(route('orders.index', ['date_window' => '90']))->assertInertia(
        fn ($page) => $page
            ->where('orders.data.0.tcgplayer_order_number', 'NEWER-ORDER')
            ->where('orders.data.1.tcgplayer_order_number', 'OLDER-ORDER')
    );
});

test('sort by order_date asc reorders results', function () {
    Order::factory()->create([
        'tcgplayer_order_number' => 'OLDEST',
        'order_date' => Carbon::now()->subDays(10),
    ]);
    Order::factory()->create([
        'tcgplayer_order_number' => 'NEWEST',
        'order_date' => Carbon::now()->subDays(1),
    ]);

    $this->get(route('orders.index', ['sort' => 'order_date', 'dir' => 'asc', 'date_window' => '90']))
        ->assertInertia(
            fn ($page) => $page
                ->where('orders.data.0.tcgplayer_order_number', 'OLDEST')
                ->where('orders.data.1.tcgplayer_order_number', 'NEWEST')
        );
});

test('filter by status=Canceled narrows results to canceled orders only', function () {
    Order::factory()->create(['tcgplayer_status' => 'Completed - Paid']);
    Order::factory()->canceled()->create(['tcgplayer_status' => 'Canceled']);
    Order::factory()->canceled()->create(['tcgplayer_status' => 'Canceled']);

    $this->get(route('orders.index', ['status' => 'Canceled', 'date_window' => 'all']))
        ->assertInertia(
            fn ($page) => $page
                ->has('orders.data', 2)
                ->where('orders.data.0.tcgplayer_status', 'Canceled')
                ->where('orders.data.1.tcgplayer_status', 'Canceled')
        );
});

test('date_window=30 excludes orders older than 30 days', function () {
    Order::factory()->create([
        'tcgplayer_order_number' => 'IN-WINDOW',
        'order_date' => Carbon::now()->subDays(5),
    ]);
    Order::factory()->create([
        'tcgplayer_order_number' => 'OUT-OF-WINDOW',
        'order_date' => Carbon::now()->subDays(45),
    ]);

    $this->get(route('orders.index', ['date_window' => '30']))->assertInertia(
        fn ($page) => $page
            ->has('orders.data', 1)
            ->where('orders.data.0.tcgplayer_order_number', 'IN-WINDOW')
    );
});

test('date_window=all bypasses the date filter', function () {
    Order::factory()->create([
        'tcgplayer_order_number' => 'RECENT',
        'order_date' => Carbon::now()->subDays(5),
    ]);
    Order::factory()->create([
        'tcgplayer_order_number' => 'ANCIENT',
        'order_date' => Carbon::now()->subYears(3),
    ]);

    $this->get(route('orders.index', ['date_window' => 'all']))->assertInertia(
        fn ($page) => $page->has('orders.data', 2)
    );
});

test('visiting without date_window redirects to url with default 90-day window', function () {
    $this->get(route('orders.index'))
        ->assertRedirect(route('orders.index', ['date_window' => '90']));
});

test('default 90-day window excludes orders older than 90 days', function () {
    Order::factory()->create([
        'tcgplayer_order_number' => 'WITHIN-WINDOW',
        'order_date' => Carbon::now()->subDays(10),
    ]);
    Order::factory()->create([
        'tcgplayer_order_number' => 'BEYOND-WINDOW',
        'order_date' => Carbon::now()->subDays(180),
    ]);

    $this->get(route('orders.index', ['date_window' => '90']))->assertInertia(
        fn ($page) => $page
            ->has('orders.data', 1)
            ->where('orders.data.0.tcgplayer_order_number', 'WITHIN-WINDOW')
    );
});

test('status options are sourced from DISTINCT tcgplayer_status at request time', function () {
    Order::factory()->create(['tcgplayer_status' => 'Completed - Paid']);
    Order::factory()->canceled()->create(['tcgplayer_status' => 'Canceled']);
    // A new status string the controller has never seen — should appear in the
    // options list automatically per the spec.
    Order::factory()->create([
        'tcgplayer_status' => 'New Mystery Status',
        'order_date' => Carbon::now()->subDays(2),
    ]);

    $this->get(route('orders.index', ['date_window' => 'all']))->assertInertia(
        fn ($page) => $page
            ->where('meta.statuses', [
                ['value' => 'Canceled', 'label' => 'Canceled'],
                ['value' => 'Completed - Paid', 'label' => 'Completed - Paid'],
                ['value' => 'New Mystery Status', 'label' => 'New Mystery Status'],
            ])
    );
});

test('empty state renders when no orders exist', function () {
    $this->get(route('orders.index', ['date_window' => '90']))->assertInertia(
        fn ($page) => $page
            ->where('orders.meta.total', 0)
            ->has('orders.data', 0)
    );
});
