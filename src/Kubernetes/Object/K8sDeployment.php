<?php

namespace TheAentMachine\AentKubernetes\Kubernetes\Object;

use TheAentMachine\AentKubernetes\Kubernetes\K8sUtils;
use TheAentMachine\Service\Enum\VolumeTypeEnum;
use TheAentMachine\Service\Environment\EnvVariable;
use TheAentMachine\Service\Environment\SharedEnvVariable;
use TheAentMachine\Service\Service;
use TheAentMachine\Service\Volume\NamedVolume;
use TheAentMachine\Service\Volume\Volume;
use TheAentMachine\Yaml\CommentedItem;

class K8sDeployment extends AbstractK8sObject
{

    public static function getKind(): string
    {
        return 'Deployment';
    }

    public static function getApiVersion(): string
    {
        return 'apps/v1';
    }

    /** @return mixed[] */
    public static function serializeFromService(Service $service, ?string $name = null): array
    {
        $serviceName = $name ?? $service->getServiceName();

        $initContainers = [];
        foreach ($service->getDependsOn() as $n) {
            $initContainers[] = [
                'name' => "init-$n",
                'image' => $n,
                'command' => ['sh', '-c', "until nslookup $n; do echo waiting for $n; sleep 2; done;"]
            ];
        }


        // Only 1 for the moment
        $container = [
            'name' => $serviceName,
            'image' => $service->getImage(),
            'imagePullPolicy' => 'Always',
            'resources' => [
                'requests' => [
                    'memory' => $service->getRequestMemory(),
                    'cpu' => $service->getRequestCpu(),
                ],
                'limits' => [
                    'memory' => $service->getLimitMemory(),
                    'cpu' => $service->getLimitCpu(),
                ]
            ]
        ];

        // containerEnvVariables
        $containerEnvVars = $service->getAllContainerEnvVariable();
        if ($containerEnvVars) {
            $env = [];
            foreach ($service->getAllContainerEnvVariable() as $key => $envVar) {
                /** @var EnvVariable $envVar */
                $env[] = new CommentedItem([
                    'name' => $key,
                    'value' => $envVar->getValue()
                ], $envVar->getComment());
            }
            $container['env'] = $env;
        }

        if ($service->getCommand()) {
            $container['args'] = $service->getCommand();
        }

        // Secret
        $sharedSecrets = $service->getAllSharedSecret();
        if ($sharedSecrets) {
            $secretNames = array_values(array_map(function (SharedEnvVariable $secret) {
                return K8sUtils::getSecretName($secret->getContainerId());
            }, $sharedSecrets));
            $secretNames = \array_unique($secretNames);
            $container['envFrom'] = array_map(function (string $containerId) {
                return [
                    'secretRef' => [
                        'name' => $containerId,
                        'optional' => false
                    ]
                ];
            }, $secretNames);
        }

        // ConfigMap
        $sharedEnvVars = $service->getAllSharedEnvVariable();
        if ($sharedEnvVars) {
            $configMapNames = array_values(array_map(function (SharedEnvVariable $envVariable) {
                return K8sUtils::getConfigMapName($envVariable->getContainerId());
            }, $sharedEnvVars));
            $configMapNames = \array_unique($configMapNames);
            $container['envFrom'] = array_merge($container['envFrom'] ?? [], array_map(function (string $containerId) {
                return [
                    'configMapRef' => [
                        'name' => $containerId
                    ]
                ];
            }, $configMapNames));
        }

        // PVC
        $volumes = [];
        $namedVolumes = array_filter($service->getVolumes(), function (Volume $v) {
            return VolumeTypeEnum::NAMED_VOLUME === $v->getType();
        });
        if ($namedVolumes) {
            $container['volumeMounts'] = \array_map(function (NamedVolume $v) {
                return [
                    'name' => $v->getSource(),
                    'mountPath' => $v->getTarget(),
                ];
            }, $namedVolumes);
            $volumes = \array_map(function (NamedVolume $v) {
                return [
                    'name' => $v->getSource(),
                    'persistentVolumeClaim' => [
                        'claimName' => K8sUtils::getPvcName($v->getSource()),
                    ]
                ];
            }, $namedVolumes);
        }


        $res = [
            'apiVersion' => self::getApiVersion(),
            'kind' => self::getKind(),
            'metadata' => [
                'name' => $name,
                'labels' => [
                    'app' => $serviceName
                ]
            ]
        ];
        $res['spec'] = [
            'replicas' => 1, // by default
            'selector' => [
                'matchLabels' => [
                    'app' => $serviceName,
                ],
            ],
            'template' => [
                'metadata' => [
                    'labels' => [
                        'app' => $serviceName
                    ]
                ],
                'spec' => array_filter([
                    'initContainers' => $initContainers,
                    'containers' => [
                        $container,
                    ],
                    'volumes' => $volumes,
                ])
            ]
        ];

        return $res;
    }
}
