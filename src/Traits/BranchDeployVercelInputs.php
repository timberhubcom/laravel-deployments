<?php

namespace Timberhub\Traits;

trait BranchDeployVercelInputs {
    use GeneralInfoInputs;

    protected function getToken(): string {
        return getenv('VERCEL_TOKEN') ?? $this->input->getOption('token');
    }

    protected function getVercelProject(): string {
        return $this->input->getOption('vercel_project');
    }

    protected function getVercelTeam(): string {
        return $this->input->getOption('vercel_team');
    }

    protected function getVercelDomain(): string {
        return preg_replace("(^https?://)", "", $this->input->getOption('vercel_domain'));
    }
}
