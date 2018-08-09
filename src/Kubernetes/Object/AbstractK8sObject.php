<?php

namespace TheAentMachine\AentKubernetes\Kubernetes\Object;

abstract class AbstractK8sObject
{
    abstract public static function getKind(): string;

    public static function getApiVersion(): string
    {
        return 'v1';
    }
}
