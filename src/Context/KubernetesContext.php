<?php

namespace TheAentMachine\AentKubernetes\Context;

use Safe\Exceptions\StringsException;
use TheAentMachine\Aent\Context\BaseOrchestratorContext;
use TheAentMachine\Aent\K8SProvider\Provider;
use TheAentMachine\Aenthill\Pheromone;
use TheAentMachine\Exception\MissingEnvironmentVariableException;
use function Safe\sprintf;

final class KubernetesContext extends BaseOrchestratorContext
{
    /** @var string */
    private $projectDir;

    /** @var string */
    private $directoryName;

    /** @var Provider */
    private $provider;

    /**
     * KubernetesContext constructor.
     * @param BaseOrchestratorContext $context
     * @throws MissingEnvironmentVariableException
     * @throws StringsException
     */
    public function __construct(BaseOrchestratorContext $context)
    {
        parent::__construct($context->getEnvironmentType(), $context->getEnvironmentName(), $context->getBaseVirtualHost());
        $this->projectDir = Pheromone::getContainerProjectDirectory();
        $this->directoryName = sprintf("kubernetes-%s", $context->getEnvironmentName());
    }

    /**
     * @return void
     */
    public function toMetadata(): void
    {
        parent::toMetadata();
        $this->provider->toMetadata();
    }

    /**
     * @return self
     * @throws MissingEnvironmentVariableException
     * @throws StringsException
     */
    public static function fromMetadata()
    {
        $self = new self(parent::fromMetadata());
        $self->provider = Provider::fromMetadata();
        return $self;
    }

    /**
     * @return string
     * @throws StringsException
     */
    public function getDirectoryPath(): string
    {
        return sprintf('%s/%s', $this->projectDir, $this->directoryName);
    }

    /**
     * @return string
     */
    public function getProjectDir(): string
    {
        return $this->projectDir;
    }

    /**
     * @return string
     */
    public function getDirectoryName(): string
    {
        return $this->directoryName;
    }

    /**
     * @return Provider
     */
    public function getProvider(): Provider
    {
        return $this->provider;
    }

    /**
     * @param Provider $provider
     * @return void
     */
    public function setProvider(Provider $provider): void
    {
        $this->provider = $provider;
    }
}
