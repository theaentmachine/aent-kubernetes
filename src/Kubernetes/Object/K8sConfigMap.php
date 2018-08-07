<?php

namespace TheAentMachine\AentKubernetes\Kubernetes\Object;

use TheAentMachine\Service\Enum\EnvVariableTypeEnum;
use TheAentMachine\Service\Environment\EnvVariable;
use TheAentMachine\Service\Service;
use TheAentMachine\Yaml\CommentedItem;

class K8sConfigMap extends AbstractK8sObject
{
    public static function getKind(): string
    {
        return 'ConfigMap';
    }

    public static function serializeFromService(Service $service, ?string $name = null): array
    {
        $sharedEnvVars = [];

        /** @var EnvVariable $envVar */
        foreach ($service->getEnvironment() as $key => $envVar) {
            if ($envVar->getType() === EnvVariableTypeEnum::SHARED_ENV_VARIABLE) {
                $sharedEnvVars[$key] = $envVar;
            }
        }

        $name = $name ?? $service->getServiceName() . '-configMap';
        $res = [
            'apiVersion' => self::getApiVersion(),
            'kind' => self::getKind(),
            'metadata' => [
                'name' => $name
            ]
        ];

        /** @var EnvVariable $envVar */
        foreach ($sharedEnvVars as $key => $envVar) {
            $res['data'][$key] = new CommentedItem($envVar->getValue(), $envVar->getComment());
        }

        return $res;
    }
}
