<?php

namespace Photomover;

use Photomover\Command\PhotoMoverCommand;
use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\Console\Input\InputInterface;

class Application extends ConsoleApplication
{
    const CMD_NAME = 'photomover';

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
        $commands = parent::getDefaultCommands();
        $commands[] = new PhotoMoverCommand(self::CMD_NAME);

        return $commands;
    }

    /**
     * @inheritdoc
     */
    public function getDefinition()
    {
        $definition = parent::getDefinition();
        $definition->setArguments();

        return $definition;
    }
}
