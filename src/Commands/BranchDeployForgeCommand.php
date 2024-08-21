<?php

namespace Timberhub\Commands;

use Exception;
use Laravel\Forge\Forge;
use Laravel\Forge\Resources\Server;
use Laravel\Forge\Resources\Site;
use Laravel\Forge\Resources\Daemon;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Timberhub\Traits\BranchDeployForgeInputs;
use Timberhub\Traits\CommandOutput;

class BranchDeployForgeCommand extends Command
{
    use BranchDeployForgeInputs;
    use CommandOutput;

    protected static $defaultName = 'branch:deploy:forge';

    public Forge $forge;

    public InputInterface $input;
    public OutputInterface $output;
    public $CREATE = 'create';
    public $DELETE = 'delete';

    protected function configure()
    {
        $this->setDescription('Deploy a branch to the staging server')
            ->addOption('token', 't', InputOption::VALUE_OPTIONAL, 'The Forge API token.')
            ->addOption('server', 's', InputOption::VALUE_OPTIONAL, 'The ID of the target server.')
            ->addOption('repository', 'r', InputOption::VALUE_REQUIRED, 'The name of the repository being deployed.')
            ->addOption('branch', 'b', InputOption::VALUE_REQUIRED, 'The name of the branch being deployed.')
            ->addOption('env-name', 'e', InputOption::VALUE_REQUIRED, 'The name of the env you would like to use.')
            ->addOption('domain', 'd', InputOption::VALUE_OPTIONAL, 'The domain you\'d like to use for deployments.')
            ->addOption('php-version', 'php-v', InputOption::VALUE_OPTIONAL, 'The PHP version we are creating the env with.', 'php8.2')
            ->addOption('db-name', 'db', InputOption::VALUE_REQUIRED, 'The db name.')
            ->addOption('db-user', 'db-u', InputOption::VALUE_OPTIONAL, 'The db username.')
            ->addOption('db-password', 'db-p', InputOption::VALUE_OPTIONAL, 'The db password.')
            ->addOption('action', 'a', InputOption::VALUE_REQUIRED, 'action: ' . $this->CREATE . ' or ' . $this->DELETE, $this->CREATE);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Set input & output in public variables to be accessible in all functions and avoid passing it around
        $this->output = $output;
        $this->input = $input;

        // Set API token and set forge service to public variable
        $forge = new Forge($this->getToken());
        $this->forge = $forge;

        try {
            // Find the server
            $server = $forge->server($this->getServer());
            $this->output("Server found.");
        } catch (Exception $_) {
            $this->output("Failed to find server.");
            return Command::FAILURE;
        }

        $domain = $this->generateOpsDomain();
        $this->output('Domain: https://' . $domain);
        
        $sites = $this->forge->sites($server->id);

        if ($this->getAction() === $this->CREATE) {
            
            // Check if the site already exists
            foreach ($sites as $site) {
                if ($site->name === $domain) {
                    $this->output('Site already exist');
                    return Command::SUCCESS;
                }
            }
            // Create Site
            $site = $this->createSite($server, $domain);

            // Install
            $this->installSite($server, $site, $domain);

            // Create DB for this site
            $this->createDatabase($server, $site);

            // Finalize deployment with scripts and environment variables
            $this->updateEnvFile($server, $site);

            // clean deployment script
            $deployment_script = $site->getDeploymentScript();
            $site->updateDeploymentScript($this->cleanDeploymentScript($deployment_script));

            $this->forge->executeSiteCommand($server->id, $site->id, ['command' => "make build"]);
            $this->output('Build executed');
        }

        if ($this->getAction() === $this->DELETE) { 
            
            foreach ($sites as $site) {
                if ($site->name === $domain) {
                    $site->delete();
                    $this->output('Site deleted');
                }
            }
            $name = $this->getDatabaseName();

            foreach ($this->forge->databases($server->id) as $database) {
                if ($database->name === $name) {
                    $database->delete();
                    $this->output('DB deleted');
                }
            }
        }

        return Command::SUCCESS;
    }

    protected function createSite(Server $server, string $domain): Site
    {   
        $data = [
            'domain' => $domain,
            'project_type' => 'php',
            'directory' => '/public'
        ];
        
        $site = $this->forge->createSite($server->id, $data);
        
        $this->output('Site created with domain ' . $domain);
        return $site;
    }

