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
        return in_array($version, ['php8.1', 'php8.2']) ? $version : 'php8.1';
    }

    protected function getPhpVersionCode(): string {
        return str_replace('.', '', $this->getPhpVersion());
    }

    protected function getSupabaseDbHost(): string {
        return $this->input->getOption('supabase-db-host');
    }

    protected function getSupabaseDbDatabase(): string {
        return $this->input->getOption('supabase-db-database');
    }

    protected function getSupabaseDbUsername(): string {
        return $this->input->getOption('supabase-db-username');
    }

    protected function getSupabaseDbPassword(): string {
        return $this->input->getOption('supabase-db-password');
    }
}
