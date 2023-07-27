<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return function (ContainerConfigurator $containerConfigurator) {
    $services = $containerConfigurator->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    $services->load('Netlogix\\Nxvarnish\\', '../Classes/');

    $services->set(\Netlogix\Nxvarnish\Event\ExposeCacheTags::class)->tag(
        'event.listener',
        [
            'identifier' => 'nxvarnish/exposecachetags-listener',
        ]
    );
};
