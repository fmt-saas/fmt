<?php

use core\alert\MessageModel;

$data = [
    'en' => [
        'name'          => 'realestate.workflow.assembly.valid',
        'type'          => 'governance',
        'label'         => "Quorum reached",
        'description'   => "The quorum of presence or represented shares is reached."
    ],
    'translations' => [
        'fr' => [
            'label'         => "Quorum atteint",
            'description'   => "Le quorum de présence ou de parts représentées est atteint."
        ]
    ]
];

$model = MessageModel::create($data['en'], 'en')->first();
foreach($data['translations'] as $lang => $translated_data) {
    MessageModel::id($model['id'])->update($translated_data, $lang);
}
