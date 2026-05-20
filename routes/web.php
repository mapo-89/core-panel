<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::redirect('/', config('core-panel.route_prefix', 'admin'));

require __DIR__.'/web/auth.php';
require __DIR__.'/web/platform.php';
require __DIR__.'/web/forms.php';
require __DIR__.'/web/profile.php';
require __DIR__.'/web/dashboard.php';
require __DIR__.'/web/admin.php';
