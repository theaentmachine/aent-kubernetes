<?php

namespace TheAentMachine\AentKubernetes\Kubernetes\Object;

use TheAentMachine\Service\Service;

abstract class AbstractK8sObject
{
    abstract public static function getKind(): string;
    public static function getApiVersion(): string
    {
        return 'v1';
    }
    /**
     * @param Service $service
     * @param null|string $name
     * @return mixed[]
     */
    abstract public static function serializeFromService(Service $service, ?string $name = null): array;
}
