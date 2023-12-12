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

    protected function getPhpVersion(): string {
        return $this->input->getOption('php-version');
    }

    protected function getQuickDeploy(): bool {
        return $this->input->getOption('quick-deploy');
    }

    protected function getDatabaseName(): string {
        return str_replace('-', '', $this->input->getOption('db-name'));
    }

    protected function getDatabaseUser(): string {
        return getenv('DB_USER') ?? $this->input->getOption('db-user');
    }

    protected function getDatabasePassword(): string {
        return getenv('DB_USER_PASSWORD') ?? $this->input->getOption('db-password');
    }

    protected function getCommands() {
        return explode(',', $this->input->getOption('commands'));
    }

    protected function getEnvVariables() {
        return $this->input->getOption('edit-env');
    }
}
