<?php

namespace Timberhub\Commands;

use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Timberhub\Helpers\HTTPRequest;
use Timberhub\Traits\DigitalOceanDomainInputs;
use Timberhub\Traits\CommandOutput;

class DigitalOceanDomainCommand  extends Command {
    use DigitalOceanDomainInputs;
    use CommandOutput;

    protected static $defaultName = 'branch:domain:create:do';

    public InputInterface $input;
    public OutputInterface $output;
    public $domain = 'timberhub.io'; 

    protected function configure() {
        $this->setDescription('Register a redirection domain for Vercel to Digital Ocean')
            ->addOption('token', 't', InputOption::VALUE_REQUIRED, 'The DO API token.')
            ->addOption('domain', 'd', InputOption::VALUE_OPTIONAL, 'The domain you\'d like to use for deployments.')
            ->addOption('subdomain', 'sd', InputOption::VALUE_REQUIRED, 'The name of the app subdomain you would like to use.')
            ->addOption('env-name', 'e', InputOption::VALUE_REQUIRED, 'The name of the env you would like to use.');
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        // Set input & output in public variables to be accessible in all functions and avoid passing it around
        $this->output = $output;
        $this->input = $input;
        
        $this->addDomain();

        return Command::SUCCESS;
    }

    protected  function headers(): array {
        return[
            "Content-Type: application/json",
            "Authorization: Bearer " . $this->getToken(),
        ];
    }

    protected function addDomain(): void {
        $data = [
            'type' => "CNAME",
            'name' => str_replace('.'.$this->domain, '',$this->getFrontendDomain($this->getSubdomain())),
            'data' => 'cname.vercel-dns.com.',
            'ttl' => 30,
        ];

        $domain = HTTPRequest::post(
            'https://api.digitalocean.com/v2/domains/' . $this->domain . '/records',
            $data,
            $this->headers()
        );

        if ($domain['httpCode'] !== 200 && $domain['httpCode'] !== 201) {
            $this->output("Failed to add domain to project.");
            return;
        }

        $this->output("Domain added to project.");
    }
}
