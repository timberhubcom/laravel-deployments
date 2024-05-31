<?php

namespace Timberhub\Traits;

trait BranchDeployVercelInputs {
    use GeneralInfoInputs;

    protected function getToken(): string {
        return getenv('VERCEL_TOKEN') ?? $this->input->getOption('token');
    }

    protected function getVercelTeam(): string {
        return getenv('VERCEL_TEAM_ID') ?? $this->input->getOption('vercel_team');
    }

    protected function getVercelProject(): string {
        return $this->input->getOption('vercel_project');
    }

    protected function getVercelDomain(): string {
        return preg_replace("(^https?://)", "", $this->input->getOption('vercel_domain'));
    }
}
