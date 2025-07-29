<?php

use core\alert\MessageModel;

$model = MessageModel::create([
        'name'          => 'realestate.governance.assembly.incomplete_sending',
        'type'          => 'governance',
        'label'         => 'Incomplete sending',
        'description'   => "At least one owner hasn't been contacted."
    ], 'en')
    ->first();

MessageModel::id($model['id'])->update([
        'label'         => 'Envoi incomplet',
        'description'   => "Au moins un propriétaire n'a pas encore été contacté.",
    ], 'fr');

