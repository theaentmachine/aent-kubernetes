<?php

namespace TheAentMachine\AentKubernetes\Command;

use TheAentMachine\Aenthill\CommonEvents;
use TheAentMachine\Aenthill\CommonMetadata;
use TheAentMachine\Aenthill\Manifest;
use TheAentMachine\AentKubernetes\Kubernetes\K8sUtils;
use TheAentMachine\AentKubernetes\Kubernetes\KubernetesServiceDirectory;
use TheAentMachine\AentKubernetes\Kubernetes\Object\K8sConfigMap;
use TheAentMachine\AentKubernetes\Kubernetes\Object\K8sDeployment;
use TheAentMachine\AentKubernetes\Kubernetes\Object\K8sIngress;
use TheAentMachine\AentKubernetes\Kubernetes\Object\K8sSecret;
use TheAentMachine\AentKubernetes\Kubernetes\Object\K8sService;
use TheAentMachine\Command\AbstractJsonEventCommand;
use TheAentMachine\Question\CommonValidators;
use TheAentMachine\Service\Enum\VolumeTypeEnum;
use TheAentMachine\Service\Environment\SharedEnvVariable;
use TheAentMachine\Service\Service;
use TheAentMachine\Service\Volume\Volume;
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
            $requestMemory = $this->getAentHelper()->question("Memory request for <info>$serviceName</info>")
                ->compulsory()
                ->setHelpText('Amount of guaranteed memory (in bytes). A Container can exceed its memory request if the Node has memory available.')
                ->setValidator(K8sUtils::getMemoryValidator())
                ->ask();
            $service->setRequestMemory($requestMemory);
        }
        if (null === $service->getRequestCpu()) {
            $requestCpu = $this->getAentHelper()->question("CPU request for <info>$serviceName</info>")
                ->compulsory()
                ->setHelpText('Amount of guaranteed cpu units (fractional values are allowed e.g. 0.1 cpu). a Container can exceed its cpu request if the Node has available cpus.')
                ->setValidator(K8sUtils::getCpuValidator())
                ->ask();
            $service->setRequestCpu($requestCpu);
        }
        if (null === $service->getLimitMemory()) {
            $limitMemory = $this->getAentHelper()->question("Memory limit for <info>$serviceName</info>")
                ->compulsory()
                ->setHelpText('Amount of memory (in bytes) that a Container is not allowed to exceed. If a Container allocates more memory than its limit, the Container becomes a candidate for termination.')
                ->setValidator(K8sUtils::getMemoryValidator())
                ->ask();
            $service->setLimitMemory($limitMemory);
        }
        if (null === $service->getLimitCpu()) {
            $limitCpu = $this->getAentHelper()->question("CPU limit for <info>$serviceName</info>")
                ->compulsory()
                ->setHelpText('Max cpu units (fractional values are allowed e.g. 0.1 cpu) that a Container is allowed to use. The limit is guaranteed by throttling.')
                ->setValidator(K8sUtils::getCpuValidator())
                ->ask();
            $service->setLimitCpu($limitCpu);
        }
        $deploymentArray = K8sDeployment::serializeFromService($service, $serviceName);
        $deploymentFilename = $k8sServiceDir->getPath() . '/deployment.yml';
        YamlTools::mergeContentIntoFile($deploymentArray, $deploymentFilename);

        // Service
        $serviceArray = K8sService::serializeFromService($service, $serviceName);
        $filePath = $k8sServiceDir->getPath() . '/service.yml';
        YamlTools::mergeContentIntoFile($serviceArray, $filePath);

        // Secret
        if (!empty($service->getAllSharedSecret())) {
            $sharedSecretsMap = K8sUtils::mapSharedEnvVarsByContainerId($service, true);
            foreach ($sharedSecretsMap as $containerId => $sharedSecrets) {
                $secretObjName = 'default-secrets';
                if ($containerId !== '') {
                    $secretObjName = "secrets-$containerId";
                }
                $tmpService = new Service();
                $tmpService->setServiceName($serviceName);
                /** @var SharedEnvVariable $secret */
                foreach ($sharedSecrets as $key => $secret) {
                    $tmpService->addSharedSecret($key, $secret->getValue(), $secret->getComment(), $secret->getContainerId());
                }
                $secretArray = K8sSecret::serializeFromService($tmpService, $secretObjName);
                $filePath = \dirname($k8sServiceDir->getPath()) . '/' . $secretObjName . '.yml';
                YamlTools::mergeContentIntoFile($secretArray, $filePath);

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
            $sharedSecretsMap = K8sUtils::mapSharedEnvVarsByContainerId($service, true);
            foreach ($sharedSecretsMap as $containerId => $sharedEnvVars) {
                $configMapName = 'default-configMap';
                if ($containerId !== '') {
                    $configMapName = "configMap-$containerId";
                }
                $tmpService = new Service();
                $tmpService->setServiceName($serviceName);
                /** @var SharedEnvVariable $sharedEnvVar */
                foreach ($sharedEnvVars as $key => $sharedEnvVar) {
                    $tmpService->addSharedEnvVariable($key, $sharedEnvVar->getValue(), $sharedEnvVar->getComment(), $sharedEnvVar->getContainerId());
                }
                $secretArray = K8sConfigMap::serializeFromService($service, $configMapName);
                $filePath = \dirname($k8sServiceDir->getPath()) . '/' . $configMapName . '.yml';
                YamlTools::mergeContentIntoFile($secretArray, $filePath);

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
            $ingressFilename = $k8sServiceDir->getPath() . '/ingress.yml';
            $tmpService = new Service();
            $tmpService->setServiceName($serviceName);
            foreach ($virtualHosts as $virtualHost) {
                $port = (int)$virtualHost['port'];
                $host = $virtualHost['host'] ?? null;
                if (null === $host) {
                    $host = $this->getAentHelper()->question("What is the domain name of your service <info>$serviceName</info> (port <info>$port</info>)? ")
                        ->compulsory()
                        ->setValidator(CommonValidators::getDomainNameValidator())
                        ->ask();
                }
                $comment = $virtualHost['comment'] ?? null;
                if ($comment !== null) {
                    $comment = (string)$comment;
                }
                $tmpService->addVirtualHost((string)$host, $port, $comment);
            }
            YamlTools::mergeContentIntoFile(K8sIngress::serializeFromService($tmpService), $ingressFilename);
        }

        // PVC
        $bindVolumes = array_filter($service->getVolumes(), function (Volume $v) {
            return $v->getType() === VolumeTypeEnum::BIND_VOLUME;
        });
        if ($bindVolumes) {
            // TODO
        }


        $this->output->writeln("Service <info>$serviceName</info> has been successfully added in <info>$k8sDirName</info>!");
        return null;
    }
}
