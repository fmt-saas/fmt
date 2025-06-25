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
use finance\accounting\FiscalPeriod;
use finance\accounting\FiscalYear;
use realestate\funding\FundRequest;
use realestate\funding\FundRequestExecution;
use realestate\funding\FundRequestExecutionLine;
use realestate\funding\FundRequestLineEntryLot;

class OwnershipTransfer extends \equal\orm\Model {

    public static function getColumns() {
        return [

            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the property lot belongs to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'required'          => true
            ],

            'request_date' => [
                'type'              => 'date',
                'description'       => "Date at which the first request from the notary was received."
            ],

            'confirmation_date' => [
                'type'              => 'date',
                'description'       => "Date at which the confirmation from the notary was received."
            ],

            'transfer_date' => [
                'type'              => 'date',
                'description'       => "Date at which the ownership transfer is scheduled",
                'help'              => "This date must match the notary deed date.",
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

            'property_lots_shares' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'function'          => 'calcPropertyLotsShares',
                'description'       => 'Total shares of selected property lots over the Condominium apportionment.',
            ],

            'working_fund_balance' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'function'          => 'calcWorkingFundBalance',
                'description'       => 'Balance of the working fund, at the transfer date.',
            ],

            'reserve_fund_balance' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'function'          => 'calcReserveFundBalance',
                'description'       => 'Balance of the reserve fund, at the transfer date.',
            ],

            'old_ownership_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the property lot belongs to.",
                'foreign_object'    => 'realestate\ownership\Ownership',
                'onupdate'          => 'onupdateOldOwnershipId',
                'domain'            => ['condo_id', '=', 'object.condo_id']
            ],

            'is_existing_new_ownership' => [
                'type'              => 'boolean',
                'description'       => "The Ownership the property is transferred to.",
                'default'           => false
            ],

            'new_ownership_id' => [
                'type'              => 'many2one',
                'description'       => "The Ownership the property is being transferred to.",
                'foreign_object'    => 'realestate\ownership\Ownership',
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['id', '<>', 'object.old_ownership_id']],
                'visible'           => [[['is_existing_new_ownership', '=', true]], [['is_resolved_new_ownership', '=', true]]]
            ],

            'has_suggested_identity' => [
                'type'              => 'boolean',
                'description'       => "The provided details resolved to a suggested identity.",
                'default'           => false
            ],

            'suggested_identity_log' => [
                'type'              => 'string',
                'usage'             => 'text/plain.small',
                'description'       => "The information about retrieved/suggested identity.",
                'readonly'          => true,
                'visible'           => [['is_existing_new_ownership', '=', false], ['has_suggested_identity', '=', true]]
            ],

            'suggested_identity_uuid' => [
                'type'              => 'integer',
                'description'       => "The global UUID of the suggested identity for the new Owner.",
                'readonly'          => true
            ],

            'is_accepted_suggested_identity' => [
                'type'              => 'boolean',
                'description'       => "Suggested identity accepted as new Owner.",
                'default'           => false
            ],

            'is_resolved_new_ownership' => [
                'type'              => 'boolean',
                'description'       => "The identity of the new Owner has been resolved to an Ownership.",
                'help'              => "This is set according to the status and new owner identity, either suggested or created using manually entered data.",
                'default'           => false
            ],

            'identity_firstname' => [
                'type'              => 'string',
                'description'       => "Full name of the contact (must be a person, not a role).",
                'visible'           => ['is_existing_new_ownership', '=', false]
            ],

            'identity_lastname' => [
                'type'              => 'string',
                'description'       => 'Reference contact surname.',
                'visible'           => ['is_existing_new_ownership', '=', false]
            ],

            'identity_gender' => [
                'type'              => 'string',
                'selection'         => ['M' => 'Male', 'F' => 'Female', 'X' => 'Non-binary'],
                'description'       => 'Reference contact gender.',
                'visible'           => ['is_existing_new_ownership', '=', false]
            ],

            'identity_title' => [
                'type'              => 'string',
                'selection'         => ['Ms' => 'Miss', 'Mrs' => 'Misses', 'Mr' => 'Mister'],
                'description'       => 'Reference contact title.',
                'visible'           => ['is_existing_new_ownership', '=', false]
            ],

            'identity_date_of_birth' => [
                'type'              => 'date',
                'description'       => 'Date of birth.',
                'visible'           => ['is_existing_new_ownership', '=', false]
            ],

            'identity_lang_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'core\Lang',
                'description'       => "Preferred language of the identity.",
                'default'           => Setting::get_value('identity', 'organization', 'identity_lang_default', 1),
                'visible'           => ['is_existing_new_ownership', '=', false]
            ],

            /*
                Description of the Identity address.
                For organizations this is the official (legal) address (typically headquarters, but not necessarily)
            */
            'identity_address_street' => [
                'type'              => 'string',
                'description'       => 'Street and number.',
                'visible'           => ['is_existing_new_ownership', '=', false]
            ],

            'identity_address_dispatch' => [
                'type'              => 'string',
                'description'       => 'Optional info for mail dispatch (apartment, box, floor, ...).',
                'visible'           => ['is_existing_new_ownership', '=', false]
            ],

            'identity_address_city' => [
                'type'              => 'string',
                'description'       => 'City.',
                'visible'           => ['is_existing_new_ownership', '=', false]
            ],

            'identity_address_zip' => [
                'type'              => 'string',
                'description'       => 'Postal code.',
                'visible'           => ['is_existing_new_ownership', '=', false]
            ],

            'identity_address_country' => [
                'type'              => 'string',
                'usage'             => 'country/iso-3166:2',
                'description'       => 'Country.',
                'default'           => 'BE',
                'visible'           => ['is_existing_new_ownership', '=', false],
            ],

            'identity_email' => [
                'type'              => 'string',
                'usage'             => 'email',
                'description'       => "Identity main email address.",
                'visible'           => ['is_existing_new_ownership', '=', false]
            ],

            'identity_phone' => [
                'type'              => 'string',
                'usage'             => 'phone',
                'description'       => "Identity secondary phone number (mobile or landline).",
                'visible'           => ['is_existing_new_ownership', '=', false]
            ],

            'identity_bank_account_iban' => [
                'type'              => 'string',
                'usage'             => 'uri/urn.iban',
                'description'       => "Number of the bank account of the Identity, if any.",
                'visible'           => ['is_existing_new_ownership', '=', false]
            ],

            'identity_citizen_identification' => [
                'type'              => 'string',
                'description'       => 'Citizen registration number, if any.',
                'visible'           => ['is_existing_new_ownership', '=', false]
            ],

            'adjustments_ids' => [
                'type'              => 'one2many',
                'description'       => "The ownership transfer the line relates to .",
                'foreign_object'    => 'realestate\property\OwnershipTransferAdjustmentLine',
                'foreign_field'     => 'ownership_transfer_id',
                'domain'            => ['condo_id', '=', 'object.condo_id'],
                'readonly'          => true
            ],

            'status' => [
                'type'              => 'string',
                'selection'         => [
                    'draft',
                    'open',
                    'seller_documents_sent',
                    'confirmed',
                    'financial_statement_sent',
                    'accounting_pending',
                    'closed'
                ],
                'default'     => 'draft',
                'description' => 'Status of the ownership transfer.',
            ],

        ];
    }

    public static function getWorkflow() {
        return [
            'draft' => [
                'description' => 'Draft ownership transfer, not yet validated.',
                'icon' => 'drafts',
                'transitions' => [
                    'open' => [
                        'description' => 'Update the document to `pending`.',
                        'status' => 'open',
                    ],
                ],
            ],
            'open' => [
                'description' => 'Validated document, waiting to be sent.',
                'icon' => 'pending_actions',
                'transitions' => [
                    'send' => [
                        'description' => 'Update the document to `processed`.',
                        'status' => 'seller_documents_sent',
                    ],
                ],
            ],
            'seller_documents_sent' => [
                'description' => 'Validated document, waiting to be sent.',
                'icon' => 'hourglass_empty',
                'transitions' => [
                    'confirm' => [
                        'description' => 'Update the document to `processed`.',
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
                    'send' => [
                        'description' => 'Update the document to `processed`.',
                        'status' => 'financial_statement_sent',
                    ],
                ],
            ],
            'financial_statement_sent' => [
                'description' => 'Documentation sent, waiting for the notary .',
                'icon' => 'hourglass_empty',
                'transitions' => [
                    'settle' => [
                        'description' => 'Mark the ownership transfer as settled.',
                        'help' => 'The notary deed has been signed and the notary has sent the settlement documents to the accounting department.',
                        'status' => 'accounting_pending',
                    ],
                    'to_complete' => [
                        'description' => 'Some additional documents are required, step back to `confirmed`.',
                        'status' => 'confirmed',
                    ],
                ],
            ],
            'accounting_pending' => [
                'description' => 'Ownership transfer is pending, waiting for the notary deed to complete accounting settlement.',
                'icon' => 'hourglass_top',
                'transitions' => [
                    'close' => [
                        'description' => 'Post accounting changes, and update the ownership transfer to `closed`.',
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

    protected static function onbeforeOpen($self) {
        $self->do('generate_adjustments');
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
            ]
        ]);
    }

    protected static function policyCanPerformTransfer($self) {
        $result = [];

        $self->read(['status']);

        foreach($self as $id => $ownershipTransfer) {
            if($ownershipTransfer['status'] !== 'accounting_pending') {
                $result[$id] = [
                    'posting_not_ready' => 'Transfer can only be performed once settlement has been confirmed.'
                ];
            }
        }

        return $result;
    }


    protected static function policyCanGenerateAdjustments($self) {
        $result = [];

        $self->read(['status']);

        foreach($self as $id => $ownershipTransfer) {
            if(!in_array($ownershipTransfer['status'], ['draft', 'open', 'confirmed'])) {
                $result[$id] = [
                    'generation_not_allowed' => 'Adjustments can only be generated while not sent to notary.'
                ];
            }
        }

        return $result;
    }

// #todo
    protected static function calcWorkingFundBalance($self) {
    }

    protected static function calcReserveFundBalance($self) {
    }

    protected static function calcPropertyLotsShares($self) {
        $result = [];

        $self->read(['condo_id', 'property_lots_ids']);

        foreach($self as $id => $ownershipTransfer) {
            $result[$id] = 0;
            $apportionment = Apportionment::search([['condo_id', '=', $ownershipTransfer['condo_id']], ['is_statutory', '=', true]])->first();
            if(!$apportionment) {
                continue;
            }
            foreach($ownershipTransfer['property_lots_ids'] as $property_lot_id) {
                $apportionmentShare = PropertyLotApportionmentShare::search([['apportionment_id', '=', $apportionment['id']], ['property_lot_id', '=', $property_lot_id]])->read(['property_lot_shares'])->first();
                $result[$id] += $apportionmentShare['property_lot_shares'];
            }
        }

        return $result;
    }

    protected static function doPerformTransfer($self) {
        $self->read(['old_ownership_id', 'property_lots_ids', 'new_ownership_id']);

        foreach($self as $id => $ownershipTransfer) {
            // set the new owner_id as active for the targeted property_lots
            PropertyLot::ids($ownershipTransfer['property_lots_ids'])->update(['active_ownership_id' => $ownershipTransfer['new_ownership_id']]);

            // mettre à jour les date_to des PropertyLotOwnership

            // creéer les écritures sur base des adjustments
            $adjustments = OwnershipTransferAdjustmentLine::search([
                    ['ownership_transfer_id', '=', $id],
                    ['adjustment_type', '=', 'reimburse']
                ])
                ->read(['request_account_id', 'amount']);
// #todo
            foreach($adjustments as $adjustment) {
            }


            // adapter les ExecutionLines sur base des adjustments
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

            // compute number of days during impacted fiscal year for which the old ownership is accountable
            $delta = $ownershipTransfer['transfer_date'] - $fiscalPeriod['date_from'];
            $old_owner_days = floor($delta / 86400);

            // retrieve working_fund account
            $account = Account::search([
                    ['condo_id', '=', $ownershipTransfer['condo_id']],
                    ['operation_assignment', '=', 'working_fund']
                ])
                ->first();

            // 1) compute the amounts to be reimbursed/charged for the sold lots based on fun requests executions already posted
            $adjustments = [
                    'reimburse' => [],
                    'schedule' => []
                ];

            // already invoiced
            $adjustments['reimburse'][] = self::computeReimbursementsByRequestType($id, 'working_fund');
            $adjustments['reimburse'][] = self::computeReimbursementsByRequestType($id, 'expense_provisions');

            // scheduled but not invoiced
            $adjustments['schedule'][] = self::computeScheduledByRequestType($id, 'working_fund');
            $adjustments['schedule'][] = self::computeScheduledByRequestType($id, 'expense_provisions');

            foreach($adjustments as $adjustment_type => $descriptors) {

                foreach($descriptors as $fund_request) {
                    if(!$fund_request['id']) {
                        continue;
                    }

                    foreach($fund_request['request_executions'] as $execution_id => $property_lots) {
                        foreach($property_lots as $property_lot_id => $amount) {
                            OwnershipTransferAdjustmentLine::create([
                                'condo_id'              => $ownershipTransfer['condo_id'],
                                'ownership_transfer_id' => $id,
                                'fund_request_id'       => $fund_request['id'],
                                'request_execution_id'  => $execution_id,
                                'adjustment_type'       => $adjustment_type,
                                'amount'                => $amount,
                                'property_lot_id'       => $property_lot_id,
                                'ownership_id'          => $ownershipTransfer['old_ownership_id']
                            ]);
                        }
                    }
                }

            }

        }

    }

    /**
     * Retrieve the billed amounts to be reimbursed, by lot, for a given fund request type.
     * Expected request types are 'working_fund' and 'expense_provisions'.
     *
     * @param int $id Ownership transfer ID.
     * @param string $request_type The type of request to retrieve reimbursements for.
     */
    private static function computeReimbursementsByRequestType($id, $request_type) {
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

        // 2) retrieve the total allocated amounts for the lots, and retain the allocated amount by sold lot
        $fundRequestLineEntryLots = FundRequestLineEntryLot::search([
                ['fund_request_id', 'in', $fund_requests_ids],
                ['ownership_id', '=', $ownershipTransfer['old_ownership_id']]
            ])
            ->read(['allocated_amount', 'property_lot_id']);

        // total amount expected for fund calls regarding the old owner, for all its lots, for the whole fiscal year
        $total_allocated = 0.0;
        $map_sold_property_lots_total_allocated = array_fill_keys($ownershipTransfer['property_lots_ids'], 0.0);

        foreach($fundRequestLineEntryLots as $fundRequestLineEntryLot) {
            $total_allocated += $fundRequestLineEntryLot['allocated_amount'];

            // keep track of the amounts related to the sold property lots
            if(in_array($fundRequestLineEntryLot['property_lot_id'], $ownershipTransfer['property_lots_ids'])) {
                $map_sold_property_lots_total_allocated[$fundRequestLineEntryLot['property_lot_id']] += $fundRequestLineEntryLot['allocated_amount'];
            }
        }

        // 3) retrieve all relevant FundRequestExecution (sales invoices)
        $fundRequestExecutions = FundRequestExecution::search([
                ['condo_id', '=', $ownershipTransfer['condo_id']],
                ['status', '=', 'posted'],
                ['posting_date', '<', $ownershipTransfer['transfer_date']],
                ['fund_request_id', 'in', $fund_requests_ids]
            ])
            ->read(['posting_date', 'fund_request_id' => ['id', 'has_date_range', 'date_range_frequency', 'date_to']]);

        $map_sold_property_lots_total_reimburse = [];

        $fund_request_id = null;

        foreach($fundRequestExecutions as $fund_request_execution_id => $fundRequestExecution) {

            if(!$fund_request_id) {
                $fund_request_id = $fundRequestExecution['fund_request_id'];
            }

            // compute prorata for invoiced date range
            $prorata = 0;
            $fund_request_date_to = $fundRequestExecution['fund_request_id']['date_to'];
            if($fundRequestExecution['fund_request_id']['has_date_range']) {
                $frequency = $fundRequestExecution['fund_request_id']['date_range_frequency'];
                $fund_request_date_to = min($fund_request_date_to, strtotime("+$frequency months", $fundRequestExecution['posting_date']) - 86400);
            }

            $total_duration = $fund_request_date_to - $fundRequestExecution['posting_date'];
            $remaining_duration = $fund_request_date_to - $ownershipTransfer['transfer_date'];

            if($total_duration > 0 && $remaining_duration > 0) {
                $prorata = $remaining_duration / $total_duration;
            }

            $map_sold_property_lots_total_reimburse[$fund_request_execution_id] = array_fill_keys($ownershipTransfer['property_lots_ids'], 0.0);

            // 4) retrieve the breakdown of the total amounts called for the concerned ownership
            $fundRequestExecutionLines = FundRequestExecutionLine::search([
                    ['invoice_id', '=', $fund_request_execution_id],
                    ['ownership_id', '=', $ownershipTransfer['old_ownership_id']]
                ])
                ->read(['price']);

            // all that has been invoiced to the seller owner, based on lots that were in its name at the beginning of the fiscal year
            $total_invoiced = 0.0;
            foreach($fundRequestExecutionLines as $fundRequestExecutionLine) {
                $total_invoiced += $fundRequestExecutionLine['price'];
            }

            // 5) calculation of the amounts to be reimbursed, by sold lot
            foreach($map_sold_property_lots_total_allocated as $property_lot_id => $sold_property_lot_total_allocated) {
                $ratio = ($total_allocated > 0) ? ($sold_property_lot_total_allocated / $total_allocated) : 0.0;
                $map_sold_property_lots_total_reimburse[$fund_request_execution_id][$property_lot_id] = round($total_invoiced * $ratio * $prorata, 2);
            }
        }

        $result = [
            'id'                    => $fund_request_id,
            'request_executions'    => $map_sold_property_lots_total_reimburse
        ];

        return $result;
    }


    private static function computeScheduledByRequestType($id, $request_type) {
        // retrieve ownership transfer
        $ownershipTransfer = self::id($id)
            ->read([
                'condo_id',
                'transfer_date',
                'property_lots_ids',
                'old_ownership_id' => ['id']
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

        // 2) retrieve the total allocated amounts for the lots, and retain the allocated amount by sold lot
        $fundRequestLineEntryLots = FundRequestLineEntryLot::search([
                ['fund_request_id', 'in', $fund_requests_ids],
                ['ownership_id', '=', $ownershipTransfer['old_ownership_id']['id']]
            ])
            ->read(['allocated_amount', 'property_lot_id']);

        // total amount expected for fund calls regarding the old owner, for all its lots, for the whole fiscal year
        $total_allocated = 0.0;
        $map_sold_property_lots_total_allocated = array_fill_keys($ownershipTransfer['property_lots_ids'], 0.0);

        foreach($fundRequestLineEntryLots as $fundRequestLineEntryLot) {
            $total_allocated += $fundRequestLineEntryLot['allocated_amount'];

            // keep track of the amounts related to the sold property lots
            if(in_array($fundRequestLineEntryLot['property_lot_id'], $ownershipTransfer['property_lots_ids'])) {
                $map_sold_property_lots_total_allocated[$fundRequestLineEntryLot['property_lot_id']] += $fundRequestLineEntryLot['allocated_amount'];
            }
        }

        // 3) retrieve all relevant FundRequestExecution (sales invoices)
        // #memo - we need executions not yet posted (posted_date might be earlier than $ownershipTransfer['transfer_date'])
        $fundRequestExecutions = FundRequestExecution::search([
                ['condo_id', '=', $ownershipTransfer['condo_id']],
                ['status', '=', 'proforma'],
                ['fund_request_id', 'in', $fund_requests_ids]
            ])
            ->read(['fund_request_id']);

        $map_sold_property_lots_total_scheduled = [];
        $fund_request_id = null;

        foreach($fundRequestExecutions as $fund_request_execution_id => $fundRequestExecution) {

            if(!$fund_request_id) {
                $fund_request_id = $fundRequestExecution['fund_request_id'];
            }

            $map_sold_property_lots_total_scheduled[$fund_request_execution_id] = array_fill_keys($ownershipTransfer['property_lots_ids'], 0.0);

            // 4) retrieve the breakdown of the total amounts called for the concerned ownership
            $fundRequestExecutionLines = FundRequestExecutionLine::search([
                    ['invoice_id', '=', $fund_request_execution_id],
                    ['ownership_id', '=', $ownershipTransfer['old_ownership_id']['id']]
                ])
                ->read(['price']);

            // total invoiced to the seller owner for this execution, based on lots that were in its name at the beginning of the fiscal year
            $total_invoiced = 0.0;
            foreach($fundRequestExecutionLines as $fundRequestExecutionLine) {
                $total_invoiced += $fundRequestExecutionLine['price'];
            }

            // 5) calculation of the amounts to be reimbursed, by sold lot
            foreach($map_sold_property_lots_total_allocated as $property_lot_id => $sold_property_lot_total_allocated) {
                $ratio = ($total_allocated > 0) ? ($sold_property_lot_total_allocated / $total_allocated) : 0.0;
                $map_sold_property_lots_total_scheduled[$fund_request_execution_id][$property_lot_id] = round($total_invoiced * $ratio, 2);
            }

        }

        // map_sold_property_lots_total_scheduled contient, pour chaque fund request execution, le montant à déduire pour le owner vendeur, et à assigner pour le owner acheteur

        $result = [
            'id'                    => $fund_request_id,
            'request_executions'    => $map_sold_property_lots_total_scheduled
        ];

        return $result;
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
                if(!$values['old_ownership_id'] || !in_array($values['old_ownership_id'], $ownerships_ids) ) {
                    $result['old_ownership_id'] = [
                        'domain' => [['condo_id', '=', $values['condo_id']], ['id', 'in', $ownerships_ids]]
                    ];
                }
                else {
                    // $result['property_lots_ids'] = [];
                    // property_lot_id
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

        if(isset($event['identity_address_street'])) {
            $values['identity_address_street'] = $event['identity_address_street'];
        }

        if(isset($event['identity_address_zip'])) {
            $values['identity_address_zip'] = preg_replace('/[^A-Z0-9]/i', '', $event['identity_address_zip']);
        }

        if(isset($event['identity_address_zip']) && isset($values['identity_address_country'])) {
            $list = self::computeCitiesByZip($values['identity_address_zip'], $values['identity_address_country'], $lang);
            if($list && count($list)) {
                $result['identity_address_city'] = [
                    'value' => $list[0],
                    'selection' => $list
                ];
            }
        }

        if(isset($event['identity_bank_account_iban'])) {
            $result['identity_bank_account_iban'] = preg_replace('/[^A-Z0-9]/i', '', $event['identity_bank_account_iban']);
        }

        if(isset($event['identity_phone'])) {
            $result['identity_phone'] = preg_replace('/[^\d+]/', '', $event['identity_phone']);
        }

        if(isset($event['identity_citizen_identification'])) {
            $values['identity_citizen_identification'] = $event['identity_citizen_identification'];
        }

        if(isset($event['identity_email'])) {
            $result['identity_email'] = trim($event['identity_email']);
            $values['identity_email'] = $result['identity_email'];
        }

        if(isset($event['is_accepted_suggested_identity']) && $event['is_accepted_suggested_identity']) {
            if(isset($values['suggested_identity_uuid'])) {
                // #todo - temp
                $identity_id = $values['suggested_identity_uuid'];

                $identity = Identity::id($identity_id)
                    ->read([
                        'name', 'firstname', 'lastname', 'citizen_identification', 'email', 'phone', 'title', 'gender',
                        'address_street', 'address_dispatch', 'address_zip', 'address_city', 'address_country',
                        'bank_account_iban'
                    ])
                    ->first();

                $result = [
                        'identity_firstname'                 => $identity['firstname'],
                        'identity_lastname'                  => $identity['lastname'],
                        'identity_gender'                    => $identity['gender'],
                        'identity_title'                     => $identity['title'],
                        'identity_citizen_identification'    => $identity['citizen_identification'],
                        'identity_email'                     => $identity['email'],
                        'identity_phone'                     => $identity['phone'],
                        'identity_address_street'            => $identity['address_street'],
                        'identity_address_dispatch'          => $identity['address_dispatch'],
                        'identity_address_zip'               => $identity['address_zip'],
                        'identity_address_city'              => $identity['address_city'],
                        'identity_address_country'           => $identity['address_country'],
                        'identity_bank_account_iban'         => $identity['bank_account_iban']
                    ];
            }
        }

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
