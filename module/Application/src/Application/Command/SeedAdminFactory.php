<?php

namespace Application\Command;

use Application\Model\UsersTable;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class SeedAdminFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        return new SeedAdmin($container->get('UsersTable'));
    }
}
