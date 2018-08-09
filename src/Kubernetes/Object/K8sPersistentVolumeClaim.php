<?php

namespace TheAentMachine\AentKubernetes\Kubernetes\Object;

use TheAentMachine\AentKubernetes\Kubernetes\K8sUtils;
use TheAentMachine\Service\Volume\NamedVolume;

class K8sPersistentVolumeClaim extends AbstractK8sObject
{

    public static function getKind(): string
    {
        return 'PersistentVolumeClaim';
    }

    /** @return mixed[] */
    public static function serializeFromNamedVolume(NamedVolume $namedVolume): array
    {
        return [
            'apiVersion' => self::getApiVersion(),
            'kind' => self::getKind(),
            'metadata' => [
                'name' => K8sUtils::getPvcName($namedVolume->getSource())
            ],
            'spec' => [
                'accessModes' => ['ReadWriteOnce'],
                'resources' => [
                    'requests' => [
                        'storage' => $namedVolume->getRequestStorage(),
                    ]
                ]
            ]
        ];
    }
}
