<?php

namespace TheAentMachine\AentKubernetes\Command;

use TheAentMachine\Aenthill\CommonEvents;
use TheAentMachine\Aenthill\CommonMetadata;
use TheAentMachine\Aenthill\Manifest;
use TheAentMachine\AentKubernetes\Kubernetes\KubernetesServiceDirectory;
use TheAentMachine\AentKubernetes\Kubernetes\Object\K8sDeployment;
use TheAentMachine\AentKubernetes\Kubernetes\Object\K8sIngress;
use TheAentMachine\AentKubernetes\Kubernetes\Object\K8sSecret;
use TheAentMachine\AentKubernetes\Kubernetes\Object\K8sService;
use TheAentMachine\Command\AbstractJsonEventCommand;
use TheAentMachine\Question\CommonValidators;
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
        $deploymentArray = K8sDeployment::serializeFromService($service, $serviceName);
        $deploymentFilename = $k8sServiceDir->getPath() . '/' . K8sDeployment::getKind() . '.yml';
        YamlTools::mergeContentIntoFile($deploymentArray, $deploymentFilename);

        // Service
        $serviceArray = K8sService::serializeFromService($service, $serviceName);
        $filename = $k8sServiceDir->getPath() . '/' . K8sService::getKind() . '.yml';
        YamlTools::mergeContentIntoFile($serviceArray, $filename);

        // Secret
        if (count($service->getAllSharedSecret()) > 0) {
            $secretObjName = $serviceName . '-secrets';
            $newContent = ['spec' => ['template' => ['spec' => ['containers' => [0 => ['envFrom' => [
                [
                    'secretFrom' => $secretObjName,
                    'optional' => false
                ]
            ]]]]]]];
            YamlTools::mergeContentIntoFile($newContent, $deploymentFilename);

            $secretArray = K8sSecret::serializeFromService($service, $secretObjName);
            $filename = $k8sServiceDir->getPath() . '/' . K8sSecret::getKind() . '.yml';
            YamlTools::mergeContentIntoFile($secretArray, $filename);
        }

        if ($service->getNeedVirtualHost()) {
            $ingressFilename = $k8sServiceDir->getPath() . '/' . K8sIngress::getKind() . '.yml';
            $tmpService = new Service();
            $tmpService->setServiceName($serviceName);
            if (empty($virtualHosts = $service->getVirtualHosts())) {
                do {
                    $host = $this->askForHost($serviceName, null);
                    $port = $this->askForPort($serviceName, $host);
                    // TODO: ask question about comments
                    $tmpService->addVirtualHost($host, $port, null);
                    $addAnotherVirtualHost = $this->getAentHelper()->question('Do you want to add another virtual host?')
                        ->yesNoQuestion()
                        ->setDefault('n')
                        ->ask();
                } while ($addAnotherVirtualHost);
            } else {
                foreach ($virtualHosts as $virtualHost) {
                    $port = $virtualHost['port'];
                    $host = $virtualHost['host'] ?? null;
                    if (null === $host) {
                        $host = $this->askForHost($serviceName, null);
                    }
                    $tmpService->addVirtualHost((string)$host, (int)$port, null);
                }
            }
            YamlTools::mergeContentIntoFile(K8sIngress::serializeFromService($tmpService), $ingressFilename);
        }

        $this->output->writeln("Service <info>$serviceName</info> has been successfully added in <info>$k8sDirName</info>!");
        return null;
    }


    private function askForHost(string $serviceName, ?int $port = null): string
    {
        $question = "What is the domain name of your service <info>$serviceName</info>";
        $question .= null === $port ? '?' : " (port: <info>$port</info>)?";
        return $this->getAentHelper()->question($question)
            ->compulsory()
            ->setValidator(CommonValidators::getDomainNameValidator())
            ->ask();
    }

    private function askForPort(string $serviceName, string $host): int
    {
        $question = "Which port for the domain name <info>$host</info> of your service <info>$serviceName</info>?";
        return (int)$this->getAentHelper()->question($question)
            ->compulsory()
            ->setDefault('80')
            ->setValidator(function (string $value) {
                $value = trim($value);
                if (!\is_numeric($value)) {
                    throw new \InvalidArgumentException("Invalid integer $value");
                }
                return $value;
            })
            ->ask();
    }
}
