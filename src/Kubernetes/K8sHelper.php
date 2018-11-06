<?php

namespace TheAentMachine\AentKubernetes\Kubernetes;

use TheAentMachine\Service\Environment\SharedEnvVariable;
use function Safe\preg_match;

final class K8sHelper
{
    /**
     * @param array<string,SharedEnvVariable> $sharedEnvVars
     * @return array<string, array<string, SharedEnvVariable>>
     */
    public static function mapSharedEnvVarsByContainerId(array $sharedEnvVars): array
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

    /**
     * @return \Closure
     */
    public static function getCpuValidator(): \Closure
    {
        return function (string $value) {
            $value = trim($value);
            if (!preg_match('/^(\d+[.])?\d+[m]?$/', $value)) {
                throw new \InvalidArgumentException("Invalid value \"$value\"." . PHP_EOL
                    . 'Hint: The CPU resource is measured in cpu units. Fractional values are allowed.' . PHP_EOL
                    . 'You can use the suffix m to mean mili. For example 100m cpu is 100 milicpu, and is the same as 0.1 cpu.' . PHP_EOL
                    . 'One cpu, in Kubernetes, is equivalent to 1 AWS vCPU, 1 GCP Core, 1 Azure vCore or 1 Hyperthread on a bare-metal Intel processor with Hyperthreading.');
            }
            return $value;
        };
    }

    /**
     * @return \Closure
     */
    public static function getMemoryValidator(): \Closure
    {
        return function (string $value) {
            $value = trim($value);
            if (!preg_match('/^(\d+[.])?\d+([EPTGMK][i]?)?$/', $value)) {
                throw new \InvalidArgumentException("Invalid value \"$value\"." . PHP_EOL
                    . 'Hint: The memory resource is measured in bytes.' . PHP_EOL
                    . 'You can express memory as a plain integer or a fixed-point integer with one of these suffixes:' . PHP_EOL
                    . 'E, P, T, G, M, K, Ei, Pi, Ti, Gi, Mi, Ki (e.g. 128974848, 129e6, 129M , 123Mi)');
            }
            return $value;
        };
    }

    /**
     * @return \Closure
     */
    public static function getStorageValidator(): \Closure
    {
        return function (string $value) {
            $value = trim($value);
            if (!preg_match('/^(\d+[.])?\d+([EPTGMK][i]?)?$/', $value)) {
                throw new \InvalidArgumentException("Invalid value \"$value\"." . PHP_EOL
                    . 'Hint: The storage resource is measured in bytes.' . PHP_EOL
                    . 'You can express storage as a plain integer or a fixed-point integer with one of these suffixes:' . PHP_EOL
                    . 'E, P, T, G, M, K, Ei, Pi, Ti, Gi, Mi, Ki (e.g. 128974848, 129e6, 8G, 1Ti)');
            }
            return $value;
        };
    }

    /**
     * @return \Closure
     */
    public static function getK8sDomainNameValidator(): \Closure
    {
        return function (string $value) {
            $value = trim($value);
            if (!preg_match('/^(?!:\/\/)([a-zA-Z0-9-_]+\.)*(#ENVIRONMENT#\.)?([a-zA-Z0-9-_]+\.)*[a-zA-Z0-9][a-zA-Z0-9-_]+\.[a-zA-Z]{2,11}?$/im', $value)) {
                throw new \InvalidArgumentException('Invalid value "' . $value . '". Hint: the domain name must not start with "http(s)://".');
            }
            return $value;
        };
    }

    /**
     * @param null|string $containerId
     * @return string
     */
    public static function getConfigMapName(?string $containerId): string
    {
        $configMapName = 'default-configMap';
        if ($containerId) {
            $configMapName = "configmap-$containerId";
        }
        return $configMapName;
    }

    /**
     * @param null|string $containerId
     * @return string
     */
    public static function getSecretName(?string $containerId): string
    {
        $secretObjName = 'default-secrets';
        if ($containerId) {
            $secretObjName = "secrets-$containerId";
        }
        return $secretObjName;
    }

    public static function getPvcName(string $sourceName): string
    {
        $name = 'pvc-' . strtolower($sourceName) . '-pvc';
        $name = str_replace('_', '-', $name);
        $name = str_replace('/', '-', $name);
        return $name;
    }
}
