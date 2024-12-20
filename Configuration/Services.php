<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services()->defaults()->autowire()->autoconfigure();

    $services->load('Netlogix\\Nxvarnish\\', '../Classes/');
};
