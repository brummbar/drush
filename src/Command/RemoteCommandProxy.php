<?php

declare(strict_types=1);

namespace Drush\Command;

use Drush\Runtime\RedispatchHook;
use Drush\Symfony\IndiscriminateInputDefinition;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Create a placeholder proxy command to represent an unknown command.
 * We use these only when executing remote commands that do not exist
 * locally. We will let the remote end decide whether these will be
 * "command not found," or some other behavior, as the remote end might
 * have additional functionality installed.
 *
 * Also note that, for remote commands, we create the proxy command prior
 * to attempting to bootstrap Drupal further, so the proxy command may
 * be used in place of some command name that is available only for
 * Drupal sites (e.g. pm:list and friends, etc.).
 */
class RemoteCommandProxy extends Command
{
    protected RedispatchHook $redispatchHook;

    public function __construct($name, RedispatchHook $redispatchHook)
    {
        parent::__construct($name);
        $this->redispatchHook = $redispatchHook;

        // Put in a special input definition to avoid option validation errors.
        $this->setDefinition(new IndiscriminateInputDefinition());

        // Put in a placeholder array argument to avoid validation errors.
        $this->addArgument(
            'arguments',
            InputArgument::IS_ARRAY,
            'Proxy for command arguments'
        );

        // The above should be enough but isn't in Drupal 10.
        $this->ignoreValidationErrors();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->redispatchHook->redispatchIfRemote($input);
        $name = $this->getName();
        throw new \Exception("Command $name could not be executed remotely.");
    }
}
