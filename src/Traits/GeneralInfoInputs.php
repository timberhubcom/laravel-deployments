<?php

namespace Timberhub\Traits;

trait GeneralInfoInputs {
    protected function getBranch(): string {
        return $this->input->getOption('branch');
    }

    protected function getEnvName(): string {
        return $this->input->getOption('env-name');
    }

    protected function getFullEnvName(): string {
        return 'th-'.$this->getEnvName();
    }


    protected function getDomain(): string {
        return getenv('DEPLOYMENT_DOMAIN') ?? $this->input->getOption('domain');
    }

    protected function generateSiteDomain(): string {
        return  implode('.', [
            $this->getFullEnvName(),
            $this->getDomain(),
        ]);
    }

    protected function generateOpsDomain(): string {
        return  implode('.', [
            'ops',
            $this->getFullEnvName(),
            $this->getDomain(),
        ]);
    }

    protected function getFrontendDomain(): string {
        return  implode('.', [
            'app',
            $this->getFullEnvName(),
            $this->getDomain(),
        ]);
    }
}