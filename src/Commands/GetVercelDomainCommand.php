<?php

namespace Timberhub\Commands;

use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Timberhub\Helpers\HTTPRequest;
use Timberhub\Traits\BranchDeployVercelInputs;
use Timberhub\Traits\CommandOutput;

class GetVercelDomainCommand extends Command {
    use BranchDeployVercelInputs;
    use CommandOutput;

    protected static $defaultName = 'get:vercel:domain';

    public InputInterface $input;
    public OutputInterface $output;

    protected function configure() {
        $this->setDescription('Deploy a branch to Vercel')
            ->addOption('token', 't', InputOption::VALUE_REQUIRED, 'The Vercel API token.')
            ->addOption('vercel_domain', 'd', InputOption::VALUE_REQUIRED, 'The domain from Vercel.');
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        // Set input & output in public variables to be accessible in all functions and avoid passing it around
        $this->output = $output;
        $this->input = $input;

        try {
            // Find the deployment
            $deployment = HTTPRequest::get(
                'https://api.vercel.com/v13/deployments/' . $this->getVercelDomain(),
                $this->headers()
            );
            
            
            if ($deployment['httpCode'] !== 200) {
                $this->output("Failed to find project.");
                return Command::FAILURE;
            }

            $response = json_decode($deployment['response']);
            $aliasDomain = 'https://'.$response->alias[0];
            
            $this->output($aliasDomain);
        } catch (Exception $_) {
            $this->output("Failed to find server.");
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    protected  function headers(): array {
        return[
            "Authorization: Bearer " . $this->getToken()
        ];
    }
}
