<?php

namespace TheAentMachine\AentKubernetes\Kubernetes\Object;

use TheAentMachine\Service\Environment\EnvVariable;
use TheAentMachine\Service\Service;
use TheAentMachine\Yaml\CommentedItem;

class K8sConfigMap extends AbstractK8sObject
{
    public static function getKind(): string
    {
        return 'ConfigMap';
    }

    /**
     * @return mixed[]
     */
    public static function serializeFromService(Service $service, string $name): array
    {
        $res = [
            'apiVersion' => self::getApiVersion(),
            'kind' => self::getKind(),
            'metadata' => [
                'name' => $name
            ]
        ];

        /** @var EnvVariable $envVar */
        foreach ($service->getAllSharedEnvVariable() as $key => $envVar) {
            $res['data'][$key] = new CommentedItem($envVar->getValue(), $envVar->getComment());
        }

        return $res;
    }
}
