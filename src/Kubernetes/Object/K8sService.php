<?php

namespace TheAentMachine\AentKubernetes\Kubernetes\Object;

use TheAentMachine\Service\Service;
use TheAentMachine\Yaml\CommentedItem;

class K8sService extends AbstractK8sObject
{
    public static function getKind(): string
    {
        return 'Service';
    }

    /** @return mixed[] */
    public static function serializeFromService(Service $service, ?string $name = null): array
    {
        $ports = [];
        foreach ($service->getPorts() as $port) {
            $ports[] = new CommentedItem([
                'name' => 'http',
                'port' => $port['source'],
                'targetPort' => $port['target'],
            ], (string)$port['comment']);
        }

        $name = $name ?? $service->getServiceName();
        $res = [
            'apiVersion' => self::getApiVersion(),
            'kind' => self::getKind(),
            'metadata' => [
                'name' => $name
            ]
        ];

        $res['spec'] = array_filter([
            'selector' => [
                'app' => $name
            ],
            'ports' => $ports,
        ]);

        return $res;
    }
}
