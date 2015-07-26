<?php

namespace Photomover;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ContainerAwareCommand extends Command
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    public function setContainer(ContainerInterface $container)
    {
        $this->container = $container;
    }
}
