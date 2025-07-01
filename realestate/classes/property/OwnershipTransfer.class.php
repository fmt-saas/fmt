<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace realestate\property;

use equal\text\TextTransformer;
use fmt\setting\Setting;
use identity\Identity;
use finance\accounting\Account;
use finance\accounting\AccountingEntryLine;
use finance\accounting\FiscalPeriod;
use finance\accounting\FiscalYear;
use realestate\finance\accounting\CondoFund;
use realestate\funding\FundRequest;
use realestate\funding\FundRequestExecution;
use realestate\funding\FundRequestExecutionLine;
use realestate\funding\FundRequestExecutionLineEntry;
use realestate\funding\FundRequestLineEntryLot;
use realestate\ownership\Ownership;

class OwnershipTransfer extends \equal\orm\Model {

    public static function getColumns() {
        return [

            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the property lot belongs to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'required'          => true
            ],

            'is_notary_request' => [
                'type'              => 'boolean',
                'description'       => "Is the original information request made by a notary office ?",
                'default'           => false
            ],

            'request_contact_name' => [
                'type'              => 'string',
                'description'       => "Contact person from whom originated the request.",
                'visible'           => ['is_notary_request', '=', false]
            ],

            'request_contact_address_street' => [
                'type'              => 'string',
                'description'       => "Address of the contact person from whom originated the request.",
                'visible'           => ['is_notary_request', '=', false]
            ],

            'request_contact_address_zip' => [
                'type'              => 'string',
                'description'       => "Postal code of the contact address.",
                'visible'           => ['is_notary_request', '=', false]
            ],

            'request_contact_address_city' => [
                'type'              => 'string',
                'description'       => "City of the contact address.",
                'visible'           => ['is_notary_request', '=', false]
            ],

            'request_contact_email' => [
                'type'              => 'string',
                'usage'             => 'email',
                'description'       => "Contact main email address.",
                'visible'           => ['is_notary_request', '=', false]
            ],

            'request_notary_office_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the property lot belongs to.",
                'foreign_object'    => 'realestate\property\NotaryOffice',
                'domain'            => ['supplier_type_code', '=', 'notary_office'],
                'visible'           => [['is_notary_request', '=', true] ]
            ],

