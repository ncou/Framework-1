<?php
namespace Wandu\Config;

use Wandu\Config\Contracts\Config;
use Wandu\DI\ContainerInterface;
use Wandu\DI\ServiceProviderInterface;

class ConfigServiceProvider implements ServiceProviderInterface 
{
    public function register(ContainerInterface $app)
    {
        $app->bind(Config::class, Config::class);
    }

    public function boot(ContainerInterface $app)
    {
    }
}