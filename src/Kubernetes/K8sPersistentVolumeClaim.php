<?php

namespace TheAentMachine\AentKubernetes\Kubernetes;

use TheAentMachine\Service\Service;

class K8sPersistentVolumeClaim extends AbstractK8sObject
{
    public static function getKind(): string
    {
        return 'PersistentVolumeClaim';
    }

    /** @return mixed[] */
    public static function serializeFromService(Service $service): array
    {
        // TODO: Implement serializeFromService() method.
        return [];
    }
}