            'confirmation_notary_office_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the property lot belongs to.",
                'foreign_object'    => 'realestate\property\NotaryOffice',
                'domain'            => ['supplier_type_code', '=', 'notary_office'],
                'required'          => true
            ],

            'request_date' => [
                'type'              => 'date',
                'description'       => "Date at which the request was sent from the notary office."
            ],

            'confirmation_date' => [
                'type'              => 'date',
                'description'       => "Date at which the confirmation was sent from the notary."
            ],

            'transfer_date' => [
                'type'              => 'date',
                'description'       => "Date at which the ownership transfer took place.",
                'help'              => "This date must match the notary deed date and is therefore known only at the end of the process.",
                'default'           => function () { return time(); }
            ],

            'seller_documents_sent_date' => [
                'type'              => 'date',
                'description'       => "Date at which the ownership transfer documentation has been sent to the notary."
            ],

            'financial_statement_sent_date' => [
                'type'              => 'date',
                'default'           => false,
                'description'       => "Date at which the settlement documents have been sent to the notary."
            ],

            'description' => [
                'type'              => 'string',
                'usage'             => 'text/plain',
                'description'       => "Description of the ownership transfer."
            ],

            'property_lot_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\property\PropertyLot',
                'description'       => 'Property Lot that is subject to the transfer.',
                'help'              => 'This serve as first lot for creating the transfer, but can be extended with more lots later on.',
            ],

            'property_lots_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'realestate\property\PropertyLot',
                'foreign_field'     => 'ownership_transfers_ids',
                'rel_table'         => 'realestate_propertylot_rel_transfer',
                'rel_foreign_key'   => 'lot_id',
                'rel_local_key'     => 'transfer_id',
                'description'       => 'Property Lots that are part of the ownership transfer.',
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['active_ownership_id', '=', 'object.old_ownership_id']]
            ],

            'old_ownership_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the property lot belongs to.",
                'foreign_object'    => 'realestate\ownership\Ownership',
                'onupdate'          => 'onupdateOldOwnershipId',
                'domain'            => ['condo_id', '=', 'object.condo_id']
            ],

            'new_ownership_id' => [
                'type'              => 'many2one',
                'description'       => "The Ownership the property is being transferred to.",
                'foreign_object'    => 'realestate\ownership\Ownership',
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['id', '<>', 'object.old_ownership_id']]
            ],

            'transfer_fees_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\property\OwnershipTransferFee',
                'foreign_field'     => 'ownership_transfer_id',
                'domain'            => ['condo_id', '=', 'object.condo_id'],
                'description'       => 'Ownership Transfer fees for the processing of the file.'
            ],

            'fund_balances_description' => [
                'type'              => 'string',
                'usage'             => 'text/plain.small',
                'description'       => "Short description of the current procedures, along with involved amounts.",
                'help'              => "As per 3.94.1.1"
            ],

            'has_seller_arrears' => [
                'type'              => 'boolean',
                'description'       => "Are there any pending arrears owed by the seller?",
                'default'           => false
            ],

            'seller_arrears_description' => [
                'type'              => 'string',
                'usage'             => 'text/plain.small',
                'description'       => "Short description of the current procedures, along with involved amounts.",
                'help'              => "As per 3.94.1.2"
            ],

            'scheduled_fund_requests_description' => [
                'type'              => 'string',
                'usage'             => 'text/plain.small',
                'description'       => "Short description of the current procedures, along with involved amounts.",
                'help'              => "As per 3.94.1.3"
            ],

            'judiciary_procedures_description' => [
                'type'              => 'string',
                'usage'             => 'text/plain.small',
                'description'       => "Short description of the current procedures, along with involved amounts.",
                'help'              => "As per 3.94.1.4"
            ],

            'general_assembly_minutes_description' => [
                'type'              => 'string',
                'usage'             => 'text/plain.small',
                'description'       => "Short description of the current procedures, along with involved amounts.",
                'help'              => "As per 3.94.1.5"
            ],

            'latest_balance_sheet_description' => [
                'type'              => 'string',
                'usage'             => 'text/plain.small',
                'description'       => "Short description of the current procedures, along with involved amounts.",
                'help'              => "As per 3.94.1.6"
            ],

            'maintenance_expenses_description' => [
                'type'              => 'string',
                'usage'             => 'text/plain.small',
                'description'       => "Short description of the current procedures, along with involved amounts.",
                'help'              => "As per 3.94.2.1"
            ],

            'fund_requests_description' => [
                'type'              => 'string',
                'usage'             => 'text/plain.small',
                'description'       => "Short description of the current procedures, along with involved amounts.",
                'help'              => "As per 3.94.2.2"
            ],

            'commons_acquisitions_description' => [
                'type'              => 'string',
                'usage'             => 'text/plain.small',
                'description'       => "Short description of the current procedures, along with involved amounts.",
                'help'              => "As per 3.94.2.3"
            ],

            'condominium_debts_description' => [
                'type'              => 'string',
                'usage'             => 'text/plain.small',
                'description'       => "Short description of the current procedures, along with involved amounts.",
                'help'              => "As per 3.94.2.4"
            ],

            'has_seller_arrears_2' => [
                'type'              => 'boolean',
                'description'       => "Are there any pending arrears owed by the seller?",
                'default'           => false
            ],

            'seller_arrears_description_2' => [
                'type'              => 'string',
                'usage'             => 'text/plain.small',
                'description'       => "Short description of the current procedures, along with involved amounts.",
                'help'              => "As per 3.94.2.5",
                'visible'           => ['has_seller_arrears', '=', true]
            ],

            'has_fuel_tank' => [
                'type'              => 'boolean',
                'description'       => "Are there any pending judiciary procedures affecting the condominium?",
                'default'           => false
            ],

            'fuel_tank_capacity' => [
                'type'              => 'integer',
                'description'       => "Capacity of the fuel tank (in liters)",
                'visible'           => ['has_fuel_tank', '=', true]
            ],

            'has_intervention_record' => [
                'type'              => 'boolean',
                'description'       => "Are there any pending judiciary procedures affecting the condominium?",
                'default'           => false
            ],

            'adjustments_ids' => [
                'type'              => 'one2many',
                'description'       => "The ownership transfer the line relates to .",
                'foreign_object'    => 'realestate\property\OwnershipTransferAdjustmentLine',
                'foreign_field'     => 'ownership_transfer_id',
                'domain'            => ['condo_id', '=', 'object.condo_id'],
                'readonly'          => true
            ],

            'fund_balances_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\property\OwnershipTransferFundBalanceLine',
                'foreign_field'     => 'ownership_transfer_id',
                'description'       => 'Balances of the condominium funds with property lots shares.'
            ],

            'fund_requests_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\property\OwnershipTransferFundRequestLine',
                'foreign_field'     => 'ownership_transfer_id',
                'description'       => 'Fund requests of the condominium funds (with property lots called amounts).'
            ],

            'arrear_fundings_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\sale\pay\Funding',
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['is_paid', '=', false], ['ownership_id', '=', 'object.old_ownership_id']],
                'description'       => 'Balances of the condominium funds with property lots shares.'
            ],

            'status' => [
                'type'              => 'string',
                'selection'         => [
                    'pending',
                    'open',
                    'seller_documents_sent',
                    'confirmed',
                    'financial_statement_sent',
                    'settled',
                    'closed'
                ],
                'default'     => 'pending',
                'description' => 'Status of the ownership transfer.',
            ],

        ];
    }

    public static function getWorkflow() {
        return [
            'pending' => [
                'description' => 'Draft ownership transfer, not yet validated.',
                'icon' => 'draft',
                'transitions' => [
                    'open' => [
                        'description'   => 'Update the document to `pending`.',
                        'policies'      => ['is_valid'],
                        'onafter'       => 'onafterOpen',
                        'status'        => 'open',
                    ],
                ],
            ],
            'open' => [
                'description' => 'Validated document, waiting to be sent.',
                'icon' => 'pending_actions',
                'transitions' => [
                    'send' => [
                        'description' => 'Update the document to `seller_documents_sent`.',
                        'status' => 'seller_documents_sent',
                    ],
                    'confirm' => [
                        'description' => 'Update the document to `confirmed`.',
                        'status' => 'confirmed'
                    ]
                ],
            ],
            'seller_documents_sent' => [
                'description' => 'Validated document, waiting to be sent.',
                'icon' => 'hourglass_empty',
                'transitions' => [
                    'confirm' => [
                        'description' => 'Update the document to `confirmed`.',
                        'status' => 'confirmed',
                    ],
                    'to_complete' => [
                        'description' => 'Some additional documents are required, step back to `open`.',
                        'status' => 'open',
                    ],
                ],
            ],
            'confirmed' => [
                'description' => 'Validated settlement, waiting to be posted to accounting system.',
                'icon' => 'check',
                'transitions' => [
                    'settle' => [
                        'description' => 'Mark the ownership transfer as settled.',
                        'help' => 'The notary deed has been signed and the notary has sent the settlement documents to the accounting department.',
                        'onafter' => 'onafterSettle',
                        'status' => 'settled',
                    ],
                    'send' => [
                        'description' => 'Update the document to `processed`.',
                        'status' => 'financial_statement_sent',
                    ],
                ],
            ],
            'financial_statement_sent' => [
                'description' => 'Documentation sent, waiting for the notary deed to complete accounting settlement.',
                'icon' => 'hourglass_empty',
                'transitions' => [
                    'settle' => [
                        'description' => 'Mark the ownership transfer as settled.',
                        'help' => 'The notary deed has been signed and the notary has sent the settlement documents to the accounting department.',
                        'onafter' => 'onafterSettle',
                        'status' => 'settled',
                    ],
                    'to_complete' => [
                        'description' => 'Some additional documents are required, step back to `confirmed`.',
                        'status' => 'confirmed',
                    ],
                ],
            ],
            'settled' => [
                'description' => 'Ownership transfer is settled, the operations for the transfer accounting are pending.',
                'icon' => 'hourglass_top',
                'transitions' => [
                    'close' => [
                        'description' => 'Post accounting changes, and update the ownership transfer to `closed`.',
                        'onbefore' => 'onbeforeClose',
                        'status' => 'closed',
                    ],
                ],
            ],
            'closed' => [
                'description' => 'Ownership transfer is closed, no further actions can be taken.',
                'icon' => 'hub',
                'transitions' => [
                ],
            ],
        ];
    }


    protected static function onbeforeClose($self) {
        $self->do('perform_transfer');
    }

    protected static function onafterOpen($self) {

        $self->update([
            // 3.94.1.1
            'fund_balances_description' => "Veuillez trouver la situation des différents fonds dans le récapitulatif suivant",
            // 3.94.1.2
            'seller_arrears_description' => "Le montant à ce jour des arriérés dus par le cédant à la copropriété;",
            // 3.94.1.3
            'scheduled_fund_requests_description' => "Voir les points fonds de réserve, fonds de roulement et budget du dernier PV de l’AG.",
            // 3.94.1.4
            'judiciary_procedures_description' => "voir le point « procédures judiciaires encours » du dernier PV de l’AG.",
            // 3.94.1.5
            'general_assembly_minutes_description' => "Voir annexes ci-jointes.",
            // 3.94.1.6
            'latest_balance_sheet_description' => "Voir annexes ci-jointes.",
            // 3.94.2.1
            'maintenance_expenses_description' => "Voir annexes ci-jointes, dernier PV de l’AG.",
            // 3.94.2.2
            'fund_requests_description' => "Voici un tableau récapitulatif des appels relatifs à l'exercice en cours (montants appelés et planifiés)",
            // 3.94.2.3
            'commons_acquisitions_description' => "Veuillez-vous référer aux derniers procès-verbaux d’assemblée générale.",
            // 3.94.2.4
            'condominium_debts_description' => "Veuillez-vous référer aux derniers procès-verbaux d’assemblée générale."
        ]);

        $self
            ->do('generate_fund_balance_lines')
            ->do('generate_fund_request_lines');
    }

    protected static function onafterSettle($self) {
        $self
            ->do('generate_adjustments');
    }

    public static function getPolicies(): array {
        return [
            'can_generate_adjustments' => [
                'description' => 'Verifies that a fiscal year can be opened according to user roles.',
                'function'    => 'policyCanGenerateAdjustments'
            ],
            'can_perform_transfer' => [
                'description' => 'Verifies that a fiscal year can be opened according to user roles.',
                'function'    => 'policyCanPerformTransfer'
            ],
            'is_valid' => [
                'description' => 'Verifies that the mandatory values are present for Condominium validation.',
                'function'    => 'policyIsValid'
            ]
        ];
    }

    public static function getActions() {
        return array_merge(parent::getActions(), [
            'perform_transfer' => [
                'description'   => 'Attempt to identity document type and subtype.',
                'policies'      => ['can_perform_transfer'],
                'function'      => 'doPerformTransfer'
            ],
            'generate_adjustments' => [
                'description'   => 'Generate required accounting adjustments.',
                'policies'      => ['can_generate_adjustments'],
                'function'      => 'doGenerateAdjustments'
            ],
            'generate_fund_balance_lines' => [
                'description'   => 'Generate the table of condo funds balances.',
                'policies'      => [],
                'function'      => 'doGenerateFundBalanceLines'
            ],
            'generate_fund_request_lines' => [
                'description'   => 'Generate the table of condo funds requests.',
                'policies'      => [],
                'function'      => 'doGenerateFundRequestLines'
            ]
        ]);
    }

    protected static function policyIsValid($self) {
        $result = [];

        $self->read(['condo_id', 'old_ownership_id', 'request_date', 'property_lots_ids']);
        foreach($self as $id => $ownershipTransfer) {

            if(!$ownershipTransfer['condo_id']) {
                $result[$id] = [
                    'missing_condo_id' => 'The condominium must be provided.'
                ];
            }

            if(!$ownershipTransfer['old_ownership_id']) {
                $result[$id] = [
                    'missing_old_ownership_id' => 'The old owner must be provided.'
                ];
            }

            if(count($ownershipTransfer['property_lots_ids']) <= 0)  {
                $result[$id] = [
                    'invalid_property_lots_count' => 'There should be at least one selected property lot.'
                ];
            }

            if(!$ownershipTransfer['request_date']) {
                $result[$id] = [
                    'missing_request_date' => 'Request Date is mandatory.'
                ];
            }

        }
        return $result;
    }

    protected static function policyCanPerformTransfer($self) {
        $result = [];

        $self->read(['status', 'old_ownership_id', 'new_ownership_id']);

        foreach($self as $id => $ownershipTransfer) {
            if($ownershipTransfer['status'] !== 'settled') {
                $result[$id] = [
                    'posting_not_ready' => 'Transfer can only be performed once settlement has been confirmed.'
                ];
            }
            if(!$ownershipTransfer['old_ownership_id']) {
                $result[$id] = [
                    'missing_old_ownership_id' => 'The old ownership must be provided.'
                ];
            }
            if(!$ownershipTransfer['new_ownership_id']) {
                $result[$id] = [
                    'missing_new_ownership_id' => 'The new ownership must be provided.'
                ];
            }

        }

        return $result;
    }


    protected static function policyCanGenerateAdjustments($self) {
        $result = [];

        $self->read(['status', 'transfer_date']);

        foreach($self as $id => $ownershipTransfer) {
            if(!$ownershipTransfer['transfer_date']) {
                $result[$id] = [
                    'missing_transfer_date' => 'Precise date of the transfer is mandatory (as per notary deed).'
                ];
            }
            if(!in_array($ownershipTransfer['status'], ['settled'])) {
                $result[$id] = [
                    'generation_not_allowed' => 'Adjustments can only be generated when transfer is confirmed.'
                ];
            }
        }

        return $result;
    }


    protected static function doGenerateFundRequestLines($self) {
        $self->read(['condo_id', 'request_date', 'confirmation_date', 'transfer_date', 'status']);
        foreach($self as $id => $ownershipTransfer) {
            OwnershipTransferFundRequestLine::search(['ownership_transfer_id', '=', $id])->delete(true);
            $date = null;
            if(in_array($ownershipTransfer['status'], ['pending', 'open', 'seller_documents_sent'], true)) {
                $date = $ownershipTransfer['request_date'];
            }
            elseif(in_array($ownershipTransfer['status'], ['confirmed', 'financial_statement_sent'], true)) {
                $date = $ownershipTransfer['confirmation_date'];
            }
            else {
                $date = $ownershipTransfer['transfer_date'];
            }
            // retrieve FiscalYear
            $fiscalYear = FiscalYear::search([
                    ['condo_id', '=', $ownershipTransfer['condo_id']],
                    ['date_from', '<=', $date],
                    ['date_to', '>=', $date],
                ])
                ->first();

            if(!$fiscalYear) {
                throw new \Exception('missing_fiscal_year', EQ_ERROR_INVALID_PARAM);
            }

            // retrieve fund requests
            $fund_requests_ids = FundRequest::search([['condo_id', '=', $ownershipTransfer['condo_id']], ['fiscal_year_id', '=', $fiscalYear['id']]])->ids();
            foreach($fund_requests_ids as $fund_request_id) {
                // #memo - most fields are computed
                OwnershipTransferFundRequestLine::create([
                        'condo_id' => $ownershipTransfer['condo_id'],
                        'ownership_transfer_id' => $id,
                        'fund_request_id' => $fund_request_id
                    ]);
            }
        }
    }

    protected static function doGenerateFundBalanceLines($self) {
        $self->read(['condo_id', 'request_date', 'confirmation_date', 'transfer_date', 'status']);
        foreach($self as $id => $ownershipTransfer) {
            OwnershipTransferFundBalanceLine::search(['ownership_transfer_id', '=', $id])->delete(true);
            $date = null;
            if(in_array($ownershipTransfer['status'], ['pending', 'open', 'seller_documents_sent'], true)) {
                $date = $ownershipTransfer['request_date'];
            }
            elseif(in_array($ownershipTransfer['status'], ['confirmed', 'financial_statement_sent'], true)) {
                $date = $ownershipTransfer['confirmation_date'];
            }
            else {
                $date = $ownershipTransfer['transfer_date'];
            }

            // retrieve FiscalYear
            $fiscalYear = FiscalYear::search([
                    ['condo_id', '=', $ownershipTransfer['condo_id']],
                    ['date_from', '<=', $date],
                    ['date_to', '>=', $date],
                ])
                ->first();

            if(!$fiscalYear) {
                throw new \Exception('missing_fiscal_year', EQ_ERROR_INVALID_PARAM);
            }

            // retrieve all funds
            $funds = CondoFund::search(['condo_id', '=', $ownershipTransfer['condo_id']])
                ->read(['name', 'fund_type', 'fund_account_id']);

            foreach($funds as $fund_id => $fund) {
                $balance = 0.0;

                $accountingEntryLines = AccountingEntryLine::search([
                        ['condo_id', '=', $ownershipTransfer['condo_id']],
                        ['fiscal_year_id', '=', $fiscalYear['id']],
                        ['account_id', '=', $fund['fund_account_id']],
                        ['entry_date', '<=', $date]
                    ])
                    ->read(['credit', 'debit']);

                foreach($accountingEntryLines as $entryLine) {
                    $balance += $entryLine['credit'] - $entryLine['debit'];
                }

                if($balance !== 0.0) {
                    OwnershipTransferFundBalanceLine::create([
                        'condo_id'              => $ownershipTransfer['condo_id'],
                        'ownership_transfer_id' => $id,
                        'condo_fund_id'         => $fund_id,
                        'condo_fund_balance'    => $balance
                    ]);
                }
            }

        }
    }

    protected static function doPerformTransfer($self) {
        $self->read(['condo_id', 'transfer_date', 'property_lots_ids', 'old_ownership_id', 'new_ownership_id']);

        foreach($self as $id => $ownershipTransfer) {
            // set the new owner_id as active for the targeted property_lots
            PropertyLot::ids($ownershipTransfer['property_lots_ids'])
                ->update(['active_ownership_id' => $ownershipTransfer['new_ownership_id']]);

            // update existing PropertyLotOwnership (`date_to`)
            foreach($ownershipTransfer['property_lots_ids'] as $property_lot_id) {
                // there should be only one match
                PropertyLotOwnership::search([
                        ['condo_id', '=', $ownershipTransfer['condo_id']],
                        ['ownership_id', '=', $ownershipTransfer['old_ownership_id']],
                        ['property_lot_id', '=', $property_lot_id],
                        ['date_to', '=', null]
                    ])
                    ->update(['date_to' => $ownershipTransfer['transfer_date']]);
            }

            // 1-a) generate refund based on OwnershipTransferAdjustmentLine (accounting entries through MoneyRefund)

            $adjustments = OwnershipTransferAdjustmentLine::search([
                    ['ownership_transfer_id', '=', $id]
                ])
                ->read(['request_account_id', 'amount']);

            foreach($adjustments as $adjustment) {


            }

            // 1-b) generate exceptional fund request(s)


            // 2) adapt the pending fund request executions: move the relevant lines to the new owner

            // retrieve impacted fiscal year
            $fiscalYear = FiscalYear::search([
                    ['condo_id', '=', $ownershipTransfer['condo_id']],
                    ['date_from', '<=', $ownershipTransfer['transfer_date']],
                    ['date_to', '>=', $ownershipTransfer['transfer_date']]
                ])
                ->read(['id', 'fiscal_period_frequency', 'date_from', 'date_to'])
                ->first();

            if(!$fiscalYear) {
                throw new \Exception("fiscal_year_not_found", EQ_ERROR_UNKNOWN_OBJECT);
            }

            // retrieve involved fund request executions
            $fundRequestExecutions = FundRequestExecution::search([['fiscal_year_id', '=', $fiscalYear['id']], ['status', '=', 'proforma']])->read(['fund_request_id']);
            // for each execution, create a new execution line
            foreach($fundRequestExecutions as $fund_request_execution_id => $fundRequestExecution) {
                $requestExecutionLine = FundRequestExecutionLine::create([
                        'condo_id'              => $ownershipTransfer['condo_id'],
                        'fund_request_id'       => $fundRequestExecution['fund_request_id'],
                        // #memo - request_execution_id is an alias of invoice_id
                        'invoice_id'            => $fund_request_execution_id,
                        'ownership_id'          => $ownershipTransfer['new_ownership_id'],
                        // 'total'                 => $map_amounts[$execution_date]
                    ])
                    ->first();

                $amount = 0.0;
                // retrieve all execution line entries related to one of the sold lots
                $executionLineEntries = FundRequestExecutionLineEntry::search([
                        ['request_execution_id', '=', $fund_request_execution_id],
                        ['ownership_id', '=',  $ownershipTransfer['old_ownership_id']],
                        ['property_lot_id', 'in', $ownershipTransfer['property_lots_ids']]
                    ])
                    ->read(['request_execution_line_id', 'called_amount']);

                foreach($executionLineEntries as $execution_line_entry_id => $executionLineEntry) {
                    $amount += $executionLineEntry['called_amount'];
                    FundRequestExecutionLineEntry::id($execution_line_entry_id)->update([
                            'request_execution_line_id' => $requestExecutionLine['id'],
                            'ownership_id' => $ownershipTransfer['new_ownership_id']
                        ]);
                }
                // adjust the amount of the execution line according to the sum of the concerned line entries
                FundRequestExecutionLine::id($requestExecutionLine['id'])->update(['total' => $amount]);
            }

        }
    }

    protected static function doGenerateAdjustments($self) {
        $self->read(['condo_id', 'transfer_date', 'property_lots_ids', 'old_ownership_id', 'new_ownership_id']);

        foreach($self as $id => $ownershipTransfer) {
            OwnershipTransferAdjustmentLine::search(['ownership_transfer_id', '=', $id])->delete(true);

            // retrieve impacted fiscal year
            $fiscalYear = FiscalYear::search([
                    ['condo_id', '=', $ownershipTransfer['condo_id']],
                    ['date_from', '<=', $ownershipTransfer['transfer_date']],
                    ['date_to', '>=', $ownershipTransfer['transfer_date']]
                ])
                ->read(['id', 'fiscal_period_frequency', 'date_from', 'date_to'])
                ->first();

            if(!$fiscalYear) {
                throw new \Exception("fiscal_year_not_found", EQ_ERROR_UNKNOWN_OBJECT);
            }

            // retrieve impacted fiscal period
            $fiscalPeriod = FiscalPeriod::search([
                    ['condo_id', '=', $ownershipTransfer['condo_id']],
                    ['fiscal_year_id', '=', $fiscalYear['id']],
                    ['date_from', '<=', $ownershipTransfer['transfer_date']],
                    ['date_to', '>=', $ownershipTransfer['transfer_date']]
                ])
                ->read(['id', 'date_from', 'date_to'])
                ->first();

            // 1) compute the amounts to be reimbursed/charged for the sold lots based on fun requests executions already posted
            $adjustments = [
                    'reimburse' => [],
                    'schedule' => []
                ];

            // A) already invoiced

            // fully reimburse, working funds based on fund balance
            $working_funds_reimbursements = self::computeWorkingFundsReimbursements($id);
            // {fund_id, property_lot_id} -> amount_to_reimburse

            foreach($working_funds_reimbursements as $fund_id => $reimbursements) {
                foreach($reimbursements as $property_lot_id => $amount) {
                    OwnershipTransferAdjustmentLine::create([
                        'condo_id'              => $ownershipTransfer['condo_id'],
                        'ownership_transfer_id' => $id,
                        'condo_fund_id'         => $fund_id,
                        'amount'                => $amount,
                        'property_lot_id'       => $property_lot_id,
                        'ownership_id'          => $ownershipTransfer['old_ownership_id']
                    ]);
                }
            }

            // prorata reimbursement
            $expense_provisions_funds_reimbursements = self::computeReimbursementsByRequestType($id, 'expense_provisions');
            // {request_id, property_lot_id} -> amount to reimburse

            foreach($expense_provisions_funds_reimbursements as $fund_request_id => $reimbursements) {
                foreach($reimbursements as $property_lot_id => $amount) {
                    OwnershipTransferAdjustmentLine::create([
                        'condo_id'              => $ownershipTransfer['condo_id'],
                        'ownership_transfer_id' => $id,
                        'fund_request_id'       => $fund_request_id,
                        'amount'                => $amount,
                        'property_lot_id'       => $property_lot_id,
                        'ownership_id'          => $ownershipTransfer['old_ownership_id']
                    ]);
                }
            }



            // B) scheduled but not invoiced (proforma - adaptations - pas de remboursement)

            // pour le old_ownership : retirer les montants correspondant au RequestExecutionLineEntry, ce qui donne la liste pour créer un appel exceptionnel pour le new ownership
            // pour le new_ownership, pour chaque type de fonds, créer un appel exceptionnel
            self::computeScheduledByRequestType($id, 'working_fund');
            self::computeScheduledByRequestType($id, 'reserve_fund');
            self::computeScheduledByRequestType($id, 'expense_provisions');
            // {request_execution_id, property_lot_id} -> amount to move
            // ? faire ceci en une étape sans passer par les adjustments


        }

    }

    private static function computeWorkingFundsReimbursements($id) {
        $map_funds_reimbursement = [];

        $ownershipTransfer = self::id($id)->read(['condo_id', 'transfer_date', 'status', 'property_lots_ids'])->first();

        if(!$ownershipTransfer['transfer_date']) {
            throw new \Exception('missing_transfer_date', EQ_ERROR_INVALID_PARAM);
        }

        $date = $ownershipTransfer['transfer_date'];

        $fiscalYear = FiscalYear::search([
                ['condo_id', '=', $ownershipTransfer['condo_id']],
                ['date_from', '<=', $date],
                ['date_to', '>=', $date],
            ])
            ->first();

        if(!$fiscalYear) {
            throw new \Exception('missing_fiscal_year', EQ_ERROR_INVALID_PARAM);
        }

        // retrieve all working funds
        $funds = CondoFund::search([
                ['condo_id', '=', $ownershipTransfer['condo_id']],
                ['fund_type', '=', 'working_fund']
            ])
            ->read(['name', 'fund_account_id', 'apportionment_id']);

        foreach($funds as $fund_id => $fund) {
            // retrieve the fund balance
            $balance = 0.0;
            $accountingEntryLines = AccountingEntryLine::search([
                    ['condo_id', '=', $ownershipTransfer['condo_id']],
                    ['fiscal_year_id', '=', $fiscalYear['id']],
                    ['account_id', '=', $fund['fund_account_id']],
                    ['entry_date', '<=', $date]
                ])
                ->read(['credit', 'debit']);

            foreach($accountingEntryLines as $entryLine) {
                $balance += $entryLine['credit'] - $entryLine['debit'];
            }

            // compute share to reimburse for each implied property lot
            $apportionment = Apportionment::id($fund['apportionment_id'])->read(['total_shares'])->first();

            if(!$apportionment) {
                throw new \Exception('missing_apportionment', EQ_ERROR_INVALID_PARAM);
            }

            $map_funds_reimbursement[$fund_id] = [];

            foreach($ownershipTransfer['property_lots_ids'] as $property_lot_id) {
                $apportionmentShare = PropertyLotApportionmentShare::search([ ['property_lot_id', '=', $property_lot_id], ['apportionment_id', '=', $apportionment['id']] ])
                    ->read(['property_lot_shares'])
                    ->first();

                if($apportionmentShare) {
                    $map_funds_reimbursement[$fund_id][$property_lot_id] = round($balance * $apportionmentShare['property_lot_shares'] / $apportionment['total_shares'], 2);
                }
            }

        }

        return $map_funds_reimbursement;
    }

    /**
     * Retrieve the billed amounts to be reimbursed, by lot, for a given fund request type.
     * This method considers only executed fund requests made before the transfer date.
     * Returned result is an associative array mapping fund_request_id and property_lot_id with related amount to reimburse.
     *
     * @param int $id Ownership transfer ID.
     * @param string $request_type The type of request to retrieve reimbursements for.
     */
    private static function computeReimbursementsByRequestType($id, $request_type) {
        $map_requests_reimbursement = [];

        // retrieve ownership transfer
        $ownershipTransfer = self::id($id)
            ->read([
                'condo_id',
                'transfer_date',
                'property_lots_ids',
                'old_ownership_id'
            ])
            ->first();

        // retrieve impacted fiscal year
        $fiscalYear = FiscalYear::search([
                ['condo_id', '=', $ownershipTransfer['condo_id']],
                ['date_from', '<=', $ownershipTransfer['transfer_date']],
                ['date_to', '>=', $ownershipTransfer['transfer_date']]
            ])
            ->read(['id', 'date_from', 'date_to'])
            ->first();


        // 1) retrieve all relevant FundRequests
        $fund_requests_ids = FundRequest::search([
                ['condo_id', '=', $ownershipTransfer['condo_id']],
                ['status', '=', 'active'],
                ['request_type', '=', $request_type],
                ['request_date', '>=', $fiscalYear['date_from']],
                ['request_date', '<=', $fiscalYear['date_to']]
            ])
            ->ids();

        // 2) retrieve all relevant FundRequestExecution (sales invoices)
        $fundRequestExecutions = FundRequestExecution::search([
                ['condo_id', '=', $ownershipTransfer['condo_id']],
                ['status', '=', 'posted'],
                ['posting_date', '<', $ownershipTransfer['transfer_date']],
                ['fund_request_id', 'in', $fund_requests_ids]
            ])
            ->read(['posting_date', 'fund_request_id' => ['id', 'has_date_range', 'date_range_frequency', 'date_to']]);

        foreach($fundRequestExecutions as $fund_request_execution_id => $fundRequestExecution) {

            $fund_request_id = $fundRequestExecution['fund_request_id']['id'];
            if(!isset($map_requests_reimbursement[$fund_request_id])) {
                $map_requests_reimbursement[$fund_request_id] = array_fill_keys($ownershipTransfer['property_lots_ids'], 0.0);
            }

            // 3) compute prorata for invoiced date range
            $prorata = 0;
            $request_execution_date_to = $fundRequestExecution['fund_request_id']['date_to'];
            if($fundRequestExecution['fund_request_id']['has_date_range']) {
                $frequency = $fundRequestExecution['fund_request_id']['date_range_frequency'];
                $request_execution_date_to = min($request_execution_date_to, strtotime("+$frequency months", $fundRequestExecution['posting_date']) - 86400);
            }

            $total_duration = $request_execution_date_to - $fundRequestExecution['posting_date'];
            $accountable_duration = min($total_duration, $ownershipTransfer['transfer_date'] - $fundRequestExecution['posting_date']);

            if($total_duration > 0 && $accountable_duration > 0) {
                $prorata = $accountable_duration / $total_duration;
            }

            // 4) retrieve the breakdown of the total amount called for the concerned ownership
            $fundRequestExecutionEntries = FundRequestExecutionLineEntry::search([
                    ['request_execution_id', '=', $fund_request_execution_id],
                    ['ownership_id', '=', $ownershipTransfer['old_ownership_id']]
                ])
                ->read(['property_lot_id', 'called_amount']);

            // 5) calculation of the amounts to be reimbursed, by sold lot
            foreach($fundRequestExecutionEntries as $fundRequestExecutionEntry) {
                $property_lot_id = $fundRequestExecutionEntry['property_lot_id'];
                $map_requests_reimbursement[$fund_request_id][$property_lot_id] += round($fundRequestExecutionEntry['called_amount'] * $prorata, 2);
            }
        }

        return $map_requests_reimbursement;
    }


    private static function computeScheduledByRequestType($id, $request_type) {
        $map_requests_movement = [];

        // retrieve ownership transfer
        $ownershipTransfer = self::id($id)
            ->read([
                'condo_id',
                'transfer_date',
                'property_lots_ids',
                'old_ownership_id'
            ])
            ->first();

        // retrieve impacted fiscal year
        $fiscalYear = FiscalYear::search([
                ['condo_id', '=', $ownershipTransfer['condo_id']],
                ['date_from', '<=', $ownershipTransfer['transfer_date']],
                ['date_to', '>=', $ownershipTransfer['transfer_date']]
            ])
            ->read(['id', 'date_from', 'date_to'])
            ->first();


        // 1) retrieve all relevant FundRequests
        $fund_requests_ids = FundRequest::search([
                ['condo_id', '=', $ownershipTransfer['condo_id']],
                ['status', '=', 'active'],
                ['request_type', '=', $request_type],
                ['request_date', '>=', $fiscalYear['date_from']],
                ['request_date', '<', $fiscalYear['date_to']]
            ])
            ->ids();

        // 2) retrieve all relevant FundRequestExecution (sales invoices)
        // #memo - we need executions not yet posted (posted_date might be earlier than $ownershipTransfer['transfer_date'])
        $fundRequestExecutions = FundRequestExecution::search([
                ['condo_id', '=', $ownershipTransfer['condo_id']],
                ['status', '=', 'proforma'],
                ['fund_request_id', 'in', $fund_requests_ids]
            ])
            ->read(['fund_request_id']);


        $fund_request_id = null;

        foreach($fundRequestExecutions as $fund_request_execution_id => $fundRequestExecution) {

            // 3) not necessary here - compute prorata for invoiced date range

            if(!$fund_request_id) {
                $fund_request_id = $fundRequestExecution['fund_request_id'];
            }

            $map_requests_movement[$fund_request_execution_id] = array_fill_keys($ownershipTransfer['property_lots_ids'], 0.0);

            // 4) retrieve the breakdown of the total amounts called for the concerned ownership
            $fundRequestExecutionEntries = FundRequestExecutionLineEntry::search([
                    ['request_execution_id', '=', $fund_request_execution_id],
                    ['ownership_id', '=', $ownershipTransfer['old_ownership_id']]
                ])
                ->read(['property_lot_id', 'called_amount']);

            // 5) calculation of the amounts to be reimbursed, by sold lot
            foreach($fundRequestExecutionEntries as $fundRequestExecutionEntry) {
                $property_lot_id = $fundRequestExecutionEntry['property_lot_id'];
                $map_requests_movement[$fund_request_execution_id][$property_lot_id] = $fundRequestExecutionEntry['called_amount'];
            }

        }

        return $map_requests_movement;
    }


    protected static function onupdateOldOwnershipId($self) {
        // make sure no propertylots from other ownership
        $self->read(['condo_id', 'property_lots_ids', 'old_ownership_id']);
        foreach($self as $id => $ownershipTransfer) {
            $changes = [];
            // retrieve all property lots that are not part of the ownership transfer
            $propertyLots = PropertyLot::ids($ownershipTransfer['property_lots_ids'])
                ->read(['active_ownership_id']);
            foreach($propertyLots as $property_lot_id => $propertyLot) {
                if($propertyLot['active_ownership_id'] != $ownershipTransfer['old_ownership_id']) {
                    $changes[] = "-$property_lot_id";
                }
            }
            if(count($changes) > 0) {
                // remove all property lots that are not part of the old ownership
                self::id($id)->update([
                    'property_lots_ids' => $changes
                ]);
            }
        }
    }

    public static function onchange($event, $values, $lang) {
        $result = [];
        // synchronize ownership & property lots
        // #memo - we must be able to assign any ownership (not only active ones)
        if(array_key_exists('old_ownership_id', $event)) {
            $result['property_lots_ids'] = [];
            if($event['old_ownership_id']) {
                $propertyOwnerships = PropertyLotOwnership::search([['ownership_id', '=', $event['old_ownership_id']]])->read(['property_lot_id'])->get(true);
                $property_lots_ids = array_map(function ($a) {return $a['property_lot_id'];}, $propertyOwnerships);
                if(!$values['property_lot_id'] || !in_array($values['property_lot_id'], $property_lots_ids) ) {
                    $result['property_lot_id'] = [
                        'domain' => [['condo_id', '=', $values['condo_id']], ['id', 'in', $property_lots_ids]]
                    ];
                }
                $result['property_lots_ids'] = $property_lots_ids;
            }
            else {
                $result['old_ownership_id'] = [
                    'domain' => ['condo_id', '=', $values['condo_id']]
                ];
                $result['property_lot_id'] = [
                    'domain' => ['condo_id', '=', $values['condo_id']]
                ];
            }
        }

        if(array_key_exists('property_lot_id', $event)) {
            if($event['property_lot_id']) {
                $propertyOwnerships = PropertyLotOwnership::search([['property_lot_id', '=', $event['property_lot_id']]])->read(['ownership_id'])->get(true);
                $ownerships_ids = array_map(function ($a) {return $a['ownership_id'];}, $propertyOwnerships);
                if(!isset($values['old_ownership_id']) || !in_array($values['old_ownership_id'], $ownerships_ids)) {
                    $result['old_ownership_id'] = null;
                    if(count($ownerships_ids) == 1) {
                        $ownership_id = reset($ownerships_ids);
                        $ownership = Ownership::id($ownership_id)->read(['id', 'name'])->first();
                        $result['old_ownership_id'] = [
                            'id'    => $ownership['id'],
                            'name'  => $ownership['name']
                        ];
                        $result['property_lots_ids'] = [$event['property_lot_id']];
                    }
                    else {
                        $result['old_ownership_id'] = [
                            'domain' => [['condo_id', '=', $values['condo_id']], ['id', 'in', $ownerships_ids]]
                        ];
                    }

                }
            }
            else {
                $result['old_ownership_id'] = [
                    'domain' => ['condo_id', '=', $values['condo_id']]
                ];
                $result['property_lot_id'] = [
                    'domain' => ['condo_id', '=', $values['condo_id']]
                ];
            }
        }

        /*
        if(!$values['has_suggested_identity'] || !$values['is_accepted_suggested_identity']) {
            // #memo - attempt to retrieve matching Identity at this stage since values have been adapted/sanitized to their final (stored) format
            // #todo - use global DB for retrieving candidate Identity
            $identity_id = null;

            if(isset($values['identity_citizen_identification']) && strlen($values['identity_citizen_identification'])) {
                $identity_id = self::computeIdentitySuggestion(null, null, $values['identity_citizen_identification'], null, null, null);
            }
            elseif(isset($values['identity_email']) && strlen($values['identity_email'])) {
                $identity_id = self::computeIdentitySuggestion(null, null, null, $values['identity_email'], null, null);
            }
            elseif(isset($values['identity_firstname'], $values['identity_lastname'], $values['identity_address_street'], $values['identity_address_zip'])) {
                $identity_id = self::computeIdentitySuggestion($values['identity_firstname'], $values['identity_lastname'], null, null, $values['identity_address_street'], $values['identity_address_zip']);
            }

            // an existing identity candidate has been found
            if($identity_id) {

                // #todo - use global DB for retrieving candidate Identity

                // populate with values as suggestion
                $identity = Identity::id($identity_id)
                    ->read([
                        'name', 'firstname', 'lastname', 'title', 'gender', 'citizen_identification', 'email',
                        'address_street', 'address_dispatch', 'address_zip', 'address_city', 'address_country',
                    ])
                    ->first();

                $result['has_suggested_identity'] = true;
                $result['suggested_identity_uuid'] = $identity_id;

                $result['suggested_identity_log'] = "
                    <b>prenom</b>: {$identity['firstname']}
                    <b>nom</b>: {$identity['lastname']}
                    <b>email</b>: {$identity['email']}
                    <b>rue</b>: {$identity['address_street']}
                    <b>CP</b>: {$identity['address_zip']}
                    <b>ville</b>: {$identity['address_city']}
                    <b>pays</b>: {$identity['address_country']}
                ";

            }
        }
        */

        return $result;
    }

    /**
     * Returns cities' names based on a zip code and a country.
     */
    private static function computeCitiesByZip($zip, $country, $lang) {
        $result = null;

        $file = EQ_BASEDIR."/packages/identity/i18n/{$lang}/zipcodes/{$country}.json";

        if(file_exists($file)) {
            $data = file_get_contents($file);
            $map_zip = json_decode($data, true);
            $result = $map_zip[$zip] ?? null;
        }
        // fallback to english value, if defined
        if(!$result) {
            $file = EQ_BASEDIR."/packages/identity/i18n/en/zipcodes/{$country}.json";
            if(file_exists($file)) {
                $data = file_get_contents($file);
                $map_zip = json_decode($data, true);
                if(isset($map_zip[$zip])) {
                    $result = $map_zip[$zip];
                }
            }
        }
        return $result;
    }

    /**
     * Ordre de comparaison :
     *
     * 1. N° registre national (rare)
     * 2. Email
     * 3. Nom + prénom + adresse
     */
    private static function computeIdentitySuggestion($firstname, $lastname, $citizen_identification, $email, $address_street, $address_zip) {
        $identity_id = null;
        // citizen_identification
        if($citizen_identification) {
            $identities_ids = Identity::search(['citizen_identification', '=', $citizen_identification])->ids();
            if(count($identities_ids) === 1) {
                $identity_id = reset($identities_ids);
            }
        }
        elseif($email) {
            $identities_ids = Identity::search(['email', 'ilike', $email])->ids();
            if(count($identities_ids) === 1) {
                $identity_id = reset($identities_ids);
            }
        }
        elseif(isset($firstname, $lastname, $address_street, $address_zip)) {
            $address_hash = self::computeAddressHash($address_street, $address_zip);
            $identities_ids = Identity::search([
                    ['firstname', 'ilike', $firstname],
                    ['lastname', 'ilike', $lastname],
                    ['address_hash', '=', $address_hash],
                ])
                ->ids();
            if(count($identities_ids) === 1) {
                $identity_id = reset($identities_ids);
            }
        }
        return $identity_id;
    }

    private static function computeAddressHash($address_street, $address_zip) {
        $address = $address_street;

        $address = strtolower(TextTransformer::toAscii($address));
        $zip = $address_zip;

        // remove non-alphanum chars (keep dash & space)
        $address = preg_replace('/[^a-z0-9\-\s]/', '', $address);
        $zip = preg_replace('/[^a-z0-9]/', '', $zip);

        // remove redundant spaces
        $address = preg_replace('/\s+/', ' ', trim($address));

        // split street and number
        /*
            matches
                17-19 rue de l'Église
                23 Avenue Léopold 2
        */
        if(preg_match('/^(\d+[a-z\-0-9]*)\s+(.*)$/i', $address, $matches)) {
            $number = $matches[1];
            $street = $matches[2];
        }
        /*
            matches
                Avenue Archibald 12
                Rue du champ, 22-24
        */
        elseif(preg_match('/^(.*)\s+(\d+[a-z\-0-9]*)$/i', $address, $matches)) {
            $street = $matches[1];
            $number = $matches[2];
        }
        else {
            $street = $address;
            $number = '';
        }

        // normalize address
        $street = str_replace(' ', '_', trim($street));
        $number = str_replace(' ', '', trim($number));

        return md5("{$street}::{$number}::{$zip}");
    }

}
