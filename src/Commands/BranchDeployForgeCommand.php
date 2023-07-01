<?php

namespace Timberhub\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class BranchDeployForgeCommand extends Command {

    protected static $defaultName = 'branch:deploy:forge';

    protected function configure() {
        $this->setDescription('Deploy a branch to the staging server')
            ->addArgument('token', InputArgument::REQUIRED, 'The Forge API token.')
            ->addArgument('server', InputArgument::REQUIRED, 'The ID of the target server.')
            ->addArgument('repo', InputArgument::REQUIRED, 'The name of the repository being deployed.')
            ->addArgument('branch', InputArgument::REQUIRED, 'The name of the branch being deployed.')
            ->addArgument('domain', InputArgument::OPTIONAL, 'The domain you\'d like to use for deployments.')
            ->addArgument('php-version', InputArgument::OPTIONAL, 'The version of PHP the site should use, e.g. php81, php80, ...', 'php81')
            ->addArgument('command', InputArgument::OPTIONAL, 'A command you would like to execute on the site, e.g. php artisan db:seed.')
            ->addArgument('edit-env', InputArgument::OPTIONAL, 'The colon-separated name and value that will be added/updated in the site\'s environment, e.g. "MY_API_KEY:my_api_key_value".')
            ->addArgument('scheduler', InputArgument::OPTIONAL, 'Setup a cronjob to run Laravel\'s scheduler.')
            ->addArgument('isolate', InputArgument::OPTIONAL, 'Enable site isolation.')
            ->addArgument('no-quick-deploy', InputArgument::OPTIONAL, 'Create your site without "Quick Deploy".');
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $output->writeln('Deploying branch to staging server...');
        return Command::SUCCESS;
    }
}