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

class BranchDeployVercelCommand extends Command {
    use BranchDeployVercelInputs;
    use CommandOutput;

    protected static $defaultName = 'branch:deploy:vercel';

    public InputInterface $input;
    public OutputInterface $output;

    protected function configure() {
        $this->setDescription('Deploy a branch to Vercel')
            ->addOption('token', 't', InputOption::VALUE_OPTIONAL, 'The Vercel API token.')
            ->addOption('env-name', 'e', InputOption::VALUE_REQUIRED, 'The name of the env you would like to use.')
            ->addOption('branch', 'b', InputOption::VALUE_REQUIRED, 'The name of the branch being deployed.')
            ->addOption('vercel_team', 'vt', InputOption::VALUE_REQUIRED, 'The name of the vercel team.')
            ->addOption('vercel_project', 'vp', InputOption::VALUE_REQUIRED, 'The name of the vercel project.')
            ->addOption('frontend_branch', 'fb', InputOption::VALUE_REQUIRED, 'The name of the frontend branch.')
            ->addOption('domain', 'd', InputOption::VALUE_OPTIONAL, 'The domain you\'d like to use for deployments.');
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        // Set input & output in public variables to be accessible in all functions and avoid passing it around
        $this->output = $output;
        $this->input = $input;

        // Set API token and set forge service to public variable
        $this->output('Vercel API token: ' . $this->getToken());

        try {
            // Find the project
            $project = HTTPRequest::get(
                'https://api.vercel.com/v9/projects/' . $this->getVercelProject(). '?teamId='. $this->getVercelTeam(),
                $this->headers()
            );

            if ($project['httpCode'] !== 200) {
                $this->output("Failed to find project.");
                return Command::FAILURE;
            }

            $this->output("Project found.");
        } catch (Exception $_) {
            $this->output("Failed to find server.");
            return Command::FAILURE;
        }

        $this->addDomainToProject();
        $this->addBackendURL();

        return Command::SUCCESS;
    }

    protected  function headers(): array {
        return[
            "Authorization: Bearer " . $this->getToken()
        ];
    }

    protected function addDomainToProject(): void {
        $data = [
            'name' => $this->generateFrontendDomain(),
            'gitBranch' => $this->getBranch(),
        ];

        $project = HTTPRequest::post(
            'https://api.vercel.com/v9/projects/' . $this->getVercelProject(). '/domains?teamId='. $this->getVercelTeam(),
            $data,
            $this->headers()
        );

        if ($project['httpCode'] !== 200) {
            $this->output("Failed to add domain to project.");
            $this->output($project['response']);
            return;
        }

        $this->output("Domain added to project.");
    }

    protected function addBackendURL(): void {
        $data = [
            'key' => 'NEXT_PUBLIC_BACKEND_URL',
            'value' => 'https://' . $this->generateOpsDomain(),
            'type' => 'plain',
            'target' => ['preview'],
            'gitBranch' => $this->getBranch(),
        ];

        $project = HTTPRequest::post(
            'https://api.vercel.com/v9/projects/' . $this->getVercelProject(). '/env?teamId='. $this->getVercelTeam(),
            $data,
            $this->headers()
        );

        if ($project['httpCode'] !== 200) {
            $this->output("Failed to add backend URL to project.");
            $this->output($project['response']);
            return;
        }

        $this->output("Backend URL added to project.");
    }
}
