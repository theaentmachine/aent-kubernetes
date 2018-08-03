<?php

namespace TheAentMachine\AentKubernetes\Kubernetes\Object;

use TheAentMachine\Service\Service;

class K8sIngress extends AbstractK8sObject
{
    public static function getKind(): string
    {
        return 'Ingress';
    }

    /** @return mixed[] */
    public static function serializeFromService(Service $service, ?string $name = null): array
    {
        // TODO: Implement serializeFromService() method.
        return [];
    }
}
