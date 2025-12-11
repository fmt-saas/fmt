<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use Sabre\Xml\Service;
use Sabre\Xml\Writer;
use Sabre\Xml\XmlSerializable;
use sale\pay\Funding;


[$params, $providers] = eQual::announce([
    'description'   => 'Generates a SEPA xml doc for a given Funding.',
    'params'        => [
        'id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'sale\pay\Funding',
            'description'       => 'The Funding for which the SEPA is requested.',
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


class SepaDocument implements XmlSerializable {

    private string $ns;
    private array $children;

    public function __construct(string $ns, array $children) {
        $this->ns = $ns;
        $this->children = $children;
    }

    public function xmlSerialize(Writer $writer): void {
        // root attributes
        $writer->writeAttributes([
            'xmlns:xsi' => 'http://www.w3.org/2001/XMLSchema-instance'
        ]);

        // write nested content
        foreach($this->children as $tag => $value) {
            $writer->write([ $tag => $value ]);
        }
    }
}

$funding = Funding::id($params['id'])
    ->read([
        'due_amount',
        'payment_reference',
        'bank_account_id' => ['bank_account_iban','bank_account_bic','owner_identity_id' => ['name']],
        'counterpart_bank_account_id' => ['bank_account_iban','bank_account_bic','owner_identity_id' => ['name']]
    ])
    ->first();

if (!$funding) {
    throw new Exception('funding_not_found', EQ_ERROR_INVALID_PARAM);
}

$amount = abs(round((float)$funding['due_amount'], 2));
if ($amount <= 0) {
    throw new Exception('invalid_amount', EQ_ERROR_INVALID_PARAM);
}

$fromIban = $funding['bank_account_id']['bank_account_iban'];
$fromBic  = $funding['bank_account_id']['bank_account_bic'];
$fromName = $funding['bank_account_id']['owner_identity_id']['name'];

$toIban   = $funding['counterpart_bank_account_id']['bank_account_iban'];
$toBic    = $funding['counterpart_bank_account_id']['bank_account_bic'];
$toName   = $funding['counterpart_bank_account_id']['owner_identity_id']['name'];

$reference = $funding['payment_reference'];


$groupId   = 'FUNDING-' . $funding['id'];
$paymentId = 'FUNDING-PAY-' . $funding['id'];
$msgId     = $groupId . '-MSG';
$executionDate = date('Y-m-d');
$now = gmdate('Y-m-d\TH:i:s');
$amountFormatted = number_format($amount, 2, '.', '');



// build SEPA array tree
$ns = 'urn:iso:std:iso:20022:tech:xsd:pain.001.001.03';
$n  = fn($t) => '{' . $ns . '}' . $t;

$children = [

    // <CstmrCdtTrfInitn>
    $n('CstmrCdtTrfInitn') => [

        // GROUP HEADER
        $n('GrpHdr') => [
            $n('MsgId')   => $msgId,
            $n('CreDtTm') => $now,
            $n('NbOfTxs') => "1",
            $n('CtrlSum') => $amountFormatted,
            $n('InitgPty') => [
                $n('Nm') => $fromName
            ]
        ],

        // PAYMENT INFO
        $n('PmtInf') => [

            $n('PmtInfId')  => $paymentId,
            $n('PmtMtd')    => 'TRF',
            $n('BtchBookg') => 'false',
            $n('NbOfTxs')   => '1',
            $n('CtrlSum')   => $amountFormatted,

            $n('PmtTpInf') => [
                $n('InstrPrty') => 'NORM',
                $n('SvcLvl')    => [ $n('Cd') => 'SEPA' ],
                $n('CtgyPurp')  => [ $n('Cd') => 'SUPP' ]
            ],

            $n('ReqdExctnDt') => $executionDate,

            // Debtor
            $n('Dbtr') => [
                $n('Nm') => $fromName,
                $n('PstlAdr') => [
                    $n('Ctry') => 'FR'
                ]
            ],

            $n('DbtrAcct') => [
                $n('Id') => [
                    $n('IBAN') => $fromIban
                ]
            ],

            $n('DbtrAgt') => [
                $n('FinInstnId') => [
                    $n('BIC') => $fromBic
                ]
            ],

            $n('ChrgBr') => 'SLEV',

            // TRANSACTION
            $n('CdtTrfTxInf') => [

                $n('PmtId') => [
                    $n('EndToEndId') => $reference ?: $paymentId
                ],

                $n('Amt') => [
                    [
                        'name' => $n('InstdAmt'),
                        'value' => $amountFormatted,
                        'attributes' => ['Ccy' => 'EUR']
                    ]
                ],

                $n('CdtrAgt') => [
                    $n('FinInstnId') => [
                        $n('BIC') => $toBic
                    ]
                ],

                $n('Cdtr') => [
                    $n('Nm') => $toName,
                    $n('PstlAdr') => [
                        $n('Ctry') => 'FR'
                    ]
                ],

                $n('CdtrAcct') => [
                    $n('Id') => [
                        $n('IBAN') => $toIban
                    ]
                ],

                $n('RmtInf') => [
                    $n('Ustrd') => $reference
                ]
            ]
        ]
    ]
];


// serialize with custom root element
$service = new Service();
$service->namespaceMap = [ $ns => '' ];

$xml = $service->write(
    '{' . $ns . '}Document',
    new SepaDocument($ns, $children)
);

$filename = sprintf("SEPA_TRANSFER_%s_%04d.xml", date('Ymd'), $funding['id']);

$context->httpResponse()
    ->header('Content-Disposition', 'inline; filename="' . $filename . '"')
    ->body($xml, true)
    ->send();
