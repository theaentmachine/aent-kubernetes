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
    public static function serializeFromService(Service $service, bool $useNodePortForIngress): array
    {
        $ports = [];
        foreach ($service->getInternalPorts() as $port) {
            $ports[] = [
                'port' => $port,
                'targetPort' => $port,
            ];
        }

        $name = $service->getServiceName();
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

        // Do we have a VirtualHost? If yes, we must go NodePort
        if ($useNodePortForIngress && !empty($service->getVirtualHosts())) {
            $res['spec']['type'] = 'NodePort';
        }

        return $res;
    }
}
