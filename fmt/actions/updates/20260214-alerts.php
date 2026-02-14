<?php

// The quorum of presence or represented shares is not reached

use core\alert\MessageModel;

$model = MessageModel::create([
        'name'          => 'realestate.workflow.assembly.quorum_not_reached',
        'type'          => 'governance',
        'label'         => 'Quorum not reached',
        'description'   => "The quorum of presence or represented shares is not reached."
    ], 'en')
    ->first();

MessageModel::id($model['id'])->update([
        'label'         => 'Quorum non atteint',
        'description'   => "Le quorum de présence ou de parts représentées n'est pas atteint.",
    ], 'fr');

$model = MessageModel::create([
        'name'          => 'realestate.workflow.assembly.quorum_reached',
        'type'          => 'governance',
        'label'         => 'Quorum reached',
        'description'   => "The quorum of presence or represented shares is reached."
    ], 'en')
    ->first();

MessageModel::id($model['id'])->update([
        'label'         => 'Quorum atteint',
        'description'   => "Le quorum de présence ou de parts représentées est atteint.",
    ], 'fr');