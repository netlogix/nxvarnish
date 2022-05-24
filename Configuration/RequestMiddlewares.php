<?php

use Netlogix\Nxvarnish\Middleware\CacheTagMiddleware;

return [
    'frontend' => [
        'netlogix/nxvarnish-cache-tag' => [
            'target' => CacheTagMiddleware::class,
            'after' => [
                'typo3/cms-frontend/maintenance-mode',
            ],
        ]
    ]
];
