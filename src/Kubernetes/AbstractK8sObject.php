<?php

namespace TheAentMachine\AentKubernetes\Kubernetes;

use TheAentMachine\Service\Service;

abstract class AbstractK8sObject
{
    abstract public static function getKind(): string;

    /** @return mixed[] */
    abstract public static function serializeFromService(Service $service): array;

    /** @return mixed[] */
    protected static function baseSerialize(string $name, string $apiVersion = 'v1'): array
    {
        return [
            'apiVersion' => $apiVersion,
            'kind' => self::getKind(),
            'metadata' => [
                'name' => $name
            ]
        ];
    }
}
