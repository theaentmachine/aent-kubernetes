<?php

namespace TheAentMachine\AentKubernetes\Kubernetes;

use Symfony\Component\Filesystem\Filesystem;
use TheAentMachine\Aenthill\CommonMetadata;
use TheAentMachine\Aenthill\Manifest;
use TheAentMachine\Aenthill\Pheromone;

class KubernetesServiceDirectory
{
    /** @var string */
    private $path;
    /** @var \SplFileInfo */
    private $dirInfo;

    /**
     * KubernetesFolder constructor.
     * @param string $serviceName
     * @throws \TheAentMachine\Exception\ManifestException
     * @throws \TheAentMachine\Exception\MissingEnvironmentVariableException
     */
    public function __construct(string $serviceName)
    {
        $parentPath = $this->findOrCreateParentDirectory();
        $this->path = "$parentPath/$serviceName";
    }

    public function exist(): bool
    {
        return \is_dir($this->path);
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getDirName(bool $withK8sDirName = true): string
    {
        // it basically returns "KUBERNETES_DIRNAME/serviceName" or just "serviceName"
        return $withK8sDirName ? \dirname($this->path) . '/' . \basename($this->path) : \basename($this->path);
    }

    /**
     * @return KubernetesServiceDirectory
     * @throws \TheAentMachine\Exception\MissingEnvironmentVariableException
     */
    public function findOrCreate(): self
    {
        if (!$this->exist()) {
            return $this->create();
        }

        $this->dirInfo = new \SplFileInfo($this->path);
        return $this;
    }

    /**
     * @return KubernetesServiceDirectory
     * @throws \TheAentMachine\Exception\MissingEnvironmentVariableException
     */
    private function create(): self
    {
        if ($this->exist()) {
            return $this;
        }

        $fileSystem = new Filesystem();
        $fileSystem->mkdir($this->path);

        $containerProjectDirInfo = new \SplFileInfo(Pheromone::getContainerProjectDirectory());
        chown($this->path, $containerProjectDirInfo->getOwner());
        chgrp($this->path, $containerProjectDirInfo->getGroup());

        $this->dirInfo = new \SplFileInfo($this->path);

        return $this;
    }

    /**
     * @throws \TheAentMachine\Exception\ManifestException
     * @throws \TheAentMachine\Exception\MissingEnvironmentVariableException
     */
    private function findOrCreateParentDirectory() :string
    {
        $parentPath = Pheromone::getContainerProjectDirectory() . '/' . Manifest::mustGetMetadata(CommonMetadata::KUBERNETES_DIRNAME_KEY);
        if (!\is_dir($parentPath)) {
            $fileSystem = new Filesystem();
            $fileSystem->mkdir($parentPath);
            $projectDirInfo = new \SplFileInfo(Pheromone::getContainerProjectDirectory());
            chown($parentPath, $projectDirInfo->getOwner());
            chgrp($parentPath, $projectDirInfo->getGroup());
        }
        return $parentPath;
    }
}
