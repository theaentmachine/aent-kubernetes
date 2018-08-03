<?php

namespace TheAentMachine\AentKubernetes\Command;

use Symfony\Component\Filesystem\Filesystem;
use TheAentMachine\Aenthill\Aenthill;
use TheAentMachine\Aenthill\CommonEvents;
use TheAentMachine\Aenthill\CommonMetadata;
use TheAentMachine\Aenthill\Manifest;
use TheAentMachine\Aenthill\Pheromone;
use TheAentMachine\Command\AbstractEventCommand;

class AddEventCommand extends AbstractEventCommand
{
    protected function getEventName(): string
    {
        return CommonEvents::ADD_EVENT;
    }

    /**
     * @param null|string $payload
     * @return null|string
     * @throws \TheAentMachine\Exception\CommonAentsException
     * @throws \TheAentMachine\Exception\ManifestException
     * @throws \TheAentMachine\Exception\MissingEnvironmentVariableException
     */
    protected function executeEvent(?string $payload): ?string
    {
        $aentHelper = $this->getAentHelper();
        $aentHelper->title('Installing a Kubernetes orchestrator');
        $envType = $aentHelper->getCommonQuestions()->askForEnvType();
        $envName = $aentHelper->getCommonQuestions()->askForEnvName($envType);

        $projectDir = Pheromone::getContainerProjectDirectory();
        $projectDirInfo = new \SplFileInfo($projectDir);
        $i = 0;
        $dirName = "kubernetes-$envName";
        while (\is_dir("$projectDir/$dirName")) {
            $i++;
            $dirName = "kubernetes-$envName$i";
        }
        $k8sDirPath = "$projectDir/$dirName";

        $fileSystem = new Filesystem();
        $fileSystem->mkdir($k8sDirPath);
        chown($k8sDirPath, $projectDirInfo->getOwner());
        chgrp($k8sDirPath, $projectDirInfo->getGroup());
        Manifest::addMetadata(CommonMetadata::KUBERNETES_DIRNAME_KEY, $dirName);

        $this->output->writeln("☸️ Kubernetes folder <info>$dirName</info> has been successfully created!");
        $aentHelper->spacer();

        $CIAentID = $aentHelper->getCommonQuestions()->askForCI();
        if (null !== $CIAentID) {
            Aenthill::run($CIAentID, CommonEvents::ADD_EVENT);
            Aenthill::run($CIAentID, CommonEvents::NEW_DEPLOY_KUBERNETES_JOB_EVENT, $dirName);
            $aentHelper->spacer();
        }

        $aentHelper->getCommonQuestions()->askForImageBuilder();
        return null;
    }
}
