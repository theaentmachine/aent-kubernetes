<?php

namespace TheAentMachine\AentKubernetes\Event;

use Safe\Exceptions\FilesystemException;
use Safe\Exceptions\StringsException;
use Symfony\Component\Filesystem\Filesystem;
use TheAentMachine\Aent\Event\Orchestrator\AbstractOrchestratorNewServiceEvent;
use TheAentMachine\AentKubernetes\Context\KubernetesContext;
use TheAentMachine\AentKubernetes\Kubernetes\K8SHelper;
use TheAentMachine\AentKubernetes\Kubernetes\Object\K8sConfigMap;
use TheAentMachine\AentKubernetes\Kubernetes\Object\K8sDeployment;
use TheAentMachine\AentKubernetes\Kubernetes\Object\K8sIngress;
use TheAentMachine\AentKubernetes\Kubernetes\Object\K8sPersistentVolumeClaim;
use TheAentMachine\AentKubernetes\Kubernetes\Object\K8sSecret;
use TheAentMachine\AentKubernetes\Kubernetes\Object\K8sService;
use TheAentMachine\Exception\MissingEnvironmentVariableException;
use TheAentMachine\Service\Enum\VolumeTypeEnum;
use TheAentMachine\Service\Environment\SharedEnvVariable;
use TheAentMachine\Service\Service;
use function Safe\chown;
use function Safe\chgrp;
use function Safe\sprintf;
use TheAentMachine\Service\Volume\NamedVolume;
use TheAentMachine\Service\Volume\Volume;
use TheAentMachine\YamlTools\YamlTools;

final class NewServiceEvent extends AbstractOrchestratorNewServiceEvent
{
    /** @var KubernetesContext */
    private $context;

    /** @var bool */
    private $withManyEnvironments;

    /** @var string */
    private $deploymentDirectoryPath;

    /** @var mixed[] */
    private $CPUandMemoryTypes = [
        'Large',
        'Medium',
        'Small',
        'Custom',
    ];

    /**
     * @param Service $service
     * @throws FilesystemException
     * @throws StringsException
     * @throws MissingEnvironmentVariableException
     */
    protected function finalizeService(Service $service): void
    {
        $this->context = KubernetesContext::fromMetadata();
        $this->withManyEnvironments = strpos($service->getImage() ?? '', '#ENVIRONMENT#') !== false;
        $this->prompt->printAltBlock(sprintf("Kubernetes: creating deployment files directory for service %s...", $service->getServiceName()));
        $this->deploymentDirectoryPath = $this->createDeploymentDirectory($service);
        $this->output->writeln(sprintf("👌 Alright, I've created the directory <info>%s</info>!", $service->getServiceName()));
        $this->prompt->printAltBlock("Kubernetes: setting up request and limit for memory and CPU %s...");
        $service = $this->processCPUAndMemoryType($service);
        $this->prompt->printAltBlock("Kubernetes: creating service deployment files...");
        $this->createServiceDeploymentFiles($service);
        $namedVolumes = array_filter($service->getVolumes(), function (Volume $v) {
            return $v->getType() === VolumeTypeEnum::NAMED_VOLUME;
        });
        if (!empty($service->getVirtualHosts())) {
            $this->prompt->printAltBlock('Kubernetes: creating ingress deployment files...');
            $this->createIngressDeploymentFile($service);
        }
        if (!empty($namedVolumes)) {
            $this->prompt->printAltBlock("Kubernetes: persistent volume claim deployment files...");
            $this->createPersistentVolumeClaimDeploymentFiles($namedVolumes);
        }
        if (!empty($service->getAllSharedSecret())) {
            $this->prompt->printAltBlock("Kubernetes: creating shared secrets deployment file...");
            $this->createSharedSecretDeploymentFile($service);
        }
        if (!empty($service->getAllSharedEnvVariable())) {
            $this->prompt->printAltBlock("Kubernetes: creating shared environment variables deployment file...");
            $this->createSharedEnvVariableDeploymentFile($service);
        }
    }

    /**
     * @param Service $service
     * @return string
     * @throws FilesystemException
     * @throws StringsException
     */
    private function createDeploymentDirectory(Service $service): string
    {
        $fileSystem = new Filesystem();
        $deploymentDirectoryPath = $this->context->getDirectoryPath() . '/' . $service->getServiceName();
        $fileSystem->mkdir($deploymentDirectoryPath);
        $dirInfo = new \SplFileInfo(\dirname($deploymentDirectoryPath));
        chown($deploymentDirectoryPath, $dirInfo->getOwner());
        chgrp($deploymentDirectoryPath, $dirInfo->getGroup());
        return $deploymentDirectoryPath;
    }

