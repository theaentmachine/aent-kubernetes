<?php

namespace TheAentMachine\AentKubernetes\Kubernetes;

use TheAentMachine\Service\Enum\EnvVariableTypeEnum;
use TheAentMachine\Service\Environment\EnvVariable;
use TheAentMachine\Service\Service;

class K8sSecret extends AbstractK8sObject
{
    public static function getKind(): string
    {
        return 'Secret';
    }

    public static function serializeFromService(Service $service): array
    {
        $secrets = [];

        /** @var EnvVariable $envVar */
        foreach ($service->getEnvironment() as $key => $envVar) {
            if ($envVar->getType() === EnvVariableTypeEnum::SHARED_SECRET) {
                $secrets[$key] = $envVar;
            }
        }

        $name = $service->getServiceName() . '-' . strtolower(self::getKind());
        $array = self::baseSerialize($name);

        /** @var EnvVariable $envVar */
        foreach ($secrets as $key => $envVar) {
            $array['stringData'][$key] = $envVar->getValue();
        }

        return $array;
    }
}
