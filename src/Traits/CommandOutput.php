<?php

namespace Timberhub\Traits;

trait CommandOutput {
    /**
     * Function to output a message to the console
     */
    protected function output(string $message): void {
        $this->output->writeln($message);
    }
}