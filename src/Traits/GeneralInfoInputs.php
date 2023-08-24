<?php

namespace Timberhub\Traits;

trait GeneralInfoInputs {
    protected function getFrontendBranch(): string {
        return getenv('FRONTEND_BRANCH') ?? $this->input->getOption('frontend_branch');
    }

    protected function getRepository(): string {
        return $this->input->getOption('repository');
    }

    protected function getRepositoryDirectory():string {
        $fullPath = explode('/', $this->getRepository());
        return end($fullPath);
    }

    protected function getBranch(): string {
        return $this->input->getOption('branch');
    }

    protected function getDomain(): string {
        return getenv('DEPLOYMENT_DOMAIN') ?? $this->input->getOption('domain');
    }

    protected function generateSiteDomain(): string {
        return  implode('.', [
            $this->getBranch(),
            $this->getDomain(),
        ]);
    }

    protected function generateOpsDomain(): string {
        return  implode('.', [
            'ops',
            $this->getBranch(),
            $this->getDomain(),
        ]);
    }

    protected function generateFrontendDomain(): string {
        return  implode('.', [
            $this->getFrontendBranch(),
            $this->getBranch(),
            $this->getDomain(),
        ]);
    }
}