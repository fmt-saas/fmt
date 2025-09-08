<?php

use documents\DocumentSubtype;
use documents\DocumentType;
use documents\recording\RecordingRule;
use documents\recording\RecordingRuleLine;

['orm' => $orm] = eQual::inject(['orm']);

$events = $orm->disableEvents();


$documentType = DocumentType::search(['code', '=', 'invoice'])->first();
$documentSubtype = DocumentSubtype::search(['code', '=', 'advance_invoice'])->first();

$recordingRule = RecordingRule::create([
        'is_template'           => true,
        'name'                  => 'Facture d\'acompte EAU',
        'document_type_id'      => $documentType['id'],
        'document_subtype_id'   => $documentSubtype['id'],
        'supplier_type_id'      => 3
    ])
    ->first();

RecordingRuleLine::create([
        'name'               => 'Eau',
        'recording_rule_id'  => $recordingRule['id'],
        'account_code'       => '6150002',
        'apportionment_code' => '0001',
        'owner_share'        => 100,
        'tenant_share'       => 0,
        'share'              => 1.0
    ]);


$orm->enableEvents($events);
