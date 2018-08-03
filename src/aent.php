#!/usr/bin/env php
<?php

require __DIR__ . '/../vendor/autoload.php';

use TheAentMachine\AentApplication;
use TheAentMachine\AentKubernetes\Command\AddEventCommand;
use TheAentMachine\AentKubernetes\Command\NewServiceEventCommand;
use TheAentMachine\Command\EnvironmentEventCommand;

$application = new AentApplication();

$application->add(new AddEventCommand());
$application->add(new NewServiceEventCommand());
$application->add(new EnvironmentEventCommand());

$application->run();