    /**
     * @param Service $service
     * @return Service
     */
    private function processCPUAndMemoryType(Service $service): Service
    {
        $CPUAndMemoryTypeIndex = $this->getCPUAndMemoryType($service);
        if ($CPUAndMemoryTypeIndex < 3) {
            $CPUAndMemoryType = $this->CPUandMemoryTypes[$CPUAndMemoryTypeIndex];
            $this->output->writeln("\n👌 Alright, I'm going to setup the profile <info>$CPUAndMemoryType</info>!");
            return $this->addDefaultCPUAndMemory($CPUAndMemoryTypeIndex, $service);
        }
        $this->output->writeln("\n👌 Alright, let's choose a custom profile!");
        return $this->addCustomCPUAndMemory($service);
    }

    /**
     * @param Service $service
     * @return int
     */
    private function getCPUAndMemoryType(Service $service): int
    {
        $serviceName = $service->getServiceName();
        $helpText = 'We provide a bunch of defaults CPU and memory profiles which fit for most cases. By choosing the custom option, you may define your own profile.';
        $response = $this->prompt->select("\nYour profile type for <info>$serviceName</info>", $this->CPUandMemoryTypes, $helpText, null, true);
        $CPUAndMemoryTypeIndex = \array_search($response, $this->CPUandMemoryTypes);
        return $CPUAndMemoryTypeIndex !== false ? (int)$CPUAndMemoryTypeIndex : 3;
    }

    /**
     * @param int $CPUAndMemoryTypeIndex
     * @param Service $service
     * @return Service
     */
    private function addDefaultCPUAndMemory(int $CPUAndMemoryTypeIndex, Service $service): Service
    {
        switch ($CPUAndMemoryTypeIndex) {
            case 0:
                // Large
                $service->setRequestCpu('4');
                $service->setRequestMemory('4G');
                $service->setLimitCpu('8');
                $service->setLimitMemory('16G');
                break;
            case 1:
                // Medium
                $service->setRequestCpu('1');
                $service->setRequestMemory('1G');
                $service->setLimitCpu('2');
                $service->setLimitMemory('4G');
                break;
            case 2:
                // Small
                $service->setRequestCpu('0.5');
                $service->setRequestMemory('256M');
                $service->setLimitCpu('1');
                $service->setLimitMemory('1G');
                break;
            default:
                throw new \RuntimeException('Unexpected profile');
        }
        return $service;
    }

    /**
     * @param Service $service
     * @return Service
     */
    private function addCustomCPUAndMemory(Service $service): Service
    {
        $service->setRequestCpu($this->getRequestCPU($service));
        $service->setRequestMemory($this->getRequestMemory($service));
        $service->setLimitCpu($this->getLimitCPU($service));
        $service->setLimitMemory($this->getLimitMemory($service));
        return $service;
    }

    /**
     * @param Service $service
     * @return string
     */
    private function getRequestCPU(Service $service): string
    {
        $serviceName = $service->getServiceName();
        $text = "\nCPU request for <info>$serviceName</info>";
        $helpText = "Amount of guaranteed CPU units (fractional values are allowed e.g. 0.1 cpu). A container may exceed its CPU request if the Node has available CPUs.";
        return $this->prompt->input($text, $helpText, null, true, K8SHelper::getCpuValidator()) ?? '';
    }

    /**
     * @param Service $service
     * @return string
     */
    private function getRequestMemory(Service $service): string
    {
        $serviceName = $service->getServiceName();
        $text = "\nMemory request for <info>$serviceName</info>";
        $helpText = "Amount of guaranteed memory (in bytes). A container may exceed its memory request if the Node has memory available.";
        return $this->prompt->input($text, $helpText, null, true, K8SHelper::getMemoryValidator()) ?? '';
    }

    /**
     * @param Service $service
     * @return string
     */
    private function getLimitCPU(Service $service): string
    {
        $serviceName = $service->getServiceName();
        $text = "\nCPU limit for <info>$serviceName</info>";
        $helpText = "Max CPU units (fractional values are allowed e.g. 0.1 cpu) that a container is allowed to use. The limit is guaranteed by throttling.";
        return $this->prompt->input($text, $helpText, null, true, K8SHelper::getCpuValidator()) ?? '';
    }

    /**
     * @param Service $service
     * @return string
     */
    private function getLimitMemory(Service $service): string
    {
        $serviceName = $service->getServiceName();
        $text = "\nMemory limit for <info>$serviceName</info>";
        $helpText = "Amount of memory (in bytes) that a container is not allowed to exceed. If a container allocates more memory than its limit, the container becomes a candidate for termination.";
        return $this->prompt->input($text, $helpText, null, true, K8SHelper::getMemoryValidator()) ?? '';
    }

    /**
     * @param Service $service
     * @return void
     * @throws FilesystemException
     */
    private function createServiceDeploymentFiles(Service $service): void
    {
        $deploymentArray = K8sDeployment::serializeFromService($service, $service->getServiceName());
        $fileExtension = $this->withManyEnvironments ? '.yml.template' : '.yml';
        $deploymentFilename = $this->deploymentDirectoryPath . '/deployment' . $fileExtension;
        YamlTools::mergeContentIntoFile($deploymentArray, $deploymentFilename);
        $serviceArray = K8sService::serializeFromService($service, $this->context->getProvider()->isUseNodePortForIngress());
        $filePath = $this->deploymentDirectoryPath . '/service.yml';
        YamlTools::mergeContentIntoFile($serviceArray, $filePath);
    }

