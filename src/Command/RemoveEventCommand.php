<?php

namespace TheAentMachine\AentKubernetes\Command;

use TheAentMachine\EventCommand;

class RemoveEventCommand extends EventCommand
{
    protected function getEventName(): string
    {
        return 'REMOVE';
    }

    protected function executeEvent(?string $payload): ?string
    {

        return null;
    }
}
