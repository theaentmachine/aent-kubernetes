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

    /** @return mixed[] */
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
        ]);

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
                    'spec' => [
                        'containers' => [
                            $container
                        ]
                    ]
                ]
            ]
        ];

        return $res;
    }
}
