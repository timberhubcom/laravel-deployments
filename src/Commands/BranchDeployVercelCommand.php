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
    public $BACKEND_KEY = 'NEXT_PUBLIC_BACKEND_URL';
    public $CREATE = 'create';
    public $DELETE = 'delete';
    public $PROJECT_ENDPOINT =  'https://api.vercel.com/v9/projects/';    

    protected function configure() {
        $this->setDescription('Deploy a branch to Vercel')
            ->addOption('token', 't', InputOption::VALUE_OPTIONAL, 'The Vercel API token.')
            ->addOption('env-name', 'e', InputOption::VALUE_REQUIRED, 'The name of the env you would like to use.')
            ->addOption('subdomain', 'su', InputOption::VALUE_REQUIRED, 'The name of the subdomain you would like to use.')
            ->addOption('branch', 'b', InputOption::VALUE_REQUIRED, 'The name of the branch being deployed.')
            ->addOption('vercel_team', 'vt', InputOption::VALUE_REQUIRED, 'The name of the vercel team.')
            ->addOption('vercel_project', 'vp', InputOption::VALUE_REQUIRED, 'The id of the vercel project.')
            ->addOption('domain', 'd', InputOption::VALUE_OPTIONAL, 'The domain you\'d like to use for deployments.')
            ->addOption('action', 'a', InputOption::VALUE_REQUIRED, 'action: ' . $this->CREATE . ' or ' . $this->DELETE, $this->CREATE);
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        // Set input & output in public variables to be accessible in all functions and avoid passing it around
        $this->output = $output;
        $this->input = $input;

        try {
            // Find the project
            $project = HTTPRequest::get(
                $this->PROJECT_ENDPOINT . $this->getVercelProject(). '?teamId='. $this->getVercelTeam(),
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

        if ($this->getAction() === $this->CREATE) {
            $this->addDomainToProject();
            $this->addBackendURL();
        }

        if ($this->getAction() === $this->DELETE) { 
            $this->removeDomainURL();
            $this->removeEnvVariable();
        }
        

        return Command::SUCCESS;
    }

    protected  function headers(): array {
        return[
            "Authorization: Bearer " . $this->getToken()
        ];
    }

    protected function addDomainToProject(): void {
        $data = [
            'name' => $this->getFrontendDomain(),
            'gitBranch' => $this->getBranch(),
        ];

        $project = HTTPRequest::post(
            $this->PROJECT_ENDPOINT . $this->getVercelProject(). '/domains?teamId='. $this->getVercelTeam(),
            $data,
            $this->headers()
        );

        if ($project['httpCode'] !== 200) {
            $this->output("Failed to add domain to project.");
            $this->output($project['response']);
            return;
        }

        $this->output("Domain added to project: https://" . $this->getFrontendDomain());
    }

    protected function addBackendURL(): void {
        $data = [
            'key' => $this->BACKEND_KEY,
            'value' => 'https://' . $this->generateOpsDomain(),
            'type' => 'plain',
            'target' => ['preview'],
            'gitBranch' => $this->getBranch(),
        ];

        $project = HTTPRequest::post(
            $this->PROJECT_ENDPOINT . $this->getVercelProject(). '/env?teamId='. $this->getVercelTeam(),
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

     protected function removeDomainURL(): void {
        $project = HTTPRequest::delete(
            $this->PROJECT_ENDPOINT . $this->getVercelProject(). '/domains/' . $this->getFrontendDomain(). '?teamId='. $this->getVercelTeam(),
            $this->headers()
        );

        if ($project['httpCode'] !== 200) {
            $this->output("Failed to delete domain URL.");
            $this->output($project['response']);
            return;
        }

        $this->output("Domain URL deleted.");
    }

    protected function removeEnvVariable(): void {
        $envs = HTTPRequest::get(
            $this->PROJECT_ENDPOINT . $this->getVercelProject(). '/env?teamId='. $this->getVercelTeam(),
            $this->headers()
        );

        $data = json_decode($envs['response']);
        foreach ($data->envs as $env) {
            if (isset($env->gitBranch) && $env->gitBranch === $this->getBranch()) {
                $project = HTTPRequest::delete(
                    $this->PROJECT_ENDPOINT . $this->getVercelProject(). '/env/' . $env->id . '?teamId='. $this->getVercelTeam(),
                    $this->headers()
                );

                if ($project['httpCode'] !== 200) {
                    $this->output("Failed to remove " . $env->key ." env variable.");
                    $this->output($project['response']);
                } else {
                    $this->output("Env " . $env->key ." variable deleted.");
                }

            }
        }    
    }
}
