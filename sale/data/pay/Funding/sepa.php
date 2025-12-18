<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use Sabre\Xml\Service;
use sale\pay\Funding;

[$params, $providers] = eQual::announce([
    'description'   => 'Generate a SEPA XML file for multiple Fundings according to ISO 20022 pain.001.001.03.',
    'help'          => 'Expected param is either a single Funding id or a list of Funding ids via the "ids" parameter. A maximum of 50 fundings per SEPA file is enforced.',
    'params'        => [
        'id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'sale\pay\Funding',
            'description'       => 'The Funding for which the SEPA is requested.',
        ],
        'ids' => [
            'type'              => 'one2many',
            'foreign_object'    => 'sale\pay\Funding',
            'description'       => 'List of Funding IDs to include in the SEPA file.',
            'default'           => []
        ]
    ],
    'response'      => [
        'content-type'  => 'text/xml',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => [ 'context', 'orm' ]
]);

/** @var \equal\php\Context $context */
/** @var \equal\orm\ObjectManager $orm */
['context' => $context, 'orm' => $orm] = $providers;


// #memo - `id` and `ids` are mutually exclusive
$ids = $params['ids'];

if(isset($params['id']) && $params['id']) {
    $ids = (array) $params['id'];
}

if(!is_array($ids) || !count($ids)) {
    throw new Exception('no_ids_provided', EQ_ERROR_INVALID_PARAM);
}

if(count($ids) > 50) {
    throw new Exception('too_many_ids', EQ_ERROR_INVALID_PARAM);
}

$fundings = Funding::ids($ids)
    ->read([
        'id',
        'due_amount',
        'has_mandate',
        'payment_reference',
        'bank_account_id' => ['bank_account_iban','bank_account_bic','owner_identity_id' => ['name']],
        'counterpart_bank_account_id' => ['bank_account_iban','bank_account_bic','owner_identity_id' => ['name']]
    ])
    ->get();

if(count($fundings) <= 0) {
    throw new Exception('fundings_not_found', EQ_ERROR_INVALID_PARAM);
}


// validation (single debtor account)
$first = reset($fundings);

$fromIban = $first['bank_account_id']['bank_account_iban'];
$fromBic  = $first['bank_account_id']['bank_account_bic'];
$fromName = $first['bank_account_id']['owner_identity_id']['name'];


// #todo - handle if Payment already exist
// is_exported -> deduce from due_amount
// !is_exported : use amount (condition <  due amount)
foreach($fundings as $funding) {
    if($funding['due_amount'] >= 0) {
        throw new Exception('sepa_only_for_outgoing_funding', EQ_ERROR_INVALID_PARAM);
    }
    if($funding['has_mandate']) {
        throw new Exception('no_sepa_for_funding_with_mandate', EQ_ERROR_INVALID_PARAM);
    }
    if($funding['bank_account_id']['bank_account_iban'] !== $fromIban) {
        throw new Exception('multiple_debtors_not_allowed', EQ_ERROR_INVALID_PARAM);
    }
}


// compute totals
$totalAmount = 0;
foreach($fundings as $funding) {
    $totalAmount += abs((float) $funding['due_amount']);
}

$total_formatted = number_format($totalAmount, 2, '.', '');


$date_execution = date('Y-m-d');
$now = gmdate('Y-m-d\TH:i:s');
$group_id = 'BATCH-' . time();
$msg_id = $group_id . '-MSG';



// build SEPA structure

$ns = 'urn:iso:std:iso:20022:tech:xsd:pain.001.001.03';

$n  = fn($t) => '{'.$ns.'}'.$t;

// GROUP HEADER
$grpHdr = [
    $n('MsgId')   => $msg_id,
    $n('CreDtTm') => $now,
    $n('NbOfTxs') => strval(count($fundings)),
    $n('CtrlSum') => $total_formatted,
    $n('InitgPty') => [
        $n('Nm') => $fromName,
    ]
];


// PAYMENT INFO
$pmtInf = [
    $n('PmtInfId')  => $group_id,
    $n('PmtMtd')    => 'TRF',
    $n('BtchBookg') => 'true',
    $n('NbOfTxs')   => strval(count($fundings)),
    $n('CtrlSum')   => $total_formatted,

    $n('PmtTpInf') => [
        $n('InstrPrty') => 'NORM',
        $n('SvcLvl') => [ $n('Cd') => 'SEPA' ],
    ],

    $n('ReqdExctnDt') => $date_execution,

    // Debtor global
    $n('Dbtr') => [
        $n('Nm') => $fromName
    ],

    $n('DbtrAcct') => [
        $n('Id') => [ $n('IBAN') => $fromIban ]
    ],

    $n('DbtrAgt') => [
        $n('FinInstnId') => [ $n('BIC') => $fromBic ]
    ],

    $n('ChrgBr') => 'SLEV'
];


// add transactions
foreach($fundings as $funding) {

    $amount = abs(round((float) $funding['due_amount'], 2));
    $amountFormatted = number_format($amount, 2, '.', '');

    $toName = $funding['counterpart_bank_account_id']['owner_identity_id']['name'];
    $toIban = $funding['counterpart_bank_account_id']['bank_account_iban'];
    $toBic  = $funding['counterpart_bank_account_id']['bank_account_bic'];
    $reference = $funding['payment_reference'] ?: ('PAY-' . $funding['id']);

    $pmtInf[] = [
        'name'  => $n('CdtTrfTxInf'),
        'value' => [
            $n('PmtId') => [
                $n('EndToEndId') => $reference
            ],

            $n('Amt') => [
                [
                    'name' => $n('InstdAmt'),
                    'value' => $amountFormatted,
                    'attributes' => ['Ccy' => 'EUR']
                ]
            ],

            $n('CdtrAgt') => [
                $n('FinInstnId') => [ $n('BIC') => $toBic ]
            ],

            $n('Cdtr') => [
                $n('Nm') => $toName
            ],

            $n('CdtrAcct') => [
                $n('Id') => [ $n('IBAN') => $toIban ]
            ],

            $n('RmtInf') => [
                $n('Ustrd') => $reference
            ]
        ]
    ];
}


// build final XML

$service = new Service();
$service->namespaceMap = [ $ns => '' ];

$xml = $service->write(
    '{' . $ns . '}Document',
    [
        $n('CstmrCdtTrfInitn') => [
            $n('GrpHdr') => $grpHdr,
            $n('PmtInf') => $pmtInf
        ]
    ]
);

// force XML declaration with encoding
$xml = preg_replace(
    '/^<\?xml version="1\.0"\?>/',
    '<?xml version="1.0" encoding="UTF-8"?>',
    $xml
);

// inject xmlns:xsi on root element
$xml = preg_replace(
    '/<Document([^>]*)>/',
    '<Document$1 xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">',
    $xml,
    1
);

Funding::ids($ids)->update(['is_sent' => true]);

$filename = "SEPA_ENVELOPE_" . date('Ymd_His') . ".xml";

$context->httpResponse()
    ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
    ->body($xml, true)
    ->send();
