<?php

return [
    'session_inactivity' => 30,

    'cookie_lifetime' => 30,

    'visitor_cookie_lifetime' => 365 * 24 * 60,

    'excluded_paths' => [
        'admin/*',
        'api/*',
        'login',
        'register',
        'cart',
        'checkout*',
    ],

    'bot_user_agents' => [
        'bot',
        'crawl',
        'spider',
        'slurp',
        'bingbot',
        'googlebot',
        'facebookexternalhit',
        'twitterbot',
        'applebot',
        'linkedinbot',
        'semrushbot',
        'ahrefsbot',
        'mj12bot',
        'yandexbot',
        'baidubot',
        'duckduckbot',
        'telegrambot',
        'whatsapp',
    ],
];
