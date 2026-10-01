<?php

use core\Task;

$controller = 'communication_email_fetch-incoming';

$values = [
    'name'            => 'Réception des emails entrants',
    'is_active'       => true,
    'is_recurring'    => true,
    'repeat_axis'     => 'minute',
    'repeat_step'     => 15,
    'is_exclusive'    => false,
    'after_execution' => 'keep',
    'controller'      => $controller
];

$tasks_ids = Task::search(['controller', '=', $controller])->ids();

if(empty($tasks_ids)) {
    Task::create($values, 'fr');
}
else {
    Task::ids($tasks_ids)->update($values, 'fr');
}
