<?php

declare(strict_types=1);

use Netlogix\Nxvarnish\Middleware\ExposeCacheTags;

return [
    'frontend' => [
        'netlogix/nxvarnish/expose-cache-tags' => [
            'target' => ExposeCacheTags::class,
            'after' => [
                'typo3/cms-core/cache-tags-attribute',
            ],
            'before' => [
                'typo3/cms-frontend/site',
            ],
        ],
    ],
];
