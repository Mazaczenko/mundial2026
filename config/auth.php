<?php

use App\Models\Participant;

return [

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'participants'),
    ],

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'participants',
        ],
    ],

    'providers' => [
        'participants' => [
            'driver' => 'eloquent',
            'model' => Participant::class,
        ],
    ],

    'passwords' => [
        'participants' => [
            'provider' => 'participants',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
