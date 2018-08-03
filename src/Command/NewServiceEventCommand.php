<?php

namespace TheAentMachine\AentKubernetes\Command;

use Symfony\Component\Yaml\Yaml;
use TheAentMachine\Aenthill\CommonEvents;
use TheAentMachine\Aenthill\CommonMetadata;
use TheAentMachine\Aenthill\Manifest;
use TheAentMachine\Aenthill\Pheromone;
use TheAentMachine\AentKubernetes\Kubernetes\KubernetesServiceDirectory;
use TheAentMachine\AentKubernetes\Kubernetes\Object\K8sDeployment;
use TheAentMachine\AentKubernetes\Kubernetes\Object\K8sService;
use TheAentMachine\Command\AbstractJsonEventCommand;
use TheAentMachine\Service\Service;

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


        $projectDir = Pheromone::getContainerProjectDirectory();
        $projectDirInfo = new \SplFileInfo($projectDir);

        // Deployment
        $deploymentArray = K8sDeployment::serializeFromService($service, $serviceName);
        $filename = $k8sServiceDir->getPath() . '/' . K8sDeployment::getKind() . '.yml';
        file_put_contents($filename, Yaml::dump($deploymentArray, 256, 2, Yaml::DUMP_OBJECT_AS_MAP));
        chown($filename, $projectDirInfo->getOwner());
        chgrp($filename, $projectDirInfo->getGroup());


        // Service
        $serviceArray = K8sService::serializeFromService($service, $serviceName);
        $filename = $k8sServiceDir->getPath() . '/' . K8sService::getKind() . '.yml';
        file_put_contents($filename, Yaml::dump($serviceArray, 256, 2, Yaml::DUMP_OBJECT_AS_MAP));
        chown($filename, $projectDirInfo->getOwner());
        chgrp($filename, $projectDirInfo->getGroup());

        /*
         // TODO
        // Secret
        if (count($service->getAllSharedSecret()) > 0) {
            $secretObjName = $serviceName . '-secrets';
            $sections[0]['spec']['template']['spec']['containers'][0]['envFrom'] = [
                [
                    'secretFrom' => $secretObjName,
                    'optional' => false
                ]
            ];
            $sections[] = K8sSecret::serializeFromService($service, $secretObjName);
        }*/

        $this->output->writeln("Service <info>$serviceName</info> has been successfully added in <info>$k8sDirName</info>!");

        return null;
    }
}
