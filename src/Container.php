<?php

namespace Photomover;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class Container extends ContainerBuilder
{
    public function init()
    {
        $loader = new YamlFileLoader($this, new FileLocator(CONFIG_PATH));
        $loader->load('config.yml');
    }
}
