<?php

namespace Timberhub\Traits;

trait DigitalOceanDomainInputs {
    use GeneralInfoInputs;

    protected function getToken(): string {
        return getenv('DIGITAL_OCEAN_TOKEN') ?? $this->input->getOption('token');
    }
}
