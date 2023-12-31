<?php

namespace Timberhub\Commands;

use Exception;
use Laravel\Forge\Forge;
use Laravel\Forge\Resources\Server;
use Laravel\Forge\Resources\Site;
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

    protected function configure()
    {
        $this->setDescription('Deploy a branch to the staging server')
            ->addOption('token', 't', InputOption::VALUE_OPTIONAL, 'The Forge API token.')
            ->addOption('server', 's', InputOption::VALUE_OPTIONAL, 'The ID of the target server.')
            ->addOption('repository', 'r', InputOption::VALUE_REQUIRED, 'The name of the repository being deployed.')
            ->addOption('branch', 'b', InputOption::VALUE_REQUIRED, 'The name of the branch being deployed.')
            ->addOption('env-name', 'e', InputOption::VALUE_REQUIRED, 'The name of the env you would like to use.')
            ->addOption('domain', 'd', InputOption::VALUE_OPTIONAL, 'The domain you\'d like to use for deployments.')
            ->addOption('db-name', 'db', InputOption::VALUE_REQUIRED, 'The db name.')
            ->addOption('db-user', 'db-u', InputOption::VALUE_OPTIONAL, 'The db username.')
            ->addOption('db-password', 'db-p', InputOption::VALUE_OPTIONAL, 'The db password.')
            ->addOption('php-version', 'php', InputOption::VALUE_OPTIONAL, 'The version of PHP the site should use, e.g. php81, php80, ...', 'php81')
            ->addOption('commands', 'c', InputOption::VALUE_OPTIONAL, 'Comma separated commands you would like to execute on the site, e.g. php artisan db:seed,php artisan migrate.')
            ->addOption('edit-env', 'env', InputOption::VALUE_OPTIONAL, 'The colon-separated name and value that will be added/updated in the site\'s environment, e.g. "MY_API_KEY:my_api_key_value".') // TODO: Add default .env file config
            ->addOption('isolate', 'iso', InputOption::VALUE_OPTIONAL, 'Enable site isolation.')
            ->addOption('quick-deploy', 'qd', InputOption::VALUE_OPTIONAL, 'Create your site with "Quick Deploy".', false);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Set input & output in public variables to be accessible in all functions and avoid passing it around
        $this->output = $output;
        $this->input = $input;

        // Set API token and set forge service to public variable
        $this->output('Forge API token: ' . $this->getToken());
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

        // Find or create the site
        $site = $this->findOrCreateSite($server);

        // Deploy the website
        $this->output('Deploying');
        $site->deploySite();

        if ($this->getCommands()) {
            foreach ($this->getCommands() as $i => $command) {
                if ($command === null || $command === "") {
                    continue;
                }

                if ($i === 0) {
                    $this->output('Executing site command(s)');
                }

                if (!empty($command)) {
                    $this->forge->executeSiteCommand($server->id, $site->id, [
                        'command' => $command,
                    ]);
                }
            }
        }

        return Command::SUCCESS;
    }

    protected function findOrCreateSite(Server $server): Site
    {
        // Retrieve all sites on the server
        $sites = $this->forge->sites($server->id);
        $domain = $this->generateOpsDomain();
        $this->output('Domain: ' . $domain);

        // Check if the site already exists
        foreach ($sites as $site) {
            if ($site->name === $domain) {
                $this->output('Found existing site.');

                if ($site->deploymentStatus === null) {
                    $this->installSite($server, $site, $domain);
                    // Finalize deployment with scripts and environment variables
                    $this->updateEnvFile($server, $site);
                }

                return $site;
            }
        }

        return $this->createSite($server, $domain);
    }

    protected function createSite(Server $server, string $domain): Site
    {
        $this->output('Creating site with domain ' . $domain);

        $data = [
            'domain' => $domain,
            'project_type' => 'php',
            'php_version' => $this->input->getOption('php-version'),
            'directory' => '/public'
        ];

        if ($this->input->getOption('isolate')) {
            $this->output('Enabling site isolation');

            $data['isolation'] = true;
            $data['username'] = $this->getBranch();
        }

        $site = $this->forge->createSite($server->id, $data);
        $this->installSite($server, $site, $domain);
        // Finalize deployment with scripts and environment variables
        $this->updateEnvFile($server, $site);

        $this->forge->executeSiteCommand($server->id, $site->id, ['command' => "make build"]);

        return $site;
    }

    protected function installSite(Server $server, Site $site, string $domain): void
    {
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

        if ($this->getQuickDeploy()) {
            $this->output('Enabling quick deploy...');

            $site->enableQuickDeploy();
            $this->output('Quick deploy enabled!');
        }

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

        // Create DB for this site
        $this->createDatabase($server, $site);
        $deployment_script = $site->getDeploymentScript();
        $site->updateDeploymentScript($this->cleanDeploymentScript($deployment_script));
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

        $this->output('Updating site environment variables');
        $envSource = $this->forge->siteEnvironmentFile($server->id, $site->id);
        $envSource = $this->updateEnvVariable('DB_DATABASE', $this->getDatabaseName(), $envSource);
        $envSource = $this->updateEnvVariable('DB_USERNAME', $this->getDatabaseUser(), $envSource);
        $envSource = $this->updateEnvVariable('DB_PASSWORD', $this->getDatabasePassword(), $envSource);

        $this->forge->updateSiteEnvironmentFile($server->id, $site->id, $envSource);
    }

    protected function updateEnvFile(Server $server, Site $site): void
    {
        $envSource = $this->forge->siteEnvironmentFile($server->id, $site->id);
        // TODO: Move to command options
        $envSource = $this->updateEnvVariable('APP_ENV', $this->getBranch(), $envSource);
        $envSource = $this->updateEnvVariable('LOCAL_DEVELOPER', $this->getBranch(), $envSource);
        $envSource = $this->updateEnvVariable('APP_URL', 'https://' . $this->generateOpsDomain(), $envSource);
        $envSource = $this->updateEnvVariable('BP_APP_URL', 'https://' . $this->generateFrontendDomain(), $envSource);
        $envSource = $this->updateEnvVariable('SANCTUM_STATEFUL_DOMAINS', $this->generateFrontendDomain() . ',' . $this->generateOpsDomain(), $envSource);
        $envSource = $this->updateEnvVariable('SESSION_DOMAIN', '.' . $this->generateSiteDomain(), $envSource);


        if ($this->getEnvVariables()) {
            $this->output('Updating environment variables');

            foreach ($this->getEnvVariables() as $env) {
                [$key, $value] = explode(':', $env, 2);

                $envSource = $this->updateEnvVariable($key, $value, $envSource);
            }
        }

        $this->forge->updateSiteEnvironmentFile($server->id, $site->id, $envSource);
    }

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
