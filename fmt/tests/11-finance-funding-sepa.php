<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use finance\accounting\Account;
use finance\bank\CondominiumBankAccount;
use realestate\sale\pay\Funding;

/*
TEST SET POSTULATE (SEPA / XML / FUNDING)

This test set assumes that a demo or test environment is already initialized
with a coherent accounting and real-estate context, including:

- at least one validated Condominium (expected id = 1),
- an active chart of accounts imported and activated for this condominium,
- at least two active CondominiumBankAccounts attached to this condominium:
    - one current bank account (bank_account_type = 'bank_current'),
    - one savings bank account (bank_account_type = 'bank_savings'),
- an accounting account with operation_assignment = 'bank_transfer'.

The purpose of this test set is NOT to validate the full accounting workflows
(journals, postings, reconciliations), but to verify the SEPA pipeline end-to-end:

1) XML structural validation against ISO 20022 pain.001.001.03 XSD,
2) SEPA XML generation from a minimal, coherent Funding object,
3) XSD validation of the generated SEPA XML.

These tests are intentionally pragmatic and environment-dependent, and are
meant to catch regressions in SEPA generation and schema compliance.
*/

$providers = eQual::inject(['context', 'orm', 'auth', 'access']);

$tests = [

        '1101' => [
            'description'       => "Test a valid SEPA XML.",
            'return'            => ['boolean'],
            'arrange'           => function() use($providers) {
                    $xml = <<<XML
                    <?xml version="1.0" encoding="UTF-8"?>
                    <Document xmlns="urn:iso:std:iso:20022:tech:xsd:pain.001.001.03"
                            xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
                    <CstmrCdtTrfInitn>
                    <GrpHdr>
                    <MsgId>BATCH-1766054754-MSG</MsgId>
                    <CreDtTm>2025-12-18T10:45:54</CreDtTm>
                    <NbOfTxs>1</NbOfTxs>
                    <CtrlSum>113.38</CtrlSum>
                    <InitgPty>
                        <Nm>ACP COURTE-PAILLE</Nm>
                    </InitgPty>
                    </GrpHdr>
                    <PmtInf>
                    <PmtInfId>BATCH-1766054754</PmtInfId>
                    <PmtMtd>TRF</PmtMtd>
                    <BtchBookg>true</BtchBookg>
                    <NbOfTxs>1</NbOfTxs>
                    <CtrlSum>113.38</CtrlSum>
                    <PmtTpInf>
                        <InstrPrty>NORM</InstrPrty>
                        <SvcLvl>
                        <Cd>SEPA</Cd>
                        </SvcLvl>
                    </PmtTpInf>
                    <ReqdExctnDt>2025-12-18</ReqdExctnDt>
                    <Dbtr>
                        <Nm>ACP COURTE-PAILLE</Nm>
                    </Dbtr>
                    <DbtrAcct>
                        <Id>
                        <IBAN>BE03068955349084</IBAN>
                        </Id>
                    </DbtrAcct>
                    <DbtrAgt>
                        <FinInstnId>
                        <BIC>GKCCBEBB</BIC>
                        </FinInstnId>
                    </DbtrAgt>
                    <ChrgBr>SLEV</ChrgBr>
                    <CdtTrfTxInf>
                        <PmtId>
                        <EndToEndId>522001873513</EndToEndId>
                        </PmtId>
                        <Amt>
                        <InstdAmt Ccy="EUR">113.38</InstdAmt>
                        </Amt>
                        <CdtrAgt>
                        <FinInstnId>
                        <BIC>GEBABEBB</BIC>
                        </FinInstnId>
                        </CdtrAgt>
                        <Cdtr>
                        <Nm>VINCOTTE</Nm>
                        </Cdtr>
                        <CdtrAcct>
                        <Id>
                        <IBAN>BE25210041441482</IBAN>
                        </Id>
                        </CdtrAcct>
                        <RmtInf>
                        <Ustrd>522001873513</Ustrd>
                        </RmtInf>
                    </CdtTrfTxInf>
                    </PmtInf>
                    </CstmrCdtTrfInitn>
                    </Document>
                    XML;

                    return $xml;
                },
            'act'               => function($xml) use($providers) {
                    return $xml;
                },
            'assert'            => function($xml) use($providers) {
                    $result = eQual::run('get', 'xml-validate', [
                            'xml'       => $xml,
                            'schema_id' => 'pain.001.001.03',
                            'package'   => 'finance'
                        ]);

                    return $result['result'];
                },
            'rollback'          => function() use($providers) {
                }
        ],


        '1102' => [
            'description'       => "Test a non-valid SEPA XML.",
            'return'            => ['boolean'],
            'arrange'           => function() use($providers) {
                    $xml = <<<XML
                    <?xml version="1.0" encoding="UTF-8"?>
                    <Document xmlns="urn:iso:std:iso:20022:tech:xsd:pain.001.001.03"
                            xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
                    <CstmrCdtTrfInitn>
                    </Document>
                    XML;

                    return $xml;
                },
            'act'               => function($xml) use($providers) {
                    return $xml;
                },
            'assert'            => function($xml) use($providers) {
                    $result = eQual::run('get', 'xml-validate', [
                            'xml'       => $xml,
                            'schema_id' => 'pain.001.001.03',
                            'package'   => 'finance'
                        ]);

                    return !((bool) $result['result']);
                },
            'rollback'          => function() use($providers) {
                }
        ],

    '1103' => [
            'description'       => "Test SEPA validity from a Funding.",
            'help'              => "This test assumes that the demo environment is already initialized with:
                - at least one validated Condominium (id = 1),
                - two active CondominiumBankAccounts for this condominium:
                    - one current bank account (bank_current),
                    - one savings bank account (bank_savings),
                - a valid chart of accounts with an accounting account assigned to operation_assignment = 'bank_transfer'.
            ",
            'return'            => ['boolean'],
            'arrange'           => function() use($providers) {
                    $originBankAccount = CondominiumBankAccount::search([ ['condo_id', '=', 1], ['bank_account_type', '=', 'bank_current'] ])->first();
                    $targetBankAccount = CondominiumBankAccount::search([ ['condo_id', '=', 1], ['bank_account_type', '=', 'bank_savings'] ])->first();

                    $accountingAccount = Account::search([ ['condo_id', '=', 1], ['operation_assignment', '=', 'bank_transfer'] ])->first();

                    $funding = Funding::create([
                            'description'                   => 'Test Funding',
                            'funding_type'                  => 'misc',
                            'due_amount'                    => -100.00,
                            'is_paid'                       => false,
                            'bank_account_id'               => $originBankAccount['id'],
                            'counterpart_bank_account_id'   => $targetBankAccount['id'],
                            'payment_reference'             => '522001873513',
                            'accounting_account_id'         => $accountingAccount['id'],
                            'due_date'                      => time()
                        ])
                        ->first();

                    return $funding;
                },
            'act'               => function($funding) use($providers) {
                    $xml = eQual::run('get', 'sale_pay_Funding_sepa', ['id' => $funding['id']]);
                    return $xml;
                },
            'assert'            => function($xml) use($providers) {
                    $result = eQual::run('get', 'xml-validate', [
                            'xml'       => $xml,
                            'schema_id' => 'pain.001.001.03',
                            'package'   => 'finance'
                        ]);

                    return $result['result'];
                },
            'rollback'          => function() use($providers) {
                    Funding::search(['description', '=', 'Test Funding'])->delete(true);
                }
        ]

];