    protected function installSite(Server $server, Site $site, string $domain): void
    {
        // Set PHP version
        $phpVersion = $this->getPhpVersionCode();

        $this->forge->changeSitePHPVersion($server->id, $site->id, $phpVersion);
        $this->output('PHP version set to ' . $phpVersion);

        if ($site->repositoryStatus !== 'installed') {
            $this->output('Installing Git repository');
            $site->installGitRepository([
                'provider' => 'github',
                'repository' => $this->getRepository(),
                'branch' => $this->getBranch(),
                'composer' => false,
            ], false);
            $this->output('Git repository installed');

            // wait for 20 seconds
            sleep(20);
        }

        $site->enableQuickDeploy();
        $this->output('Quick deploy enabled!');

        $certificates = $this->forge->certificates($server->id, $site->id);
        $certificate_exists = false;
        foreach ($certificates as $certificate) {
            if ($certificate->domain === $domain) {
                $certificate_exists = true;
            }
        }

        if (!$certificate_exists) {
            $this->output('Generating SSL certificate...');
            $this->forge->obtainLetsEncryptCertificate($server->id, $site->id, [
                'domains' => [$domain],
            ], false);
        }
    }

    protected function createDatabase(Server $server, Site $site): void
    {
        $name = $this->getDatabaseName();

        $db_exists = false;
        foreach ($this->forge->databases($server->id) as $database) {
            if ($database->name === $name) {
                $this->output('Database already exists.' . $name);
                $db_exists = true;
            }
        }

        if (!$db_exists) {
            $this->output('Creating database' . $name);
            $this->forge->createDatabase($server->id, [
                'name' => $name,
            ], /* wait */ true);
        }
        
    }

    protected function updateEnvFile(Server $server, Site $site): void
    {
        $this->output('Updating site environment variables');
        $envSource = $this->forge->siteEnvironmentFile($server->id, $site->id);
        $envSource = $this->updateEnvVariable('APP_ENV', $this->getFullEnvName(), $envSource);
        $envSource = $this->updateEnvVariable('APP_DEBUG', true, $envSource);
        $envSource = $this->updateEnvVariable('LOCAL_DEVELOPER', $this->getBranch(), $envSource);
        $envSource = $this->updateEnvVariable('APP_URL', 'https://' . $this->generateOpsDomain(), $envSource);
        $envSource = $this->updateEnvVariable('BP_APP_URL', 'https://' . $this->getFrontendDomain('app'), $envSource);
        $envSource = $this->updateEnvVariable('IT_APP_URL', 'https://' . $this->getFrontendDomain('it'), $envSource);
        $envSource = $this->updateEnvVariable('SANCTUM_STATEFUL_DOMAINS', $this->getFrontendDomain('app') . ',' . $this->getFrontendDomain('it') . ',' . $this->generateOpsDomain(), $envSource);
        $envSource = $this->updateEnvVariable('SESSION_DOMAIN', '.' . $this->generateSiteDomain(), $envSource);
        $envSource = $this->updateEnvVariable('DB_DATABASE', $this->getDatabaseName(), $envSource);
        $envSource = $this->updateEnvVariable('DB_USERNAME', $this->getDatabaseUser(), $envSource);
        $envSource = $this->updateEnvVariable('DB_PASSWORD', $this->getDatabasePassword(), $envSource);
        $envSource = $this->updateEnvVariable('WAREHOUSE_DB_DATABASE', $this->getDatabaseName(), $envSource);
        $envSource = $this->updateEnvVariable('WAREHOUSE_DB_USERNAME', $this->getDatabaseUser(), $envSource);
        $envSource = $this->updateEnvVariable('WAREHOUSE_DB_PASSWORD', $this->getDatabasePassword(), $envSource);
        $envSource = $this->updateEnvVariable('CUSTOMERIO_SITE_ID', $this->getCustomerIoConfig('site_id'), $envSource);
        $envSource = $this->updateEnvVariable('CUSTOMERIO_API_KEY', $this->getCustomerIoConfig('api_key'), $envSource);
        $envSource = $this->updateEnvVariable('CUSTOMERIO_APP_API_KEY', $this->getCustomerIoConfig('app_api_key'), $envSource);
        $envSource = $this->updateEnvVariable('CUSTOMERIO_WEBHOOK_URL', $this->getCustomerIoConfig('webhook_url'), $envSource);
        $envSource = $this->updateEnvVariable('CUSTOMERIO_WEBHOOK_INTERNAL_KEY', $this->getCustomerIoConfig('webhook_internal_key'), $envSource);
        $this->forge->updateSiteEnvironmentFile($server->id, $site->id, $envSource);
        $this->output('Site environment variables updated');
    }

    //utils
    protected function updateEnvVariable(string $name, string $value, string $source): string
    {
        if (!str_contains($source, "{$name}=")) {
            $source .= PHP_EOL . "{$name}={$value}";
        } else {
            $source = preg_replace("/^{$name}=[^\r\n]*/m", "{$name}={$value}", $source, 1);
        }

        return $source;
    }

    protected function cleanDeploymentScript(string $script): string
    {
        $pattern = '$FORGE_COMPOSER install';
        // Explode the content into an array of lines
        $lines = explode("\n", $script);

        // Filter out lines that start with the specified pattern
        $filteredLines = array_filter($lines, function ($line) use ($pattern) {
            return strpos($line, $pattern) !== 0;
        });

        // Combine the filtered lines into a single string
        return implode("\n", $filteredLines);
    }
}
