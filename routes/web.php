<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'name' => config('app.name'),
        'status' => 'ok',
    ]);
});

// The admin panel is a single page app: every /admin path returns the same
// shell and vue-router takes it from there. Deep links and refreshes therefore
// work without a server route per page. No auth here on purpose — the shell
// holds no data, and every endpoint it calls is guarded server-side.
Route::view('/admin/{path?}', 'admin')
    ->where('path', '.*')
    ->name('admin');
