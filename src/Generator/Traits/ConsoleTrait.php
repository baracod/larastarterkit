<?php

namespace Baracod\Larastarterkit\Generator\Traits;

use Symfony\Component\Console\Output\ConsoleOutput;

trait ConsoleTrait
{
    public function consoleWriteError($message): void
    {
        $output = new ConsoleOutput;
        $output->writeln("<error> $message </error>");
    }

    public function consoleWriteComment($message): void
    {
        $output = new ConsoleOutput;
        $output->writeln("<fg=white> $message </>");
    }

    public function consoleWriteMessage($message): void
    {
        $output = new ConsoleOutput;
        $output->writeln("<comment> $message </comment>");
    }

    public function consoleWriteSuccess($message): void
    {
        $output = new ConsoleOutput;
        $output->writeln("<info> $message </info>");
    }
}
