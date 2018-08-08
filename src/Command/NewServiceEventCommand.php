<?php

namespace TheAentMachine\AentKubernetes\Command;

use TheAentMachine\Aenthill\CommonEvents;
use TheAentMachine\Aenthill\CommonMetadata;
use TheAentMachine\Aenthill\Manifest;
use TheAentMachine\AentKubernetes\Kubernetes\K8sHelper;
use TheAentMachine\AentKubernetes\Kubernetes\KubernetesServiceDirectory;
use TheAentMachine\AentKubernetes\Kubernetes\Object\K8sConfigMap;
use TheAentMachine\AentKubernetes\Kubernetes\Object\K8sDeployment;
use TheAentMachine\AentKubernetes\Kubernetes\Object\K8sIngress;
use TheAentMachine\AentKubernetes\Kubernetes\Object\K8sSecret;
use TheAentMachine\AentKubernetes\Kubernetes\Object\K8sService;
use TheAentMachine\Command\AbstractJsonEventCommand;
use TheAentMachine\Service\Environment\SharedEnvVariable;
use TheAentMachine\Service\Service;
use TheAentMachine\YamlTools\YamlTools;

class NewServiceEventCommand extends AbstractJsonEventCommand
{

    protected function getEventName(): string
    {
        return CommonEvents::NEW_SERVICE_EVENT;
    }

    /**
     * @param array $payload
     * @return array|null
     * @throws \TheAentMachine\Exception\ManifestException
     * @throws \TheAentMachine\Exception\MissingEnvironmentVariableException
     * @throws \TheAentMachine\Service\Exception\ServiceException
     */
    protected function executeJsonEvent(array $payload): ?array
    {
        $service = Service::parsePayload($payload);
        if (!$service->isForMyEnvType()) {
            return null;
        }
        $k8sHelper = new K8sHelper($this->getAentHelper());

        $k8sDirName = Manifest::mustGetMetadata(CommonMetadata::KUBERNETES_DIRNAME_KEY);
        $this->getAentHelper()->title('Kubernetes: adding/updating a service');

        $serviceName = $service->getServiceName();
        $k8sServiceDir = new KubernetesServiceDirectory($serviceName);

        if ($k8sServiceDir->exist()) {
            $this->output->writeln('☸️ <info>' . $k8sServiceDir->getDirName(true) . '</info> found!');
        } else {
            $k8sServiceDir->findOrCreate();
            $this->output->writeln('☸️ <info>' . $k8sServiceDir->getDirName(true) . '</info> was successfully created!');
        }
        $this->getAentHelper()->spacer();


        // Deployment
        if (null === $service->getRequestMemory()) {
            $service->setRequestMemory($k8sHelper->askForMemory($serviceName, true));
        }
        if (null === $service->getRequestCpu()) {
            $service->setRequestCpu($k8sHelper->askForCpu($serviceName, true));
        }
        if (null === $service->getLimitMemory()) {
            $service->setLimitMemory($k8sHelper->askForMemory($serviceName, false));
        }
        if (null === $service->getLimitCpu()) {
            $service->setLimitCpu($k8sHelper->askForCpu($serviceName, false));
        }
        $deploymentArray = K8sDeployment::serializeFromService($service, $serviceName);
        $deploymentFilename = $k8sServiceDir->getPath() . '/' . K8sDeployment::getKind() . '.yml';
        YamlTools::mergeContentIntoFile($deploymentArray, $deploymentFilename);

        // Service
        $serviceArray = K8sService::serializeFromService($service, $serviceName);
        $filename = $k8sServiceDir->getPath() . '/' . K8sService::getKind() . '.yml';
        YamlTools::mergeContentIntoFile($serviceArray, $filename);

        // Secret
        if (!empty($service->getAllSharedSecret())) {
            $sharedSecretsMap = $k8sHelper->mapSharedEnvVarsByContainerId($service, true);
            foreach ($sharedSecretsMap as $containerId => $sharedSecrets) {
                $secretObjName = 'default-secrets';
                $subFilename = K8sSecret::getKind();
                if ($containerId !== K8sHelper::NULL_CONTAINER_ID_KEY) {
                    $secretObjName = "secrets-$containerId";
                    $subFilename .= "-$containerId";
                }
                $tmpService = new Service();
                $tmpService->setServiceName($serviceName);
                /** @var SharedEnvVariable $secret */
                foreach ($sharedSecrets as $key => $secret) {
                    $tmpService->addSharedSecret($key, $secret->getValue(), $secret->getComment(), $secret->getContainerId());
                }
                $secretArray = K8sSecret::serializeFromService($tmpService, $secretObjName);
                $filename = \dirname($k8sServiceDir->getPath()) . '/' . $subFilename . '.yml';
                YamlTools::mergeContentIntoFile($secretArray, $filename);

                $newDeploymentContent = ['spec' => ['template' => ['spec' => ['containers' => [0 => ['envFrom' => [
                    [
                        'secretFrom' => $secretObjName,
                        'optional' => false
                    ]
                ]]]]]]];
                YamlTools::mergeContentIntoFile($newDeploymentContent, $deploymentFilename);
            }
        }

        // ConfigMap
        if (!empty($service->getAllSharedEnvVariable())) {
            $sharedSecretsMap = $k8sHelper->mapSharedEnvVarsByContainerId($service, true);
            foreach ($sharedSecretsMap as $containerId => $sharedEnvVars) {
                $configMapName = 'default-configMap';
                $subFilename = K8sConfigMap::getKind();
                if ($containerId !== K8sHelper::NULL_CONTAINER_ID_KEY) {
                    $configMapName = "configMap-$containerId";
                    $subFilename .= "-$containerId";
                }
                $tmpService = new Service();
                $tmpService->setServiceName($serviceName);
                /** @var SharedEnvVariable $sharedEnvVar */
                foreach ($sharedEnvVars as $key => $sharedEnvVar) {
                    $tmpService->addSharedEnvVariable($key, $sharedEnvVar->getValue(), $sharedEnvVar->getComment(), $sharedEnvVar->getContainerId());
                }
                $secretArray = K8sConfigMap::serializeFromService($service, $configMapName);
                $filename = \dirname($k8sServiceDir->getPath()) . '/' . $subFilename . '.yml';
                YamlTools::mergeContentIntoFile($secretArray, $filename);

                $newDeploymentContent = ['spec' => ['template' => ['spec' => ['containers' => [0 => ['envFrom' => [
                    [
                        'configMapRef' => $configMapName,
                    ]
                ]]]]]]];
                YamlTools::mergeContentIntoFile($newDeploymentContent, $deploymentFilename);
            }
        }

        // Ingress
        if (!empty($virtualHosts = $service->getVirtualHosts())) {
            $ingressFilename = $k8sServiceDir->getPath() . '/' . K8sIngress::getKind() . '.yml';
            $tmpService = new Service();
            $tmpService->setServiceName($serviceName);
            foreach ($virtualHosts as $virtualHost) {
                $port = (int)$virtualHost['port'];
                $host = $virtualHost['host'] ?? null;
                if (null === $host) {
                    $host = $k8sHelper->askForHost($serviceName, $port);
                }
                $comment = $virtualHost['comment'] ?? null;
                if ($comment !== null) {
                    $comment = (string)$comment;
                }
                $tmpService->addVirtualHost((string)$host, $port, $comment);
            }
            YamlTools::mergeContentIntoFile(K8sIngress::serializeFromService($tmpService), $ingressFilename);
        }

        $this->output->writeln("Service <info>$serviceName</info> has been successfully added in <info>$k8sDirName</info>!");
        return null;
    }
}
