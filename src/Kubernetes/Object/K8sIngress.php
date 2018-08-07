<?php

namespace TheAentMachine\AentKubernetes\Kubernetes\Object;

use TheAentMachine\Service\Service;
use TheAentMachine\Yaml\CommentedItem;

class K8sIngress extends AbstractK8sObject
{
    public static function getKind(): string
    {
        return 'Ingress';
    }

    public static function getApiVersion(): string
    {
        return 'extensions/v1beta1';
    }


    /** @return mixed[] */
    public static function serializeFromService(Service $service, ?string $name = null): array
    {
        $rules = [];
        foreach ($service->getVirtualHosts() as $virtualHost) {
            $rules[] = [
                'host' => new CommentedItem($virtualHost['host'], (string)$virtualHost['comment']),
                'http' => [
                    'paths' => [
                        'path' => '/',
                        'backend' => [
                            'serviceName' => $service->getServiceName(),
                            'servicePort' => $virtualHost['port'],
                        ]
                    ]
                ]
            ];
        }

        $name = $name ?? $service->getServiceName() . '-ingress';
        $res = [
            'apiVersion' => self::getApiVersion(),
            'kind' => self::getKind(),
            'metadata' => [
                'name' => $name
            ],
            'spec' => [
                'rules' => $rules,
            ]
        ];

        return $res;
    }

    public static function appendInIngressFile(string $serviceName, string $host, string $port): void
    {
    }
}
