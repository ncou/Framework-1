<?php
namespace Wandu\Console;

use Interop\Container\ContainerInterface;
use Symfony\Component\Console\Application as SymfonyApplication;
use Wandu\Console\Symfony\CommandProxy;

class Dispatcher
{
    /** @var \Interop\Container\ContainerInterface */
    protected $container;

    /** @var \Symfony\Component\Console\Application */
    protected $application;

    /** @var string[] */
    protected $commands;

    public function __construct(ContainerInterface $container, SymfonyApplication $application)
    {
        $this->container = $container;
        $this->application = $application;
        $this->commands = [];
    }

    /**
     * @param string $name
     * @param string $className
     */
    public function add($name, $className)
    {
        $this->commands[$name] = $className;
    }

    public function execute()
    {
        foreach ($this->commands as $name => $command) {
            $this->application->add(
                new CommandProxy($name, $this->container->get($command))
            );
        }
    }
}
