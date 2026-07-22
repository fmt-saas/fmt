<?php

use core\alert\MessageModel;

$model = MessageModel::create([
        'name'          => 'fmt.monitoring.failed_email_sending',
        'type'          => 'monitoring',
        'label'         => 'Send email failed',
        'description'   => "The sending of an email failed."
    ])
    ->first();

MessageModel::id($model['id'])->update([
        'label'         => 'Envoi email a échoué',
        'description'   => "L'envoi d'un email a échoué.",
    ], 'fr');
