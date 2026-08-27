<?php

use core\alert\MessageModel;

$message_model = MessageModel::search([
        ['name', '=', 'finance.accounting.fund_request.transfer_in_progress']
    ])
    ->read(['name'])
    ->first();

if(is_null($message_model)) {
    $model = MessageModel::create([
            'name'          => 'finance.accounting.fund_request.transfer_in_progress',
            'type'          => 'accounting',
            'label'         => 'Transfer in progress',
            'description'   => "Warning, an ongoing ownership transfer may affect this fund request."
        ], 'en')
        ->first();

    MessageModel::id($model['id'])->update([
            'label'         => 'Mutation en cours',
            'description'   => "Attention, une mutation en cours peut impacter cet appel.",
        ], 'fr');
}
