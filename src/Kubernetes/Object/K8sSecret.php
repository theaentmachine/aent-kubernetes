<?php

namespace TheAentMachine\AentKubernetes\Kubernetes\Object;

use TheAentMachine\Service\Environment\EnvVariable;
use TheAentMachine\Service\Service;
use TheAentMachine\Yaml\CommentedItem;

class K8sSecret extends AbstractK8sObject
{
    public static function getKind(): string
    {
        return 'Secret';
    }

    public static function serializeFromService(Service $service, ?string $name = null): array
    {

        $name = $name ?? $service->getServiceName() . '-secrets';
        $res = [
            'apiVersion' => self::getApiVersion(),
            'kind' => self::getKind(),
            'metadata' => [
                'name' => $name
            ]
        ];

        /** @var EnvVariable $envVar */
        foreach ($service->getAllSharedSecret() as $key => $envVar) {
            $res['stringData'][$key] = new CommentedItem($envVar->getValue(), $envVar->getComment());
        }

        return $res;
    }
}
