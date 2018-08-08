<?php

namespace TheAentMachine\AentKubernetes\Kubernetes;

use TheAentMachine\Service\Environment\SharedEnvVariable;
use TheAentMachine\Service\Service;

class K8sUtils
{

    /**
     * @param Service $service
     * @param array<string,SharedEnvVariable> $sharedEnvVars
     * @return array<string, array<string, SharedEnvVariable>>
     */
    public static function mapSharedEnvVarsByContainerId(Service $service, array $sharedEnvVars): array
    {
        $res = [];
        /**
         * @var string $key
         * @var SharedEnvVariable $envVar
         */
        foreach ($sharedEnvVars as $key => $envVar) {
            if (null === $containerId = $envVar->getContainerId()) {
                $containerId = '';
            }
            $res[$containerId][$key] = $envVar;
        }
        return $res;
    }

    public static function getCpuValidator(): \Closure
    {
        return function (string $value) {
            $value = trim($value);
            if (!\preg_match('/^(\d+[.])?\d+[m]?$/', $value)) {
                throw new \InvalidArgumentException("Invalid value \"$value\"." . PHP_EOL
                    . 'Hint: The CPU resource is measured in cpu units. Fractional values are allowed.' . PHP_EOL
                    . 'You can use the suffix m to mean mili. For example 100m cpu is 100 milicpu, and is the same as 0.1 cpu.' . PHP_EOL
                    . 'One cpu, in Kubernetes, is equivalent to 1 AWS vCPU, 1 GCP Core, 1 Azure vCore or 1 Hyperthread on a bare-metal Intel processor with Hyperthreading.');
            }
            return $value;
        };
    }

    public static function getMemoryValidator(): \Closure
    {
        return function (string $value) {
            $value = trim($value);
            if (!\preg_match('/^(\d+[.])?\d+([EPTGMK][i]?)?$/', $value)) {
                throw new \InvalidArgumentException("Invalid value \"$value\"." . PHP_EOL
                    . 'Hint: The memory resource is measured in bytes.' . PHP_EOL
                    . 'You can express memory as a plain integer or a fixed-point integer with one of these suffixes:' . PHP_EOL
                    . 'E, P, T, G, M, K, Ei, Pi, Ti, Gi, Mi, Ki (e.g. 128974848, 129e6, 129M , 123Mi)');
            }
            return $value;
        };
    }

    public static function getStorageValidator(): \Closure
    {
        return function (string $value) {
            $value = trim($value);
            if (!\preg_match('/^(\d+[.])?\d+([EPTGMK][i]?)?$/', $value)) {
                throw new \InvalidArgumentException("Invalid value \"$value\"." . PHP_EOL
                    . 'Hint: The storage resource is measured in bytes.' . PHP_EOL
                    . 'You can express storage as a plain integer or a fixed-point integer with one of these suffixes:' . PHP_EOL
                    . 'E, P, T, G, M, K, Ei, Pi, Ti, Gi, Mi, Ki (e.g. 128974848, 129e6, 8G, 1Ti)');
            }
            return $value;
        };
    }

    public static function getConfigMapName(?string $containerId): string
    {
        $configMapName = 'default-configMap';
        if ($containerId) {
            $configMapName = "configMap-$containerId";
        }
        return $configMapName;
    }

    public static function getSecretName(?string $containerId): string
    {
        $secretObjName = 'default-secrets';
        if ($containerId) {
            $secretObjName = "secrets-$containerId";
        }
        return $secretObjName;
    }
}
