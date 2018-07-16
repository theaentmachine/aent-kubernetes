<?php

namespace TheAentMachine\AentKubernetes\Command;

use TheAentMachine\EventCommand;

class AddEventCommand extends EventCommand
{
    protected function getEventName(): string
    {
        return 'ADD';
    }

    protected function executeEvent(?string $payload): ?string
    {

        return null;
    }
}
