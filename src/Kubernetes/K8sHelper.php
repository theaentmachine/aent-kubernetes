<?php

namespace TheAentMachine\AentKubernetes\Kubernetes;

use TheAentMachine\Helper\AentHelper;
use TheAentMachine\Question\CommonValidators;
use TheAentMachine\Service\Environment\SharedEnvVariable;
use TheAentMachine\Service\Service;

class K8sHelper
{
    public const NULL_CONTAINER_ID_KEY = 'null___container___id___key___foo';

    /** @var AentHelper */
    private $aentHelper;

    public function __construct(AentHelper $aentHelper)
    {
        $this->aentHelper = $aentHelper;
    }

    /**
     * @param Service $service
     * @param bool $mapSharedSecrets
     * @return array<string, array<string, EnvVariable>>
     */
    public function mapSharedEnvVarsByContainerId(Service $service, bool $mapSharedSecrets = false): array
    {
        $res = [];
        $sharedEnvVars = $mapSharedSecrets ? $service->getAllSharedEnvVariable() : $service->getAllSharedSecret();
        /**
         * @var string $key
         * @var SharedEnvVariable $envVar
         */
        foreach ($sharedEnvVars as $key => $envVar) {
            if (null === $containerId = $envVar->getContainerId()) {
                $containerId = self::NULL_CONTAINER_ID_KEY;
            }
            $res[$containerId][$key] = $envVar;
        }
        return $res;
    }

    public function askForHost(string $serviceName, int $port): string
    {
        $question = "What is the domain name of your service <info>$serviceName</info> (port <info>$port</info>)? ";
        return $this->aentHelper->question($question)
            ->compulsory()
            ->setValidator(CommonValidators::getDomainNameValidator())
            ->ask();
    }

    public function askForMemory(string $serviceName, bool $isRequest): string
    {
        if ($isRequest) {
            $question = "Memory request for <info>$serviceName</info>";
            $helpText = 'Amount of guaranteed memory (in bytes). A Container can exceed its memory request if the Node has memory available.';
        } else {
            $question = "Memory limit for <info>$serviceName</info>";
            $helpText = 'Amount of memory (in bytes) that a Container is not allowed to exceed. If a Container allocates more memory than its limit, the Container becomes a candidate for termination.';
        }
        return $this->aentHelper->question($question)
            ->compulsory()
            ->setHelpText($helpText)
            ->setValidator($this->getMemoryValidator())
            ->ask();
    }

    public function askForCpu(string $serviceName, bool $isRequest): string
    {
        if ($isRequest) {
            $question = "CPU request for <info>$serviceName</info>";
            $helpText = 'Amount of guaranteed cpu units (fractional values are allowed e.g. 0.1 cpu). a Container can exceed its cpu request if the Node has available cpus.';
        } else {
            $question = "CPU limit for <info>$serviceName</info>";
            $helpText = 'Max cpu units (fractional values are allowed e.g. 0.1 cpu) that a Container is allowed to use. The limit is guaranteed by throttling.';
        }
        return $this->aentHelper->question($question)
            ->compulsory()
            ->setHelpText($helpText)
            ->setValidator($this->getCpuValidator())
            ->ask();
    }

    public function askForRequestStorage(string $serviceName): string
    {
        $question = "Storage request for <info>$serviceName</info>";
        $helpText = 'Amount of guaranteed storage in bytes (e.g. 8G, 1Ti).';

        return $this->aentHelper->question($question)
            ->compulsory()
            ->setHelpText($helpText)
            ->setValidator($this->getStorageValidator())
            ->ask();
    }

    private function getCpuValidator(): \Closure
    {
        return function (string $value) {
            $value = trim($value);
            if (!\preg_match('/^(\d+[.])?\d+[m]?$/', $value)) {
                throw new \InvalidArgumentException("Invalid value \"$value\"." . PHP_EOL
                    . 'Hint: The CPU resource is measured in cpu units. Fractional values are allowed.' . PHP_EOL
                    . 'You can use the suffix m to mean mili. For example 100m cpu is 100 milicpu, and is the same as 0.1 cpu.' . PHP_EOL
                    . 'One cpu, in Kubernetes, is equivalent to 1 AWS vCPU, 1 GCP Core, 1 Azure vCore or 1 Hyperthread on a bare-metal Intel processor with Hyperthreading.');
            }
            return $value;
        };
    }

    private function getMemoryValidator(): \Closure
    {
        return function (string $value) {
            $value = trim($value);
            if (!\preg_match('/^(\d+[.])?\d+([EPTGMK][i]?)?$/', $value)) {
                throw new \InvalidArgumentException("Invalid value \"$value\"." . PHP_EOL
                    . 'Hint: The memory resource is measured in bytes.' . PHP_EOL
                    . 'You can express memory as a plain integer or a fixed-point integer with one of these suffixes:' . PHP_EOL
                    . 'E, P, T, G, M, K, Ei, Pi, Ti, Gi, Mi, Ki (e.g. 128974848, 129e6, 129M , 123Mi)');
            }
            return $value;
        };
    }

    private function getStorageValidator(): \Closure
    {
        return function (string $value) {
            $value = trim($value);
            if (!\preg_match('/^(\d+[.])?\d+([EPTGMK][i]?)?$/', $value)) {
                throw new \InvalidArgumentException("Invalid value \"$value\"." . PHP_EOL
                    . 'Hint: The storage resource is measured in bytes.' . PHP_EOL
                    . 'You can express storage as a plain integer or a fixed-point integer with one of these suffixes:' . PHP_EOL
                    . 'E, P, T, G, M, K, Ei, Pi, Ti, Gi, Mi, Ki (e.g. 128974848, 129e6, 8G, 1Ti)');
            }
            return $value;
        };
    }
}
