<?php

namespace Photomover;

use Photomover\Command\PhotoMoveCommand;
use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class Application extends BaseApplication
{
    const CMD_NAME = 'photo:move';

    public function setContainer(ContainerInterface $container)
    {
        /** @var PhotoMoveCommand $command */
        $command = $this->find(self::CMD_NAME);
        $command->setContainer($container);
    }


    /**
     * @inheritdoc
     */
    public function getDefinition()
    {
        $inputDefinition = parent::getDefinition();
        $inputDefinition->setArguments();

        return $inputDefinition;
    }

    /**
     * @inheritdoc
     */
    protected function getCommandName(InputInterface $input)
    {
        return self::CMD_NAME;
    }

    /**
     * @inheritdoc
     */
    protected function getDefaultCommands()
    {
        $defaultCommands = parent::getDefaultCommands();
        $defaultCommands[] = new PhotoMoveCommand(self::CMD_NAME);

        return $defaultCommands;
    }
}
