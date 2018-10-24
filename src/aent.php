#!/usr/bin/env php
<?php

require __DIR__ . '/../vendor/autoload.php';

use \TheAentMachine\Aent\OrchestratorAent;
use \TheAentMachine\AentKubernetes\Event\AddEvent;
use \TheAentMachine\AentKubernetes\Event\NewServiceEvent;

$application = new OrchestratorAent('Kubernetes', new AddEvent(), new NewServiceEvent());
$application->run();
