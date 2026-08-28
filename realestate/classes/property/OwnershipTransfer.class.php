<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace realestate\property;

use communication\template\Template;
use equal\text\TextTransformer;
use fmt\setting\Setting;
use identity\Identity;
use finance\accounting\Account;
use finance\accounting\FiscalPeriod;
use finance\accounting\FiscalYear;
use finance\accounting\OpeningBalance;
use realestate\finance\accounting\CondoFund;
use realestate\funding\FundRequest;
use realestate\funding\FundRequestLineEntryLot;
use realestate\ownership\Ownership;
use realestate\sale\pay\Funding;

class OwnershipTransfer extends \equal\orm\Model {

    public function getTable() {
        return 'realestate_property_ownershiptransfer';
    }

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
                'visible'           => ['is_notary_request', '=', false],
                'onupdate'          => 'onupdateRequestContactEmail'
            ],

            'request_notary_office_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the property lot belongs to.",
                'foreign_object'    => 'realestate\property\NotaryOffice',
                'domain'            => ['supplier_type_code', '=', 'notary_office'],
                'visible'           => [['is_notary_request', '=', true] ],
                'onupdate'          => 'onupdateRequestNotaryOfficeId'
            ],

            'confirmation_notary_office_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the property lot belongs to.",
                'foreign_object'    => 'realestate\property\NotaryOffice',
                'domain'            => ['supplier_type_code', '=', 'notary_office'],
                'onupdate'          => 'onupdateConfirmationNotaryOfficeId'
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
                'help'              => "This date must match the notary deed date and is therefore known only at the end of the process."
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

            'fiscal_year_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'finance\accounting\FiscalYear',
                'description'       => "Fiscal year the transfer relates to.",
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null]],
                'help'              => "Fiscal Year is automatically assigned based on provided dates. Ultimately the transfer_date is predominant.",
                'function'          => 'calcFiscalYearId',
                'store'             => true,
                'instant'           => true
            ],

            'fiscal_period_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'finance\accounting\FiscalPeriod',
                'description'       => "Period of the fiscal year the transfer relates to.",
                'help'              => "Period is automatically assigned based on provided dates. Ultimately the transfer_date is predominant.",
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null], ['fiscal_year_id', '=', 'object.fiscal_year_id']],
                'function'          => 'calcFiscalPeriodId',
                'store'             => true,
                'instant'           => true
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
                'domain'            => [
                    ['condo_id', '=', 'object.condo_id'], ['active_ownership_id', '=', 'object.old_ownership_id']
                ]
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

            'ownership_transfer_settlement_id' => [
                'type'           => 'many2one',
                'foreign_object' => 'realestate\property\transfer\OwnershipTransferSettlement',
                'description'    => 'Accounting settlement linked to the ownership transfer.',
                'domain'         => ['ownership_transfer_id', '=', 'object.id'],
                'readonly'       => true,
                'ondelete'       => 'null'
            ],

            'condo_shares' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'function'          => 'calcCondoShares',
                'description'       => "The total statutory shares of the involved condominium."
            ],

            'ownership_shares' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'function'          => 'calcOwnershipShares',
                'description'       => "The total statutory shares implied by the ownership transfer."
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
                'usage'             => 'text/html.small',
                'description'       => "Short description of the current balances of the condominium funds.",
                'help'              => "As per 3.94.1.1"
            ],

            'has_seller_arrears_1' => [
                'type'              => 'boolean',
                'description'       => "Are there any pending arrears owed by the seller?",
                'default'           => true
            ],

            'arrears_amount_1' => [
                'type'              => 'float',
                'usage'             => 'amount/money:2',
                'description'       => "The total pending arrears owed by the seller."
            ],

            'arrear_lines_1_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\property\OwnershipTransferArrearLine',
                'foreign_field'     => 'ownership_transfer_id',
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['arrear_paragraph', '=', '1']],
                'description'       => 'Balances of the condominium funds with property lots shares.'
            ],

            'seller_arrears_description_1' => [
                'type'              => 'string',
                'usage'             => 'text/html.small',
                'description'       => "Short description of the current procedures, along with involved amounts.",
                'help'              => "As per 3.94.1.2"
            ],

            'has_seller_arrears_2' => [
                'type'              => 'boolean',
                'description'       => "Are there any pending arrears owed by the seller?",
                'default'           => true
            ],

            'arrears_amount_2' => [
                'type'              => 'float',
                'usage'             => 'amount/money:2',
                'description'       => "The total pending arrears owed by the seller."
            ],

            'arrear_lines_2_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\property\OwnershipTransferArrearLine',
                'foreign_field'     => 'ownership_transfer_id',
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['arrear_paragraph', '=', '2']],
                'description'       => 'Balances of the condominium funds with property lots shares.'
            ],

            'seller_arrears_description_2' => [
                'type'              => 'string',
                'usage'             => 'text/html.small',
                'description'       => "Short description of the current procedures, along with involved amounts.",
                'help'              => "As per 3.94.2.2"
            ],

            'scheduled_fund_requests_description' => [
                'type'              => 'string',
                'usage'             => 'text/html.small',
                'description'       => "Short description of the current procedures, along with involved amounts.",
                'help'              => "As per 3.94.1.3"
            ],

            'has_judiciary_procedures' => [
                'type'              => 'boolean',
                'description'       => "Are there any pending judiciary procedures affecting the condominium?",
                'default'           => true
            ],

            'judiciary_procedures_description' => [
                'type'              => 'string',
                'usage'             => 'text/html.small',
                'description'       => "Short description of the current procedures, along with involved amounts.",
                'help'              => "As per 3.94.1.4"
            ],

            'general_assembly_minutes_description' => [
                'type'              => 'string',
                'usage'             => 'text/html.small',
                'description'       => "Short text introducing the General Assembly minutes.",
                'help'              => "As per 3.94.1.5"
            ],

            'latest_balance_sheet_description' => [
                'type'              => 'string',
                'usage'             => 'text/html.small',
                'description'       => "Short text introducting the latest balance sheet.",
                'help'              => "As per 3.94.1.6"
            ],

            'with_both_paragraphs' => [
                'type'              => 'boolean',
                'description'       => "Send a correspondence holding both paragraphs",
                'default'           => false,
                'help'              => "Optional request from the buyer notary office."
            ],

            'maintenance_expenses_description' => [
                'type'              => 'string',
                'usage'             => 'text/html.small',
                'description'       => "Short description of the current maintenance expenses.",
                'help'              => "As per 3.94.2.1"
            ],

            'fund_requests_description' => [
                'type'              => 'string',
                'usage'             => 'text/html.small',
                'description'       => "Short description of the current fund requests.",
                'help'              => "As per 3.94.2.2"
            ],

            'commons_acquisitions_description' => [
                'type'              => 'string',
                'usage'             => 'text/html.small',
                'description'       => "Short description of the current common acquisitions.",
                'help'              => "As per 3.94.2.3"
            ],

            'condominium_debts_description' => [
                'type'              => 'string',
                'usage'             => 'text/html.small',
                'description'       => "Short description of the current condominium debts.",
                'help'              => "As per 3.94.2.4"
            ],

            'with_additional_info_1' => [
                'type'              => 'boolean',
                'description'       => "Should additional info be added to courier §1?",
                'default'           => true
            ],

            'with_additional_info_2' => [
                'type'              => 'boolean',
                'description'       => "Should additional info be added to courier §2?",
                'default'           => true
            ],

            // additional information that might be requested by the notary office

            'has_fuel_tank' => [
                'type'              => 'boolean',
                'description'       => "Does the condominium have a fuel tank?",
                'default'           => false
            ],

            'fuel_tank_capacity' => [
                'type'              => 'integer',
                'description'       => "Capacity of the fuel tank (in liters)",
                'visible'           => ['has_fuel_tank', '=', true]
            ],

            'has_intervention_record' => [
                'type'              => 'boolean',
                'description'       => "Does the Condominium have a future intervention record?",
                'default'           => false
            ],

            'has_bank_loan' => [
                'type'              => 'boolean',
                'description'       => "Does the condominium have an active bank loan?",
                'default'           => false
            ],

            'bank_loan_lines_ids' => [
                'type'              => 'one2many',
                'description'       => 'Bank loans subscribed by the condominium and allocated to transferred property lots.',
                'foreign_object'    => 'realestate\property\OwnershipTransferBankLoanLine',
                'foreign_field'     => 'ownership_transfer_id',
                'domain'            => ['condo_id', '=', 'object.condo_id'],
                'visible'           => ['has_bank_loan', '=', true]
            ],

            'bank_loan_description' => [
                'type'              => 'string',
                'usage'             => 'text/html.small',
                'description'       => "Short description about ba&nk loan(s), if any."
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

            'mails_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'core\Mail',
                'foreign_field'     => 'object_id',
                'domain'            => ['object_class', '=', 'realestate\property\OwnershipTransfer'],
                'description'       => 'List of emails sent in the context of the transfer.'
            ],

            'history_entries_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\property\OwnershipTransferHistoryEntry',
                'foreign_field'     => 'ownership_transfer_id',
                'description'       => 'History of emails sent in the context of the transfer.',
                'readonly'          => true
            ],

            'document_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'documents\Document',
                'description'       => 'EDMS Document linked to  the Ownership transfer.'
            ],

            'attached_documents_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'documents\Document',
                'foreign_field'     => 'ownership_transfers_ids',
                'rel_table'         => 'realestate_property_ownershiptransferattachment',
                'rel_foreign_key'   => 'document_id',
                'rel_local_key'     => 'ownership_transfer_id',
                'domain'            => ['condo_id', '=', 'object.condo_id'],
                'description'       => 'Selected documents as attachment.'
            ],

            'ownership_transfer_attachments_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\property\OwnershipTransferAttachment',
                'foreign_field'     => 'ownership_transfer_id',
                'domain'            => ['condo_id', '=', 'object.condo_id'],
                'description'       => 'Selected documents as attachment.',
                'help'              => 'Resulting list of all Attachments, whatever the section'
            ],

            'ownership_transfer_attachments_all_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\property\OwnershipTransferAttachment',
                'foreign_field'     => 'ownership_transfer_id',
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['attachment_section', '=', 'all']],
                'description'       => 'Selected documents as attachment.'
            ],

            'ownership_transfer_attachments_expense_statement_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\property\OwnershipTransferAttachmentExpenseStatement',
                'foreign_field'     => 'ownership_transfer_id',
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['attachment_section', '=', 'expense_statement']],
                'description'       => 'Selected documents as attachment.'
            ],

            'ownership_transfer_attachments_balance_sheet_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\property\OwnershipTransferAttachmentBalanceSheet',
                'foreign_field'     => 'ownership_transfer_id',
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['attachment_section', '=', 'balance_sheet']],
                'description'       => 'Selected documents as attachment.'
            ],

            'ownership_transfer_attachments_general_assembly_minutes_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\property\OwnershipTransferAttachmentGeneralAssemblyMinutes',
                'foreign_field'     => 'ownership_transfer_id',
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['attachment_section', '=', 'general_assembly_minutes']],
                'description'       => 'Selected documents as attachment.'
            ],

            'contacts_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\property\OwnershipTransferContact',
                'foreign_field'     => 'ownership_transfer_id',
                'domain'            => ['condo_id', '=', 'object.condo_id'],
                'description'       => 'Arbitrary list of contact recipients with email addresses only.',
            ],

            'status' => [
                'type'              => 'string',
                'selection'         => [
                    'pending',                      // draft
                    'open',                         // in progress : §1
                    'seller_documents_sent',        // in progress, doc sent : §1
                    'confirmed',                    // request from the notary office of the buyer : §2
                    'financial_statement_sent',     // request from the notary office of the buyer : §2
                    'settled',                      // sale was made, receipt from notary office : §3
                    'closed'                        // cas is closed (§3)
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
                        'description'   => 'Update the document to `open`.',
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
                        'onafter' => 'onafterSend',
                        'status' => 'seller_documents_sent',
                    ],
                    'confirm' => [
                        'description' => 'Update the document to `confirmed`.',
                        'onafter' => 'onafterConfirm',
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
                        'onafter' => 'onafterConfirm',
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
                        'status' => 'settled',
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
                        'description' => 'Close the ownership transfer after settlement completion.',
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

    protected static function onupdateRequestNotaryOfficeId($self) {
        $self->read(['condo_id', 'request_notary_office_id' => ['name', 'email']]);
        foreach($self as $id => $ownershipTransfer) {
            if($ownershipTransfer['request_notary_office_id']) {
                $email = $ownershipTransfer['request_notary_office_id']['email'];
                if(!$email || strlen($email) <= 0) {
                    continue;
                }
                // check if email is present amongst current contacts
                $contacts_ids = OwnershipTransferContact::search([ ['ownership_transfer_id', '=', $id], ['email', '=', $email] ])->ids();
                if(!count($contacts_ids)) {
                    OwnershipTransferContact::create([
                        'condo_id'              => $ownershipTransfer['condo_id'],
                        'ownership_transfer_id' => $id,
                        'name'                  => $ownershipTransfer['request_notary_office_id']['name'],
                        'email'                 => $ownershipTransfer['request_notary_office_id']['email']
                    ]);
                }
                self::id($id)->update(['confirmation_notary_office_id' => $ownershipTransfer['request_notary_office_id']['id']]);
            }
        }
    }

    protected static function onupdateConfirmationNotaryOfficeId($self) {
        $self->read(['condo_id', 'confirmation_notary_office_id' => ['name', 'email']]);
        foreach($self as $id => $ownershipTransfer) {
            if($ownershipTransfer['confirmation_notary_office_id']) {
                $email = $ownershipTransfer['confirmation_notary_office_id']['email'];
                if(!$email || strlen($email) <= 0) {
                    continue;
                }
                // check if email is present amongst current contacts
                $contacts_ids = OwnershipTransferContact::search([ ['ownership_transfer_id', '=', $id], ['email', '=', $email] ])->ids();
                if(!count($contacts_ids)) {
                    OwnershipTransferContact::create([
                        'condo_id'              => $ownershipTransfer['condo_id'],
                        'ownership_transfer_id' => $id,
                        'name'                  => $ownershipTransfer['confirmation_notary_office_id']['name'],
                        'email'                 => $ownershipTransfer['confirmation_notary_office_id']['email']
                    ]);
                }
            }
        }
    }

    protected static function onupdateRequestContactEmail($self) {
        $self->read(['condo_id', 'request_contact_email', 'request_contact_name']);
        foreach($self as $id => $ownershipTransfer) {
            if($ownershipTransfer['request_contact_email'] && strlen($ownershipTransfer['request_contact_email']) > 0)  {
                $email = $ownershipTransfer['request_contact_email'];
                // check if email is present amongst current contacts
                $contacts_ids = OwnershipTransferContact::search([ ['ownership_transfer_id', '=', $id], ['email', '=', $email] ])->ids();
                if(!count($contacts_ids)) {
                    OwnershipTransferContact::create([
                        'condo_id'              => $ownershipTransfer['condo_id'],
                        'ownership_transfer_id' => $id,
                        'name'                  => $ownershipTransfer['request_contact_name'],
                        'email'                 => $ownershipTransfer['request_contact_email']
                    ]);
                }
            }
        }
    }

    protected static function onafterOpen($self) {
        // 3.94.1
        $fund_balances_description = '';
        $scheduled_fund_requests_description = '';
        $judiciary_procedures_description = '';
        $general_assembly_minutes_description = '';
        $latest_balance_sheet_description = '';
        // 3.94.2
        $maintenance_expenses_description = '';
        $fund_requests_description = '';
        $commons_acquisitions_description = '';
        $condominium_debts_description = '';

        // additional
        $bank_loan_description = '';

        $templates = Template::search([
                ['type', '=', 'document'],
                ['code', 'in', ['ownership_transfer_paragraph_1', 'ownership_transfer_paragraph_2']]
            ])
            ->read( ['id','parts_ids' => ['name', 'value']]);

        foreach($templates as $template_id => $template) {
            foreach($template['parts_ids'] as $part_id => $part) {
                if($part['name'] === 'fund_balances_description') {
                    $fund_balances_description = $part['value'];
                }
                elseif($part['name'] === 'scheduled_fund_requests_description') {
                    $scheduled_fund_requests_description = $part['value'];
                }
                elseif($part['name'] === 'judiciary_procedures_description') {
                    $judiciary_procedures_description = $part['value'];
                }
                elseif($part['name'] === 'general_assembly_minutes_description') {
                    $general_assembly_minutes_description = $part['value'];
                }
                elseif($part['name'] === 'latest_balance_sheet_description') {
                    $latest_balance_sheet_description = $part['value'];
                }
                elseif($part['name'] === 'maintenance_expenses_description') {
                    $maintenance_expenses_description = $part['value'];
                }
                elseif($part['name'] === 'fund_requests_description') {
                    $fund_requests_description = $part['value'];
                }
                elseif($part['name'] === 'commons_acquisitions_description') {
                    $commons_acquisitions_description = $part['value'];
                }
                elseif($part['name'] === 'condominium_debts_description') {
                    $condominium_debts_description = $part['value'];
                }
                elseif($part['name'] === 'bank_loan_description') {
                    $bank_loan_description = $part['value'];
                }
            }
        }

        $self->update([
            // 3.94.1.1
            'fund_balances_description'             => $fund_balances_description,
            // #memo - set based on actual arrears
            // 'seller_arrears_description'         => "Le montant à ce jour des arriérés dus par le cédant à la copropriété;",
            // 3.94.1.3
            'scheduled_fund_requests_description'   => $scheduled_fund_requests_description,
            // 3.94.1.4
            'judiciary_procedures_description'      => $judiciary_procedures_description,
            // 3.94.1.5
            'general_assembly_minutes_description'  => $general_assembly_minutes_description,
            // 3.94.1.6
            'latest_balance_sheet_description'      => $latest_balance_sheet_description,
            // 3.94.2.1
            'maintenance_expenses_description'      => $maintenance_expenses_description,
            // 3.94.2.2
            'fund_requests_description'             => $fund_requests_description,
            // 3.94.2.3
            'commons_acquisitions_description'      => $commons_acquisitions_description,
            // 3.94.2.4
            'condominium_debts_description'         => $condominium_debts_description,
            // additional
            'bank_loan_description'                 => $bank_loan_description
        ]);

        $self
            ->do('refresh')
            ->do('check-transfer-in-progress');
    }

    protected static function onafterSend($self) {
        $self->do('check-transfer-in-progress');
    }

    protected static function doCheckTransferInProgress($self) {
        $self->read(['condo_id']);

        foreach($self as $ownershipTransfer) {
            \eQual::run('do', 'realestate_funding_check-transfer-in-progress', [
                'condo_id' => $ownershipTransfer['condo_id']
            ]);
        }
    }

    protected static function doRefresh($self) {
        $self
            ->do('refresh_fund_balance_lines')
            ->do('refresh_fund_request_lines')
            ->do('refresh_arrears');
    }

    protected static function onafterConfirm($self) {
        $self
            ->do('refresh_arrears');
    }

    public static function getPolicies(): array {
        return [
            'is_valid' => [
                'description' => 'Verifies that the mandatory values are present for Condominium validation.',
                'function'    => 'policyIsValid'
            ]
        ];
    }

    public static function getActions() {
        return array_merge(parent::getActions(), [
            'refresh_fund_balance_lines' => [
                'description'   => 'Generate the table of condo funds balances.',
                'policies'      => [],
                'function'      => 'doRefreshFundBalanceLines'
            ],
            'refresh_fund_request_lines' => [
                'description'   => 'Generate the table of condo funds requests.',
                'policies'      => [],
                'function'      => 'doRefreshFundRequestLines'
            ],
            'refresh_arrears' => [
                'description'   => 'Refresh values related to arrears owed by the seller.',
                'policies'      => [],
                'function'      => 'doRefreshArrears'
            ],
            'refresh' => [
                'description'   => 'Refresh all values related to arrears and shares.',
                'policies'      => [],
                'function'      => 'doRefresh'
            ],
            'check-transfer-in-progress' => [
                'description'   => 'Refresh all values related to arrears and shares.',
                'policies'      => [],
                'function'      => 'doCheckTransferInProgress'
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

    protected static function calcFiscalYearId($self) {
        $result = [];

        $self->read(['condo_id', 'request_date', 'confirmation_date', 'transfer_date', 'status']);

        foreach($self as $id => $ownershipTransfer) {
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

            if(!$date) {
                continue;
            }

            // retrieve FiscalYear
            $fiscalYear = FiscalYear::search([
                    ['condo_id', '=', $ownershipTransfer['condo_id']],
                    ['date_from', '<=', $date],
                    ['date_to', '>=', $date],
                ])
                ->first();

            if(!$fiscalYear) {
                continue;
            }

            $result[$id] = $fiscalYear['id'];
        }
        return $result;
    }

    protected static function calcFiscalPeriodId($self) {
        $result = [];
        $self->read(['request_date', 'confirmation_date', 'transfer_date', 'status', 'fiscal_year_id' => ['fiscal_periods_ids' => ['date_from', 'date_to']]]);
        foreach($self as $id => $ownershipTransfer) {
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

            if(!$date) {
                continue;
            }

            foreach($ownershipTransfer['fiscal_year_id']['fiscal_periods_ids'] ?? [] as $period_id => $period) {
                if($date >= $period['date_from'] && $date <= $period['date_to']) {
                    $result[$id] = $period_id;
                    break;
                }
            }
        }
        return $result;
    }


    protected static function doRefreshArrears($self) {
        $self->read(['status', 'condo_id', 'old_ownership_id']);
        $now = strtotime('today');

        foreach($self as $id => $ownershipTransfer) {

            $paragraph = (in_array($ownershipTransfer['status'], ['pending', 'open', 'seller_documents_sent'], true)) ? '1' : '2';

            $arrears_amount = 0.0;

            $fiscalYear = FiscalYear::search([
                        ['condo_id', '=', $ownershipTransfer['condo_id']],
                        ['date_from', '<=', $now],
                        ['status', '=', 'open']
                    ],
                    ['sort' => ['date_from' => 'desc'], 'limit' => 1
                ])
                ->read(['date_from'])
                ->first();

            if(!$fiscalYear) {
                continue;
            }

            $date_from = $fiscalYear['date_from'];

            if($paragraph === '1') {

                OwnershipTransferArrearLine::search([
                        ['ownership_transfer_id', '=', $id],
                        ['arrear_paragraph', '=', $paragraph]
                    ])
                    ->delete(true);

                $data = \eQual::run('get', 'finance_accounting_ownerAccountStatement_collect', [
                    'ownership_id' => $ownershipTransfer['old_ownership_id'],
                    'date_from'    => $date_from,
                    'date_to'      => time()
                ]);

                if(count($data)) {
                    $arrears_amount = end($data)['balance'] ?? 0;
                }

                $arrearFundings = Funding::search([
                        ['condo_id', '=', $ownershipTransfer['condo_id']],
                        ['is_paid', '=', false],
                        ['ownership_id', '=', $ownershipTransfer['old_ownership_id']]
                    ])
                    ->read(['due_date', 'name', 'funding_type', 'remaining_amount']);

                if($arrearFundings->count() > 0) {
                    foreach($arrearFundings as $funding_id => $funding) {
                        OwnershipTransferArrearLine::create([
                            'condo_id'              => $ownershipTransfer['condo_id'],
                            'ownership_transfer_id' => $id,
                            'due_date'              => $funding['due_date'],
                            'description'           => $funding['name'],
                            'arrear_paragraph'      => $paragraph,
                            'arrear_line_type'      => 'funding',
                            'due_amount'            => $funding['remaining_amount'],
                        ]);
                    }
                }
                else {
                    OwnershipTransferArrearLine::create([
                        'condo_id'              => $ownershipTransfer['condo_id'],
                        'ownership_transfer_id' => $id,
                        'description'           => 'arriérés',
                        'arrear_paragraph'      => $paragraph,
                        'arrear_line_type'      => 'funding',
                        'due_amount'            => 0.0
                    ]);
                }
            }
            elseif($paragraph === '2') {
                OwnershipTransferArrearLine::search([['ownership_transfer_id', '=', $id], ['arrear_paragraph', '=', $paragraph]])->delete(true);

                $data = \eQual::run('get', 'finance_accounting_ownerAccountStatement_collect', [
                    'ownership_id' => $ownershipTransfer['old_ownership_id'],
                    'date_from'    => $date_from,
                    'date_to'      => time()
                ]);

                if(count($data)) {
                    $arrears_amount = end($data)['balance'] ?? 0;
                }

                $arrearFundings = Funding::search([
                        ['condo_id', '=', $ownershipTransfer['condo_id']],
                        ['is_paid', '=', false],
                        ['ownership_id', '=', $ownershipTransfer['old_ownership_id']]
                    ])
                    ->read(['due_date', 'name', 'funding_type', 'remaining_amount']);

                if($arrearFundings->count() > 0) {
                    foreach($arrearFundings as $funding_id => $funding) {
                        OwnershipTransferArrearLine::create([
                            'condo_id'              => $ownershipTransfer['condo_id'],
                            'ownership_transfer_id' => $id,
                            'due_date'              => $funding['due_date'],
                            'description'           => $funding['name'],
                            'arrear_paragraph'      => $paragraph,
                            'arrear_line_type'      => 'funding',
                            'due_amount'            => $funding['remaining_amount'],
                        ]);
                    }
                }
                else {
                    OwnershipTransferArrearLine::create([
                        'condo_id'              => $ownershipTransfer['condo_id'],
                        'ownership_transfer_id' => $id,
                        'description'           => 'arriérés',
                        'arrear_paragraph'      => $paragraph,
                        'arrear_line_type'      => 'funding',
                        'due_amount'            => 0.0
                    ]);
                }

                OwnershipTransferArrearLine::create([
                    'condo_id'              => $ownershipTransfer['condo_id'],
                    'ownership_transfer_id' => $id,
                    'description'           => 'Provisions complémentaires (3.95)',
                    'arrear_paragraph'      => $paragraph,
                    'arrear_line_type'      => 'additional_provision',
                    'due_amount'            => 0.0,
                ]);

                OwnershipTransferArrearLine::create([
                    'condo_id'              => $ownershipTransfer['condo_id'],
                    'ownership_transfer_id' => $id,
                    'description'           => 'Frais de dossier (3.94 §4)',
                    'arrear_paragraph'      => $paragraph,
                    'arrear_line_type'      => 'processing_fee',
                    'due_amount'            => 0.0,
                ]);
            }
            else {
                continue;
            }

            $values = [
                'arrears_amount_' . $paragraph => $arrears_amount
            ];

            $domain_template = [
                ['type', '=', 'document'],
                ['code', '=', 'ownership_transfer_paragraph_' . $paragraph]
            ];

            $template = Template::search($domain_template)
                ->read( ['id','parts_ids' => ['name', 'value']])
                ->first(true);

            if(round($arrears_amount, 2) >= 0.01) {
                $values['has_seller_arrears_' . $paragraph] = true;

                foreach($template['parts_ids'] as $part_id => $part) {
                    if($part['name'] == 'seller_arrears_some_description') {
                        $text = $part['value'];

                        $map_values = [
                            'amount' => $arrears_amount,
                        ];

                        // Replace {var} items with corresponding values, set in $map_values
                        $text = preg_replace_callback('/\{(\w+)\}/', function ($matches) use ($map_values) {
                            $key = $matches[1];
                            return $map_values[$key] ?? '';
                        }, $text);

                        $values['seller_arrears_description_' . $paragraph] = $text;
                        break;
                    }
                }
            }
            else {
                $values['has_seller_arrears_' . $paragraph] = false;

                foreach($template['parts_ids'] as $part_id => $part) {
                    if($part['name'] == 'seller_arrears_none_description') {
                        $text = $part['value'];

                        $values['seller_arrears_description_' . $paragraph] = $text;
                        break;
                    }
                }
            }

            self::id($id)->update($values);
        }
    }

    protected static function doRefreshFundRequestLines($self) {
        $self->read(['condo_id', 'fiscal_year_id']);
        foreach($self as $id => $ownershipTransfer) {
            OwnershipTransferFundRequestLine::search([
                    ['ownership_transfer_id', '=', $id]
                ])
                ->delete(true);

            // retrieve fund requests
            $fund_requests_ids = FundRequest::search([['condo_id', '=', $ownershipTransfer['condo_id']], ['fiscal_year_id', '=', $ownershipTransfer['fiscal_year_id']]])->ids();
            foreach($fund_requests_ids as $fund_request_id) {
                // #memo - most fields are computed
                OwnershipTransferFundRequestLine::create([
                        'condo_id'              => $ownershipTransfer['condo_id'],
                        'ownership_transfer_id' => $id,
                        'fund_request_id'       => $fund_request_id
                    ]);
            }
        }
    }

    protected static function doRefreshFundBalanceLines($self) {
        $self->read(['condo_id', 'fiscal_year_id', 'request_date', 'confirmation_date', 'transfer_date', 'status']);
        foreach($self as $id => $ownershipTransfer) {
            if(!$ownershipTransfer['condo_id']) {
                continue;
            }

            if(!$ownershipTransfer['fiscal_year_id']) {
                continue;
            }

            OwnershipTransferFundBalanceLine::search([
                    ['ownership_transfer_id', '=', $id]
                ])
                ->delete(true);

            // retrieve latest date to take under account
            // #memo - table must always hold the most up to date info
            $date = time();

            /*
            if(in_array($ownershipTransfer['status'], ['pending', 'open', 'seller_documents_sent'], true)) {
                $date = $ownershipTransfer['request_date'];
            }
            elseif(in_array($ownershipTransfer['status'], ['confirmed', 'financial_statement_sent'], true)) {
                $date = $ownershipTransfer['confirmation_date'];
            }
            else {
                $date = $ownershipTransfer['transfer_date'];
            }
            */

            // retrieve pivot date
            /*
            $fiscalYear = FiscalYear::id($ownershipTransfer['fiscal_year_id'])->read(['status', 'date_from'])->first();
            if($fiscalYear['status'] !== 'open' && $fiscalYear['status'] !== 'preclosed') {
                // current year might not hold some accounting entries: we must retrieve the first date of the latest fully open fiscal year
                $canditatefiscalYear = FiscalYear::search([
                            [['condo_id', '=', $ownershipTransfer['condo_id']], ['status', '=', 'open']],
                            [['condo_id', '=', $ownershipTransfer['condo_id']], ['status', '=', 'preclosed']],
                        ],
                        ['sort' => ['date_from' => 'desc'], 'limit' => 1]
                    )
                    ->read(['date_from'])
                    ->first();
                if($canditatefiscalYear) {
                    $fiscalYear = $canditatefiscalYear;
                }
            }
            $pivot_date = $fiscalYear['date_from'];
            */
            $openingBalance = OpeningBalance::search(['condo_id', '=', $ownershipTransfer['condo_id']], ['sort' => ['created' => 'desc'], 'limit' => 1])
                ->read(['fiscal_year_id' => ['date_from']])
                ->first();

            $pivot_date = $openingBalance['fiscal_year_id']['date_from'];

            // retrieve all funds
            $condoFunds = CondoFund::search(['condo_id', '=', $ownershipTransfer['condo_id']])
                ->read(['name', 'fund_type', 'fund_account_id']);

            // retrieve all accounting entries to consider, between pivot_date and date
            foreach($condoFunds as $condo_fund_id => $condoFund) {
                if(!$condoFund['fund_account_id']) {
                    continue;
                }
                $rows = \eQual::run('get', 'finance_accounting_generalBalance_collect', [
                        'condo_id'    => $ownershipTransfer['condo_id'],
                        'date_from'   => $pivot_date,
                        'date_to'     => $date,
                        'account_id'  => $condoFund['fund_account_id']
                    ], true);

                if(count($rows)) {
                    $last = end($rows);

                    OwnershipTransferFundBalanceLine::create([
                        'condo_id'              => $ownershipTransfer['condo_id'],
                        'ownership_transfer_id' => $id,
                        'condo_fund_id'         => $condo_fund_id,
                        'condo_fund_balance'    => -$last['balance'] ?? 0.0
                    ]);
                }
            }

        }
    }

    protected static function calcCondoShares($self) {
        $result = [];

        $self->read(['condo_id']);

        foreach($self as $id => $ownershipTransfer) {
            $apportionment = Apportionment::search([
                    ['condo_id', '=', $ownershipTransfer['condo_id']],
                    ['is_statutory', '=', true],
                ])
                ->read(['total_shares'])
                ->first();

            if(!$apportionment) {
                continue;
            }
            $result[$id] = $apportionment['total_shares'];
        }

        return $result;
    }

    protected static function calcOwnershipShares($self) {
        $result = [];

        $self->read(['condo_id', 'property_lots_ids']);

        foreach($self as $id => $ownershipTransfer) {
            $apportionment = Apportionment::search([
                    ['condo_id', '=', $ownershipTransfer['condo_id']],
                    ['is_statutory', '=', true],
                ])
                ->first();

            if(!$apportionment) {
                continue;
            }

            $result[$id] = 0;

            $apportionmentShares = PropertyLotApportionmentShare::search([
                    [ 'property_lot_id', 'in', $ownershipTransfer['property_lots_ids'] ],
                    [ 'apportionment_id', '=', $apportionment['id']]
                ])
                ->read(['property_lot_shares']);

            foreach($apportionmentShares as $apportionmentShare) {
                $result[$id] += $apportionmentShare['property_lot_shares'];
            }
        }

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

        if(array_key_exists('condo_id', $event)) {
            // upon change on condo_id, reset all fields
            $result['property_lots_ids'] = [];
            $result['old_ownership_id'] = null;
            $result['property_lot_id'] = null;

            if($event['condo_id']) {
                $result['old_ownership_id'] = [
                    'domain' => ['condo_id', '=', $event['condo_id']]
                ];
                $result['property_lot_id'] = [
                    'visible' => true,
                    'domain' => ['condo_id', '=', $event['condo_id']]
                ];
            }
        }

        // synchronize ownership & property lots
        // #memo - we must be able to assign any ownership (not only active ones)
        if(array_key_exists('old_ownership_id', $event)) {
            $result['property_lots_ids'] = [
                'value' => []
            ];

            if($event['old_ownership_id']) {

                $result['property_lot_id'] = [
                    'visible' => false,
                    'domain' => [
                        ['active_ownership_id', '=', $event['old_ownership_id']]
                    ]
                ];

                $result['property_lots_ids'] = [
                    'value' => [],
                    'domain' => [
                        ['active_ownership_id', '=', $event['old_ownership_id']]
                    ]
                ];

                /*
                $propertyOwnerships = PropertyLotOwnership::search([['ownership_id', '=', $event['old_ownership_id']]])->read(['property_lot_id'])->get(true);
                $property_lots_ids = array_map(function ($a) {return $a['property_lot_id'];}, $propertyOwnerships);
                if(!$values['property_lot_id'] || !in_array($values['property_lot_id'], $property_lots_ids) ) {
                    $result['property_lot_id'] = [
                        'domain' => [['condo_id', '=', $values['condo_id']], ['id', 'in', $property_lots_ids]]
                    ];
                }
                $result['property_lots_ids'] = $property_lots_ids;
                */
            }
            else {
                $result['old_ownership_id'] = [
                    'domain' => ['condo_id', '=', $values['condo_id']]
                ];
                $result['property_lot_id'] = [
                    'visible' => true,
                    'domain' => ['condo_id', '=', $values['condo_id']]
                ];
            }
        }

        if(array_key_exists('property_lot_id', $event)) {
            if($event['property_lot_id']) {
                $propertyOwnerships = PropertyLotOwnership::search([['property_lot_id', '=', $event['property_lot_id']]])->read(['ownership_id'])->get(true);
                $ownerships_ids = array_map(function ($a) {return $a['ownership_id'];}, $propertyOwnerships);
                if(!isset($values['old_ownership_id']) || !in_array($values['old_ownership_id'], $ownerships_ids)) {
                    if(count($ownerships_ids) === 1) {
                        $ownership_id = reset($ownerships_ids);
                        $ownership = Ownership::id($ownership_id)->read(['id', 'name'])->first();
                        $result['old_ownership_id'] = [
                            'id'    => $ownership['id'],
                            'name'  => $ownership['name']
                        ];
                        $result['property_lot_id'] = [
                            'visible'  => false
                        ];
                        $result['property_lots_ids'] = [
                            'value' => [$event['property_lot_id']],
                            'domain' => [
                                ['active_ownership_id', '=', $ownership_id]
                            ]
                        ];
                    }
                    else {
                        $result['old_ownership_id'] = [
                            'domain' => [['condo_id', '=', $values['condo_id']], ['id', 'in', $ownerships_ids]]
                        ];
                    }
                }
            }
            else {
                /*
                $result['old_ownership_id'] = [
                    'domain' => ['condo_id', '=', $values['condo_id']]
                ];
                */
                $result['property_lot_id'] = [
                    'domain' => [
                        ['condo_id', '=', $values['condo_id']],
                        ['active_ownership_id', '=', $values['old_ownership_id']]
                    ]
                ];
            }
        }

        return $result;
    }


}
