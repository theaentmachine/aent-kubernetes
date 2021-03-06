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


    /**
     * @param Service $service
     * @param mixed[] $hosts
     * @param null|string $ingressClass
     * @param bool $isCertManager
     * @return mixed[]
     */
    public static function serializeFromService(Service $service, array $hosts, ?string $ingressClass, bool $isCertManager): array
    {
        $rules = [];
        foreach ($hosts as $host) {
            $rules[] = [
                'host' => $host['url'],
                'http' => [
                    'paths' => [
                        [
                            'backend' => [
                                'serviceName' => $service->getServiceName(),
                                'servicePort' => $host['port'],
                            ]
                        ]
                    ]
                ]
            ];
        }

        $name = $service->getServiceName() . '-ingress';
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

        if ($ingressClass) {
            $res['metadata']['annotations']['kubernetes.io/ingress.class'] = $ingressClass;
        }

        if ($isCertManager) {
            $res['metadata']['annotations']['ingress.kubernetes.io/ssl-redirect'] = 'true';
            $res['metadata']['annotations']['kubernetes.io/tls-acme'] = 'true';
            $res['metadata']['annotations']['certmanager.k8s.io/cluster-issuer'] = 'letsencrypt-prod-cluster-issuer';
            $res['spec']['tls'][] = [ 'secretName' => 'tls-certificate' ];
        }

        return $res;
    }

    public static function appendInIngressFile(string $serviceName, string $host, string $port): void
    {
    }
}
