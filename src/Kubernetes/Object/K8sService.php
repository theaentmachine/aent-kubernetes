<?php

namespace TheAentMachine\AentKubernetes\Kubernetes\Object;

use TheAentMachine\Service\Service;

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
            $ports[] = [
                'name' => 'http',
                'port' => $port['source'],
                'targetPort' => $port['target'],
            ];
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
