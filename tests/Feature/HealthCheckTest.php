<?php

use Illuminate\Support\Facades\DB;

it('boots the application and reaches the real test database', function () {
    // Proves two things at once: the app can fully boot through the normal
    // HTTP kernel (a broken service provider would fail here), and the
    // configured DB_DATABASE really is flowstock_test, not dev data.
    expect(DB::connection()->getDatabaseName())->toBe('flowstock_test');

    $this->get('/up')->assertStatus(200);
});
