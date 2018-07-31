<?php

namespace TheAentMachine\AentKubernetes\Command;

use TheAentMachine\Aenthill\CommonEvents;
use TheAentMachine\Command\AbstractJsonEventCommand;
use TheAentMachine\Service\Service;

class NewDeployKubernetesJobEvent extends AbstractJsonEventCommand
{

    protected function getEventName(): string
    {
        return CommonEvents::NEW_DEPLOY_KUBERNETES_JOB_EVENT;
    }

    protected function executeJsonEvent(array $payload): ?array
    {
        $service = Service::parsePayload($payload);
        $serviceName = $service->getServiceName();

        // TODO

        return null;
    }
}
