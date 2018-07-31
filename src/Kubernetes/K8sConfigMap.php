<?php

namespace TheAentMachine\AentKubernetes\Kubernetes;

use TheAentMachine\Service\Enum\EnvVariableTypeEnum;
use TheAentMachine\Service\Environment\EnvVariable;
use TheAentMachine\Service\Service;

class K8sConfigMap extends AbstractK8sObject
{
    public static function getKind(): string
    {
        return 'ConfigMap';
    }

    public static function serializeFromService(Service $service): array
    {
        $sharedEnvVars = [];

        /** @var EnvVariable $envVar */
        foreach ($service->getEnvironment() as $key => $envVar) {
            if ($envVar->getType() === EnvVariableTypeEnum::SHARED_ENV_VARIABLE) {
                $sharedEnvVars[$key] = $envVar;
            }
        }

        $name = $service->getServiceName() . '-' . strtolower(self::getKind());
        $array = self::baseSerialize($name);

        /** @var EnvVariable $envVar */
        foreach ($sharedEnvVars as $key => $envVar) {
            $array['data'][$key] = $envVar->getValue();
        }

        return $array;
    }
}