    /**
     * @param Service $service
     * @return void
     * @throws FilesystemException
     * @throws StringsException
     */
    private function createIngressDeploymentFile(Service $service): void
    {
        $serviceName = $service->getServiceName();
        $virtualHosts = $service->getVirtualHosts();
        $baseVirtualHost = $this->context->getBaseVirtualHost();
        $fileExtension = $this->withManyEnvironments ? '.yml.template' : '.yml';
        $filePath = \dirname($this->deploymentDirectoryPath) . '/ingress' . $fileExtension;
        $hosts = [];
        foreach ($virtualHosts as $index => $port) {
            $subdomain = $this->prompt->getPromptHelper()->getSubdomain($serviceName, $port, $baseVirtualHost);
            $url = $this->withManyEnvironments ? $subdomain . '.#ENVIRONMENT#.' . $baseVirtualHost : $subdomain . '.' . $baseVirtualHost;
            $this->output->writeln("\n👌 Your service <info>$serviceName</info> will be accessible at <info>$url</info> (using port <info>$port</info>)!");
            $hosts[] = [ 'url' => $url, 'port' => $port ];
        }
        YamlTools::mergeContentIntoFile(K8sIngress::serializeFromService($service, $hosts, $this->context->getProvider()->getIngressClass(), $this->context->getProvider()->isCertManager()), $filePath);
    }

    /**
     * @param NamedVolume[] $namedVolumes
     * @throws FilesystemException
     */
    private function createPersistentVolumeClaimDeploymentFiles(array $namedVolumes): void
    {
        /** @var NamedVolume $v */
        foreach ($namedVolumes as $v) {
            $text = "Storage request for <info>{$v->getSource()}</info>";
            $helpText = 'Amount of guaranteed storage in bytes (e.g. 8G, 0.5Ti).';
            $requestStorage = $this->prompt->input($text, $helpText, null, true, K8SHelper::getStorageValidator()) ?? '';
            $v = new NamedVolume($v->getSource(), $v->getTarget(), $v->isReadOnly(), $v->getComment(), $requestStorage);
            $pvcArray = K8sPersistentVolumeClaim::serializeFromNamedVolume($v);
            $filePath = $this->deploymentDirectoryPath . '/' . K8SHelper::getPvcName($v->getSource()) . '.yml';
            YamlTools::mergeContentIntoFile($pvcArray, $filePath);
        }
    }

    /**
     * @param Service $service
     * @return void
     * @throws FilesystemException
     */
    private function createSharedSecretDeploymentFile(Service $service): void
    {
        $allSharedSecrets = $service->getAllSharedSecret();
        $sharedSecretsMap = K8SHelper::mapSharedEnvVarsByContainerId($allSharedSecrets);
        foreach ($sharedSecretsMap as $containerId => $sharedSecrets) {
            $secretObjName = K8SHelper::getSecretName($containerId);
            $tmpService = new Service();
            $tmpService->setServiceName($service->getServiceName());
            /** @var SharedEnvVariable $secret */
            foreach ($sharedSecrets as $key => $secret) {
                $tmpService->addSharedSecret($key, $secret->getValue(), $secret->getComment(), $secret->getContainerId());
            }
            $secretArray = K8sSecret::serializeFromService($tmpService, $secretObjName);
            $filePath = \dirname($this->deploymentDirectoryPath) . '/' . $secretObjName . '.yml';
            YamlTools::mergeContentIntoFile($secretArray, $filePath);
        }
    }

    /**
     * @param Service $service
     * @return void
     * @throws FilesystemException
     */
    private function createSharedEnvVariableDeploymentFile(Service $service): void
    {
        $allSharedEnvVars = $service->getAllSharedEnvVariable();
        $sharedSecretsMap = K8SHelper::mapSharedEnvVarsByContainerId($allSharedEnvVars);
        foreach ($sharedSecretsMap as $containerId => $sharedEnvVars) {
            $configMapName = K8SHelper::getConfigMapName($containerId);
            $tmpService = new Service();
            $tmpService->setServiceName($service->getServiceName());
            /** @var SharedEnvVariable $sharedEnvVar */
            foreach ($sharedEnvVars as $key => $sharedEnvVar) {
                $tmpService->addSharedEnvVariable($key, $sharedEnvVar->getValue(), $sharedEnvVar->getComment(), $sharedEnvVar->getContainerId());
            }
            $secretArray = K8sConfigMap::serializeFromService($service, $configMapName);
            $filePath = \dirname($this->deploymentDirectoryPath) . '/' . $configMapName . '.yml';
            YamlTools::mergeContentIntoFile($secretArray, $filePath);
        }
    }
}
