<?php

use Illuminate\Support\Facades\Route;

test('verification notifications routes are disabled', function () {
    expect(Route::has('verification.notice'))->toBeFalse()
        ->and(Route::has('verification.send'))->toBeFalse();
});
