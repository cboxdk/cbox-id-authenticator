<?php

use App\Providers\AppServiceProvider;
use App\Providers\NativeServiceProvider;
use App\Providers\PushTokenServiceProvider;

return [
    AppServiceProvider::class,
    NativeServiceProvider::class,
    PushTokenServiceProvider::class,
];
