<?php

namespace TheAentMachine\AentKubernetes\Event;

use Safe\Exceptions\FilesystemException;
use Safe\Exceptions\StringsException;
use Symfony\Component\Filesystem\Filesystem;
use TheAentMachine\Aent\Context\BaseOrchestratorContext;
use TheAentMachine\Aent\Context\ContextInterface;
use TheAentMachine\Aent\Event\Orchestrator\AbstractOrchestratorAddEvent;
use TheAentMachine\Aent\K8SProvider\Provider;
use TheAentMachine\Aent\Payload\CI\KubernetesDeployJobPayload;
use TheAentMachine\Aent\Payload\CI\KubernetesReplyDeployJobPayload;
use TheAentMachine\Aenthill\Aenthill;
use TheAentMachine\AentKubernetes\Context\KubernetesContext;
use function Safe\chown;
use function Safe\chgrp;
use function Safe\sprintf;
use TheAentMachine\Exception\MissingEnvironmentVariableException;
use TheAentMachine\Prompt\Helper\ValidatorHelper;

final class AddEvent extends AbstractOrchestratorAddEvent
{
    /** @var KubernetesContext */
    private $context;

    /**
     * @return ContextInterface
     * @throws FilesystemException
     * @throws StringsException
     * @throws MissingEnvironmentVariableException
     */
    protected function setup(): ContextInterface
    {
        $this->context = new KubernetesContext(BaseOrchestratorContext::fromMetadata());
        $this->prompt->printAltBlock("Kubernetes: creating deployment files directory...");
        $this->createKubernetesDirectory();
        $this->output->writeln(sprintf("👌 Alright, I've created the directory <info>%s</info>!", $this->context->getDirectoryName()));
        $this->prompt->printAltBlock("Kubernetes: setting up provider...");
        $this->context->setProvider($this->getProvider());
        $this->output->writeln(sprintf("\n👌 Alright, I'm going to use <info>%s</info> as a provider for your application!", $this->context->getProvider()->getName()));
        return $this->context;
    }

    /**
     * @param ContextInterface $context
     * @return ContextInterface
     */
    protected function addDeployJobInCI(ContextInterface $context): ContextInterface
    {
        $this->prompt->printAltBlock("Kubernetes: adding deploy job in CI/CD...");
        $payload = new KubernetesDeployJobPayload($this->context->getDirectoryName(), $this->context->getProvider());
        $response = Aenthill::runJson(KubernetesContext::CI_DEPENDENCY_KEY, 'KUBERNETES_DEPLOY_JOB', $payload->toArray());
        $assoc = \GuzzleHttp\json_decode($response[0], true);
        $replyPayload = KubernetesReplyDeployJobPayload::fromArray($assoc);
        $this->context->setSingleEnvironment(!$replyPayload->isWithManyEnvironments());
        return $this->context;
    }

    /**
     * @return void
     * @throws FilesystemException
     * @throws StringsException
     */
    private function createKubernetesDirectory(): void
    {
        $fileSystem = new Filesystem();
        $directoryPath = $this->context->getDirectoryPath();
        $fileSystem->mkdir($directoryPath);
        $dirInfo = new \SplFileInfo(\dirname($directoryPath));
        chown($directoryPath, $dirInfo->getOwner());
        chgrp($directoryPath, $dirInfo->getGroup());
    }

    /**
     * @return Provider
     */
    private function getProvider(): Provider
    {
        $text = "\nSelect your provider";
        $helpText = "The platform on which you'll deploy your application";
        $providerName = $this->prompt->select($text, [ Provider::RANCHER, Provider::GOOGLE_CLOUD ], $helpText, null, true) ?? '';
        $provider = $providerName === Provider::GOOGLE_CLOUD ? Provider::newGoogleCloudProvider() : Provider::newRancherProvider();
        $provider->setCertManager($this->getCertManager());
        if ($provider->isCertManager()) {
            $provider->setUseNodePortForIngress(false);
            $provider->setIngressClass('nginx');
            return $provider;
        }
        $provider->setIngressClass($this->getIngressClass());
        if ($providerName === Provider::GOOGLE_CLOUD && (empty($provider->getIngressClass()) || $provider->getIngressClass() === 'gce')) {
            $provider->setUseNodePortForIngress(true);
        } else {
            $provider->setUseNodePortForIngress(false);
        }
        return $provider;
    }

    /**
     * @return bool
     */
    private function getCertManager(): bool
    {
        $text = "\nDoes your cluster support <info>CertManager</info>?";
        $helpText = "<info>CertManager</info> is used to get HTTPS certificates via <info>Let's Encrypt</info>";
        return $this->prompt->confirm($text, $helpText, null, true);
    }

    /**
     * @return string
     */
    private function getIngressClass(): string
    {
        $text = "\nDefault <info>Ingress</info> class to use (keep empty to use cluster's default class)";
        $helpText = "You can alter the default Ingress controller used when an Ingress is created. This will add a <info>kubernetes.io/ingress.class</info> annotation in your Ingresses.";
        return $this->prompt->input($text, $helpText, null, false, ValidatorHelper::getAlphaValidator()) ?? '';
    }
}
