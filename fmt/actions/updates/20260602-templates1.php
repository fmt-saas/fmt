<?php

use communication\template\Template;
use communication\template\TemplatePart;

['orm' => $orm] = eQual::inject(['orm']);

$events = $orm->disableEvents();


/** OwnershipTransfer */
TemplatePart::create([
    'name'          => 'condominium_debts_description',
    'value'         => "
        <p>Voici un tableau avec les emprubnts contractés à ce jour et le ou les soldes restant dûs par lot concerné par la cession.</p>
    ",
    'template_id'   => 25,
    'variables'     => '[]'
]);

$orm->enableEvents($events);


