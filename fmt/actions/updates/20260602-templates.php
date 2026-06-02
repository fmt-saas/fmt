<?php

use communication\template\Template;
use communication\template\TemplatePart;

['orm' => $orm] = eQual::inject(['orm']);

$events = $orm->disableEvents();


/** OwnershipTransfer */


TemplatePart::create([
    'name'          => 'fund_balances_description',
    'value'         => "
        <p>Veuillez trouver la situation des différents fonds dans le récapitulatif suivant.</p>
    ",
    'template_id'   => 24,
    'variables'     => '[]'
]);

TemplatePart::create([
    'name'          => 'scheduled_fund_requests_description',
    'value'         => "
        <p>Voir les points fonds de réserve, fonds de roulement et budget du dernier PV de l’AG.</p>
    ",
    'template_id'   => 24,
    'variables'     => '[]'
]);


TemplatePart::create([
    'name'          => 'judiciary_procedures_description',
    'value'         => "
        <p>Voir le point « procédures judiciaires encours » du dernier PV de l’AG.</p>
    ",
    'template_id'   => 24,
    'variables'     => '[]'
]);


TemplatePart::create([
    'name'          => 'general_assembly_minutes_description',
    'value'         => "
        <p>Voir annexes ci-jointes..</p>
    ",
    'template_id'   => 24,
    'variables'     => '[]'
]);


TemplatePart::create([
    'name'          => 'latest_balance_sheet_description',
    'value'         => "
        <p>Voir annexes ci-jointes..</p>
    ",
    'template_id'   => 24,
    'variables'     => '[]'
]);


$template = Template::create([
        'code'          => 'ownership_transfer_paragraph_2',
        'description'   => "Dossier de mutation - paragraphe 2",
        'category_id'   => 5,
        'type_id'       => 5
    ])
    ->read(['id'])
    ->first();

TemplatePart::create([
    'name'          => 'seller_arrears_some_description',
    'value'         => "
        <p>OUI, il existe des arriérés qui s’élèvent à ce jour au montant de <strong>{amount} €</strong>.</p><p><br /></p><p><em>Ce montant ne comprend pas les majorations, pénalités financières, intérêts, frais et dépens résultant des statuts de la copropriété, de décisions d’assemblées générales ou de décisions judiciaires. Le décompte définitif ne pourra être arrêté qu’au jour de la réception des sommes dues.</em></p><p>&nbsp;</p><p><em>Nous rappelons également que le copropriétaire cédant restera redevable de sa quote-part dans le(s) décompte(s) de charges à établir jusqu’à la date de signature de l’acte authentique, ainsi que des honoraires du syndic liés à la gestion du dossier de transfert de propriété.</em></p>
    ",
    'template_id'   => $template['id'],
    'variables'     => '["amount"]'
]);

TemplatePart::create([
    'name'          => 'seller_arrears_none_description',
    'value'         => "
        <p>NON, il n’existe aucun arriéré à ce jour.</p><p><br /></p><p><em>Nous rappelons toutefois que le copropriétaire cédant restera redevable de sa quote-part dans le(s) décompte(s) de charges à établir jusqu’à la date de signature de l’acte authentique, ainsi que des honoraires du syndic liés à la gestion du dossier de transfert de propriété.</em></p>
    ",
    'template_id'   => $template['id'],
    'variables'     => '[]'
]);


TemplatePart::create([
    'name'          => 'maintenance_expenses_description',
    'value'         => "
        <p>Voir annexes ci-jointes, dernier PV de l’AG.</p>
    ",
    'template_id'   => $template['id'],
    'variables'     => '[]'
]);

TemplatePart::create([
    'name'          => 'fund_requests_description',
    'value'         => "
        <p>Voici un tableau récapitulatif des appels relatifs à l'exercice en cours (montants appelés et planifiés).</p>
    ",
    'template_id'   => $template['id'],
    'variables'     => '[]'
]);

TemplatePart::create([
    'name'          => 'commons_acquisitions_description',
    'value'         => "
        <p>Veuillez-vous référer aux derniers procès-verbaux d’AG.</p>
    ",
    'template_id'   => $template['id'],
    'variables'     => '[]'
]);

TemplatePart::create([
    'name'          => 'condominium_debts_description',
    'value'         => "
        <p>Veuillez-vous référer aux derniers procès-verbaux d’assemblée générale.</p>
    ",
    'template_id'   => $template['id'],
    'variables'     => '[]'
]);


$orm->enableEvents($events);


