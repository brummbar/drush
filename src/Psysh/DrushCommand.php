<?php

declare(strict_types=1);

namespace Drush\Psysh;

use Consolidation\AnnotatedCommand\AnnotatedCommand;
use Drush\Drush;
use Psy\Command\Command as BaseCommand;
use Psy\Output\ShellOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * DrushCommand is a PsySH proxy command which accepts a Drush command config
 * array and tries to build an appropriate PsySH command for it.
 */
class DrushCommand extends BaseCommand
{
    /**
     * @var \Symfony\Component\Console\Command\Command
     */
    private $command;

    /**
     * DrushCommand constructor.
     *
     * @param \Symfony\Component\Console\Command\Command $command
     *   Original Drush command.
     */
    public function __construct(Command $command)
    {
        $this->command = $command;
        parent::__construct();
    }

    /**
     * Get the namespace of this command.
     */
    public function getNamespace(): string
    {
        $parts = explode(':', $this->getName());
        return count($parts) >= 2 ? array_shift($parts) : 'global';
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setName($this->command->getName())
            ->setAliases($this->command->getAliases())
            ->setDefinition($this->command->getDefinition())
            ->setDescription($this->command->getDescription())
            ->setHelp($this->buildHelpFromCommand());
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        assert($output instanceof ShellOutput);

        $args = $input->getArguments();
        $first = array_shift($args);

        // If the first argument is an alias, assign the next argument as the
        // command.
        if (str_starts_with($first, '@')) {
            $alias = $first;
            $command = array_shift($args);
        } else {
            // Otherwise, default the alias to '@self' and use the first argument as the
            // command.
            $alias = '@self';
            $command = $first;
        }

        $options = array_diff_assoc($input->getOptions(), $this->getDefinition()->getOptionDefaults());
        $process = Drush::drush(Drush::aliasManager()->get($alias), $command, array_filter(array_values($args)), $options);
        $process->run();

        if ((!$process->isSuccessful()) && !empty($process->getErrorOutput())) {
            $output->write($process->getErrorOutput());
            // Add a newline after so the shell returns on a new line.
            $output->writeln('');
        } else {
            $output->page($process->getOutput());
        }

        return $process->getExitCode();
    }

    /**
     * Build a command help from the Drush configuration array.
     *
     * Currently it's a word-wrapped description, plus any examples provided.
     *
     *   The help string.
     */
    protected function buildHelpFromCommand(): string
    {
        $help = wordwrap($this->command->getDescription());

        $examples = [];

        if ($this->command instanceof AnnotatedCommand) {
            foreach ($this->command->getExampleUsages() as $ex => $def) {
                // Skip empty examples and things with obvious pipes...
                if (($ex === '') || (str_contains($ex, '|'))) {
                    continue;
                }

                $ex = preg_replace('/^drush\s+/', '', $ex);
                $examples[$ex] = $def;
            }
        }

        if ($examples !== []) {
            $help .= "\n\ne.g.";

            foreach ($examples as $ex => $def) {
                $help .= sprintf("\n<return>// %s</return>\n", wordwrap(OutputFormatter::escape($def), 75, "</return>\n<return>// "));
                $help .= sprintf("<return>>>> %s</return>\n", OutputFormatter::escape($ex));
            }
        }

        return $help;
    }
}
