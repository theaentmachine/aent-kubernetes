<?php

namespace TheAentMachine\AentKubernetes\Kubernetes\Object;

use TheAentMachine\Service\Environment\EnvVariable;
use TheAentMachine\Service\Service;
use TheAentMachine\Yaml\CommentedItem;

class K8sDeployment extends AbstractK8sObject
{

    public static function getKind(): string
    {
        return 'Deployment';
    }

    public static function getApiVersion(): string
    {
        return 'extensions/v1beta';
    }

    /** @return mixed[]
     * @throws \TheAentMachine\Service\Exception\ServiceException
     */
    public static function serializeFromService(Service $service, ?string $name = null): array
    {
        $serviceName = $name ?? $service->getServiceName();

        $imageEnvVars = [];
        foreach ($service->getAllImageEnvVariable() as $key => $envVar) {
            /** @var EnvVariable $envVar */
            $imageEnvVars[] = new CommentedItem([
                'name' => $key,
                'value' => $envVar->getValue()
            ], $envVar->getComment());
        }

        // Only 1 for the moment
        $container = array_filter([
            'name' => $serviceName,
            'image' => $service->getImage(),
            'env' => $imageEnvVars,
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
        ]);

        $initContainers = [];
        foreach ($service->getDependsOn() as $n) {
            $initContainers[] = [
                'name' => "init-$n",
                'image' => $n,
                'command' => ['sh', '-c', "until nslookup $n; do echo waiting for $n; sleep 2; done;"]
            ];
        }

        $res = [
            'apiVersion' => self::getApiVersion(),
            'kind' => self::getKind(),
            'metadata' => [
                'name' => $name
            ]
        ];

        $res['metadata']['labels'] = [
            'app' => $serviceName
        ];
        $res['spec'] = [
            'replicas' => 1, // by default
            'selector' => [
                'matchLabels' => [
                    'app' => $serviceName,
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
                        ]
                    ])
                ]
            ]
        ];

        return $res;
    }
}
