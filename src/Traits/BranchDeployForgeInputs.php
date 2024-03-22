<?php

namespace Timberhub\Traits;

trait BranchDeployForgeInputs {
    use GeneralInfoInputs;

    protected function getToken(): string {
        return getenv('FORGE_API_TOKEN') ?? $this->input->getOption('token');
    }

    protected function getServer(): string {
        return getenv('FORGE_SERVER_ID') ?? $this->input->getOption('server');
    }

    protected function getRepository(): string {
        return $this->input->getOption('repository');
    }

    protected function getDatabaseName(): string {
        return 'th'.str_replace('-', '', $this->input->getOption('db-name'));
    }

    protected function getDatabaseUser(): string {
        return getenv('DB_USER') ?? $this->input->getOption('db-user');
    }

    protected function getDatabasePassword(): string {
        return getenv('DB_USER_PASSWORD') ?? $this->input->getOption('db-password');
    }

    protected function getPhpVersion(): string {
        $version = strtolower($this->input->getOption('php-version'));
        return in_array($version, ['php8.1', 'php8.2']) ? $version : 'php8.2';
    }

    protected function getPhpVersionCode(): string {
        return str_replace('.', '', $this->getPhpVersion());
    }

    protected function getCustomerIoConfig(string $key): string {
        $configs = [
            'site_id' => getenv('CUSTOMERIO_SITE_ID'),
            'api_key' => getenv('CUSTOMERIO_API_KEY'),
            'app_api_key' => getenv('CUSTOMERIO_APP_API_KEY'),
            'webhook_url' => getenv('CUSTOMERIO_WEBHOOK_URL'),
            'webhook_internal_key' => getenv('CUSTOMERIO_WEBHOOK_INTERNAL_KEY'),
        ];

        return $configs[$key] ?? '';
    }
}
