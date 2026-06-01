<?php

use communication\template\Template;
use communication\template\TemplatePart;

['orm' => $orm] = eQual::inject(['orm']);

$events = $orm->disableEvents();


/** OwnershipTransfer */

$template = Template::create([
        'code'          => 'ownership_transfer_paragraph_1',
        'description'   => "Dossier de mutation - paragrpahe 1",
        'category_id'   => 5,
        'type_id'       => 5
    ])
    ->read(['id'])
    ->first();

TemplatePart::create([
    'name'          => 'seller_arrears_some_description',
    'value'         => "<p>OUI, il y a des arriérés qui s'élèvent à {amount}. Ce montant ne comprend pas les majorations dues aux pénalités financières, intérêts, frais et dépens, que celles-ci résultent des statuts de la copropriété, de décisions d’assemblées générales ou de justice. Le calcul final ne pouvant être entrepris que le jour de la réception des montants dus.</p>
        <p>De plus, ce montant ne comprend pas les honoraires pour les frais de transfert de propriété.</p>
        <p>Ce montant ne tient pas compte du décompte de charge de l’exercice comptable en cours.</p>",
    'template_id'   => $template['id'],
    'variables'     => '["amount"]'
]);

TemplatePart::create([
    'name'          => 'seller_arrears_none_description',
    'value'         => "<p>NON, il n'y a pas d'arriérés à ce jour.</p>",
    'template_id'   => $template['id'],
    'variables'     => '[]'
]);



$orm->enableEvents($events);


