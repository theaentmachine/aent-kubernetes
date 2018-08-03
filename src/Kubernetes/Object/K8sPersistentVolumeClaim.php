<?php

namespace TheAentMachine\AentKubernetes\Kubernetes\Object;

use TheAentMachine\Service\Service;

class K8sPersistentVolumeClaim extends AbstractK8sObject
{

    public static function getKind(): string
    {
        return 'PersistentVolumeClaim';
    }

    /** @return mixed[] */
    public static function serializeFromService(Service $service, ?string $name = null): array
    {
        return [];
    }
}
