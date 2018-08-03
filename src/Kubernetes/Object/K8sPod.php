<?php

namespace TheAentMachine\AentKubernetes\Kubernetes\Object;

use TheAentMachine\Service\Service;

class K8sPod extends AbstractK8sObject
{

    public static function getKind(): string
    {
        return 'Pod';
    }

    /** @return mixed[] */
    public static function serializeFromService(Service $service, ?string $name = null): array
    {
        // TODO: Implement serializeFromService() method.
        return [];
    }
}
