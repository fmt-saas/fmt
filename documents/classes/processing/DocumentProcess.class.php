<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace documents\processing;
use equal\orm\Model;
use documents\Document;
use documents\DocumentType;
use documents\recording\RecordingRule;
use documents\recording\RecordingRuleLine;
use finance\bank\Bank;
use finance\bank\BankAccount;
use finance\bank\BankStatement;
use finance\bank\BankStatementLine;
use finance\bank\CondominiumBankAccount;
use finance\bank\SuppliershipBankAccount;
use hr\role\Role;
use hr\role\RoleAssignment;
use purchase\supplier\Supplier;
use purchase\supplier\Suppliership;
use purchase\supplier\SuppliershipReference;
use realestate\property\Condominium;
use realestate\purchase\accounting\invoice\PurchaseInvoice;
use realestate\purchase\accounting\invoice\PurchaseInvoiceLine;

class DocumentProcess extends Model {

    public static function getName() {
        return "Document Process";
    }

    public static function getDescription() {
        return "A Document Process keeps info about the processing of a single document and the result of each step.";
    }

    public static function getColumns() {

        return [
            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the document belongs to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'onupdate'          => 'onupdateCondoId'
            ],

            'name' => [
                'type'              => 'string',
                'description'       => "Name of the processed document.",
                'required'          => true
            ],

            'description' => [
                'type'              => 'string',
                'description'       => "Short description of the rule to serve as memo."
            ],

            'document_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'documents\Document',
                'description'       => 'Targeted document of the job.',
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null]]
            ],

            'document_link' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'usage'             => 'uri/url.relative',
                'description'       => 'URL for visualizing the document.',
                'function'          => 'calcDocumentLink',
            ],

            'document_type_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'documents\DocumentType',
                'description'       => 'Document type associated with the document.',
                'onupdate'          => 'onupdateDocumentTypeId',
                'dependents'        => ['document_type_code']
            ],

            'document_subtype_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'documents\DocumentSubtype',
                'description'       => 'Document subtype associated with the document.',
                'onupdate'          => 'onupdateDocumentSubtypeId',
            ],

            'document_type_code' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'relation'          => ['document_type_id' => 'code'],
                'store'             => true,
                'instant'           => true
            ],

            'has_document_json' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'relation'          => ['document_id' => 'has_document_json'],
                'store'             => false
            ],

            'supplier_id' => [
                'type'              => 'many2one',
                'description'       => "The supplier the document originates from.",
                'foreign_object'    => 'purchase\supplier\Supplier',
                'onupdate'          => 'onupdateSupplierId',
            ],

            'ownership_id' => [
                'type'              => 'many2one',
                'description'       => "The ownership that the owner refers to.",
                'foreign_object'    => 'realestate\ownership\Ownership',
                'domain'            => ['condo_id', '=', 'object.condo_id'],
                'onupdate'          => 'onupdateOwnershipId'
            ],

            'data' => [
                'type'              => 'binary',
                'description'       => 'Raw binary data of the uploaded document',
                'help'              => 'This field is meant to be used for the subsequent document creation, and is emptied once the document creation is confirmed.',
                'onupdate'          => 'onupdateData'
            ],

            'report_html' => [
                'type'              => 'string',
                'usage'             => 'text/html',
                'description'       => 'Human readable descriptor of the processing result.'
            ],

            'is_rejected' => [
                'type'              => 'boolean',
                'description'       => 'Flag marking the document as rejected (invalid, incomplete, ...).',
                'default'           => false
            ],

            'has_warning' => [
                'type'              => 'boolean',
                'description'       => 'Flag marking the processing job with warning(s).',
                'default'           => false
            ],

            'has_error' => [
                'type'              => 'boolean',
                'description'       => 'Flag marking the processing job with error(s).',
                'default'           => false
            ],

            'has_analysis' => [
                'type'              => 'boolean',
                'description'       => 'Does the document have a JSON version of its content.',
                'default'           => false
            ],

            'has_target_object' => [
                'type'              => 'boolean',
                'description'       => 'Has the target entity been created (drafted).',
                'help'              => 'When the target entity is created (document_invoice_id, document_bank_statement_id, ...), this flag is automatically set to true.',
                'default'           => false
            ],

            'document_source' => [
                'type'              => 'string',
                'description'       => 'The source the document originated from.',
                'selection'         => [
                    'manual',           // manual upload
                    'email',            // email digestor
                    'internal',         // document produced by the software
                    'external'          // document retrieved from an external source (API, ...)
                ],
                'default'           => 'manual'
            ],

            'source_type' => [
                'type'              => 'string',
                'selection'         => [
                    'email',
                    'manual'
                ],
                'default'           => 'manual',
                'description'       => 'Type of source (eid, registry, manual, etc.)',
                'help'              => 'Indicates how the document data was obtained.',
            ],

            'status' => [
                'type'              => 'string',
                'description'       => 'Current status of the job.',
                'selection'         => [
                    'created',
                    'completed',
                    'validated',
                    'recorded',
                    'confirmed',
                    'integrated',
                    'cancelled'
                ],
                'default'           => 'created'
            ],

            /*
                Fields below are links to possible documents being processed, depending on DocumentTypes (associated to a valid JSON schema)
                The information contained in document_json is used to complete these objects.
                In cases where they are not present, the user can add them manually.
            */

            'document_invoice_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\purchase\accounting\invoice\PurchaseInvoice',
                'visible'           => ['document_type_code', '=', 'invoice'],
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['supplier_id', '=', 'object.supplier_id']]
            ],

            'document_bank_statement_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\bank\BankStatement',
                'visible'           => ['document_type_code', '=', 'bank_statement']
            ]

            // #todo - [...] to be completed according to document types that are supported by the DocumentProcess workflow

        ];
    }

    public static function getWorkflow() {
        return [
            'created' => [
                'description' => 'Just imported document, waiting to be completed (manually or auto-analysis).',
                'icon'        => 'draw',
                'transitions' => [
                    'complete' => [
                        'description' => 'Update the document to `completed`.',
                        'policies'    => ['is_complete', 'is_unique'],
                        'status'      => 'completed'
                    ],
                    'cancel' => [
                        'description' => 'Cancel the processing (duplicate, invalid, complaint, ...).',
                        'policies'    => [],
                        'status'      => 'cancelled'
                    ]
                ]
            ],
            'completed' => [
                'description' => 'Completed document, waiting to be validated.',
                'icon'        => 'assignment',
                'transitions' => [
                    'revert' => [
                        'description' => 'Revert the document to `created`.',
                        'status'      => 'created'
                    ],
                    'validate' => [
                        'description' => 'Update the document to `validated`.',
                        'policies'    => ['is_valid', 'can_validate'],
                        'status'      => 'validated'
                    ]
                ]
            ],
            'validated' => [
                'description' => 'Validated document, waiting to be processed.',
                'icon'        => 'done',
                'transitions' => [
                    'revert' => [
                        'description' => 'Revert the document to `completed`.',
                        'status'      => 'completed'
                    ],
                    'record' => [
                        'description' => 'Update the document to `recorded`.',
                        'policies'    => ['can_record'],
                        'onbefore'    => 'onbeforeRecord',
                        'status'      => 'recorded'
                    ]
                ]
            ],
            'recorded' => [
                'description' => 'Validated document, waiting to be confirmed.',
                'icon'        => 'done_all',
                'transitions' => [
                    'confirm' => [
                        'description' => 'Update the document to `confirmed`.',
                        'policies'    => ['can_confirm'],
                        'status'      => 'confirmed'
                    ]
                ]
            ],
            'confirmed' => [
                'description' => 'Recorded document, waiting to be integrated.',
                'icon'        => 'assignment_turned_in',
                'transitions' => [
                    'integrate' => [
                        'description' => 'Update the document to `integrated`.',
                        'help'        => 'Integration is meant to be made automatically at the end of the workflow.',
                        'policies'    => [],
                        'onbefore'    => 'onbeforeIntegrate',
                        'status'      => 'integrated'
                    ]
                ]
            ],
            'integrated' => [
                'description' => 'Finalized document.',
                'icon'        => 'check_circle',
                'transitions' => []
            ],
            'cancelled' => [
                'description' => 'Just imported document, waiting to be completed (manually or auto-analysis).',
                'icon'        => 'cancel',
                'transitions' => []
            ]
        ];
    }

    public static function getPolicies(): array {
        return [
            'can_perform_identification' => [
                'description' => 'Verifies that the state of the processing allows identification.',
                'function'    => 'policyCanPerformIdentification'
            ],
            'can_perform_extraction' => [
                'description' => 'Verifies that the state of the processing allows extraction.',
                'function'    => 'policyCanPerformExtraction'
            ],
            'can_perform_matching' => [
                'description' => 'Verifies that the state of the processing allows matching.',
                'function'    => 'policyCanPerformMatching'
            ],
            'can_perform_drafting' => [
                'description' => 'Verifies that the state of the processing allows drafting.',
                'function'    => 'policyCanPerformDrafting'
            ],
            'is_complete' => [
                'description' => 'Verifies that all requested information are present.',
                'function'    => 'policyIsComplete'
            ],
            'is_unique' => [
                'description' => 'Verifies that the document has not been already imported (unless cancelled).',
                'function'    => 'policyIsUnique'
            ],
            'is_valid' => [
                'description' => 'Verifies that the document is valid, according to rules linked to document type.',
                'function'    => 'policyIsValid'
            ],
            'can_validate' => [
                'description' => 'Checks if the current user is permitted to validate the document based on their roles.',
                'function'    => 'policyCanValidate'
            ],
            'can_record' => [
                'description' => 'Checks if the current user is permitted to record the document based on their roles.',
                'function'    => 'policyCanRecord'
            ],
            'can_confirm' => [
                'description' => 'Checks if the current user is permitted to confirm the document based on their roles.',
                'function'    => 'policyCanConfirm'
            ]
        ];
    }

    public static function getActions() {
        return array_merge(parent::getActions(), [
            'perform_identification' => [
                'description'   => 'Attempt to identity document type and subtype.',
                'policies'      => ['can_perform_identification'],
                'function'      => 'doPerformIdentification'
            ],
            'perform_extraction' => [
                'description'   => 'Attempt to retrieve meta info of the document (based on document type).',
                'policies'      => ['can_perform_extraction'],
                'function'      => 'doPerformExtraction'
            ],
            'perform_matching' => [
                'description'   => 'Attempt to auto-link to other entities according to document meta data (cond_id, supplier_id).',
                'policies'      => ['can_perform_matching'],
                'function'      => 'doPerformMatching'
            ],
            'perform_drafting' => [
                'description'   => 'Attempt to populate the target Entity with the retrieved data.',
                'policies'      => ['can_perform_drafting'],
                'function'      => 'doPerformDrafting'
            ],
            'update_document_json' => [
                'description'   => 'Update the JSON representation of the target document.',
                'help'          => 'This is used for handling arbitrary changes to one or more fields (according to JSON schema) when encoding targeted documents (e.g. purchase invoice).',
                // #todo - add policy - can only be performed while document is at 'completion' stage
                'policies'      => [],
                'function'      => 'doUpdateDocumentJson'
            ]
        ]);
    }

    /**
     * #todo - vérifier que l'utilisateur a un rôle de "Manager"
     *
     */
    protected static function policyCanValidate($self, $auth): array {
        $result = [];

        return $result;
        $user_id = $auth->userId();

        $authorized_roles = ['director', 'manager'];
        $roles_ids = Role::search(['code', 'in', $authorized_roles])->ids();

        $self->read(['condo_id']);

        foreach($self as $id => $documentProcess) {
            // check if user has an assignment for this role on the targeted condo_id
            // #memo - if $condo_id is null, it will fetch global entry, if any (i.e. the user has the role for any condo)
            $assignments_ids = RoleAssignment::search([
                    ['user_id', '=', $user_id],
                    ['role_id', 'in', $roles_ids],
                    ['condo_id', '=', $documentProcess['condo_id']]
                ])
                ->ids();
            if(!count($assignments_ids)) {
                $result[$id] = [
                    'not_allowed' => 'User has none of allowed roles.'
                ];
            }
        }
        return $result;
    }

    /**
     * #todo - vérifier que l'utilisateur a un rôle de "Accountant"
     *
     */
    protected static function policyCanRecord($self): array {
        $result = [];
        $authorized_roles = ['accountant'];

        return $result;
    }

    protected static function policyCanConfirm($self): array {
        $result = [];
        $authorized_roles = ['director', 'accountant'];

        return $result;
    }

    public static function candelete($self) {
        $self->read(['status']);
        foreach($self as $documentProcess) {
            if($documentProcess['status'] != 'created') {
                return ['status' => ['non_removable' => 'Non-draft Document cannot be deleted.']];
            }
        }
        return parent::candelete($self);
    }

    public static function policyCanPerformIdentification($self): array {
        $result = [];
        $self->read(['status', 'document_type_id']);
        foreach($self as $id => $documentProcess) {
            // #memo - we must allow assigning document_type_id manually
            if($documentProcess['status'] != 'created') {
                $result[$id] = [
                    'invalid_status' => 'Document type has already been identified.'
                ];
                continue;
            }
        }
        return $result;
    }

    public static function policyCanPerformExtraction($self): array {
        $result = [];
        $self->read(['status', 'document_id' => ['has_document_json']]);
        foreach($self as $id => $documentProcess) {
            if($documentProcess['status'] != 'created') {
                $result[$id] = [
                    'invalid_status' => 'Document has cannot be automatically modified anymore.'
                ];
                continue;
            }
        }
        return $result;
    }

    public static function policyCanPerformMatching($self): array {
        $result = [];
        $self->read(['status']);
        foreach($self as $id => $documentProcess) {
            if($documentProcess['status'] != 'created') {
                $result[$id] = [
                    'invalid_status' => 'Document has cannot be automatically modified anymore.'
                ];
                continue;
            }
        }
        return $result;
    }

    protected static function policyCanPerformDrafting($self): array {
        $result = [];
        $self->read(['condo_id', 'document_type_code', 'document_type_id', 'document_subtype_id', 'has_target_object']);

        foreach($self as $id => $documentProcess) {
            if($documentProcess['has_target_object']) {
                $result[$id] = [
                    'invalid_status' => 'Target document has already been created.'
                ];
                continue;
            }
            if(!isset($documentProcess['document_type_id'])) {
                $result[$id] = [
                    'invalid_status' => 'Document type is unknown.'
                ];
                continue;
            }

            // #todo - to complete
            $is_recording_rule_mandatory = in_array($documentProcess['document_type_code'], ['invoice', 'credit_note']);

            // #memo - recording rules might not be mandatory
            /*
            // #todo - a recording rule might be missing
            if($is_recording_rule_mandatory) {
                $rules_ids = RecordingRule::search([['condo_id', '=', $documentProcess['condo_id']], ['document_type_id', '=', $documentProcess['document_type_id']]])->ids();
                if(count($rules_ids) <= 0) {
                    $result[$id] = [
                        'no_rule_match' => 'No rule are available for the document type.'
                    ];
                    continue;
                }
            }
            */

        }
        return $result;
    }

    public static function policyIsUnique($self): array {
        $result = [];
        $self->read(['document_type_code']);
        foreach($self as $id => $documentProcess) {
            if(!isset($documentProcess['document_type_code'])) {
                continue;
            }
            // duplicate invoice amongst purchase invoice of the Condominium
            if($documentProcess['document_type_code'] === 'invoice' || $documentProcess['document_type_code'] === 'credit_note') {
                // check if there is a non-cancelled DocumentProcess concerning an invoice with the same characteristics
                $documentProcess = self::id($id)->read(['document_invoice_id'])->first();
                $purchaseInvoice = PurchaseInvoice::id($documentProcess['document_invoice_id'])->read(['id', 'suppliership_id', 'supplier_invoice_number'])->first();
                $has_duplicate = false;
                $duplicateInvoices = PurchaseInvoice::search([
                        ['id','<>', $purchaseInvoice['id']],
                        ['suppliership_id', '=', $purchaseInvoice['suppliership_id']],
                        ['supplier_invoice_number', '=', $purchaseInvoice['supplier_invoice_number']]
                    ])
                    ->read(['document_process_id' => ['status']]);

                foreach($duplicateInvoices as $duplicateInvoice) {
                    if(isset($duplicateInvoice['document_process_id']['status']) && !in_array($duplicateInvoice['document_process_id']['status'], ['proforma', 'cancelled'])) {
                        $has_duplicate = true;
                        break;
                    }
                }
            }
            // search for duplicate bank statement amongst statements of the Condominium
            elseif($documentProcess['document_type_code'] === 'bank_statement') {
                $documentProcess = self::id($id)->read(['document_bank_statement_id'])->first();
                $bankStatement = BankStatement::id($documentProcess['document_bank_statement_id'])->read(['id', 'opening_date', 'closing_date', 'opening_balance', 'closing_balance'])->first();
                $duplicateStatements = BankStatement::search([
                        ['id','<>', $bankStatement['id']],
                        ['opening_date', '=', $bankStatement['opening_date']],
                        ['closing_date', '=', $bankStatement['closing_date']],
                        ['opening_balance', '=', $bankStatement['opening_balance']],
                        ['closing_balance', '=', $bankStatement['closing_balance']]
                    ])
                    ->read(['document_process_id' => ['status']]);

                foreach($duplicateStatements as $duplicateStatement) {
                    if(isset($duplicateStatement['document_process_id']['status']) && $duplicateStatement['document_process_id']['status'] !== 'cancelled') {
                        $has_duplicate = true;
                        break;
                    }
                }
            }
            if($has_duplicate) {
                $result[$id] = [
                    'duplicate_document' => 'This document has already been imported'
                ];
                continue;
            }

        }
        return $result;
    }

    /**
     * Check that all required information in order to apply recording rules are present.
     *
     */
    public static function policyIsComplete($self): array {
        $result = [];
        $self->read(['status', 'document_type_id' => ['json_schema'], 'document_subtype_id', 'document_id' => ['document_json']]);
        foreach($self as $id => $documentProcess) {
            if(!isset($documentProcess['document_id'])) {
                // missing document
                $result[$id] = [
                    'invalid_document' => 'Missing document.'
                ];
            }
            if(!isset($documentProcess['document_type_id'])) {
                // missing document type
                $result[$id] = [
                    'invalid_document' => 'Missing document type.'
                ];
                continue;
            }
            if(!isset($documentProcess['document_type_id']['json_schema'])) {
                // missing document schema
                $result[$id] = [
                    'invalid_document_type' => 'Missing document schema.'
                ];
                continue;
            }
            if(!isset($documentProcess['document_id']['document_json'])) {
                // missing document json
                $result[$id] = [
                    'invalid_document' => 'Missing document JSON payload.'
                ];
                continue;
            }
            try {
                // validate the document JSON using the schema associated to the document type, including values format and requested properties
                $data = \eQual::run('get', 'json-validate', ['json' => $documentProcess['document_id']['document_json'], 'schema_id' => $documentProcess['document_type_id']['json_schema']]);
                if(isset($data['errors']) && count($data['errors'])) {
                    $result[$id] = [
                        'invalid_document' => $data['errors']
                    ];
                    continue;
                }
            }
            catch(\Exception $e) {
                trigger_error("APP::unable to validate JSON :" . $e->getMessage(), EQ_REPORT_WARNING);
                $result[$id] = [
                    'invalid_document' => 'Unable to validate document'
                ];
            }
        }
        return $result;
    }


    /**
     * Check that all information required in order to perform the "recording" (i.e. creation of a proforma resource)
     * are present and consistent with the logic associated to the document type.
     */
    public static function policyIsValid($self): array {
        $result = [];
        $self->read(['status', 'document_type_id' => ['json_schema'], 'document_subtype_id', 'document_id' => ['document_json']]);
        // #todo - use ValidationRule based on document_type
        // as a first draft, we use the same check as for completeness (`policyIsComplete`)
        foreach($self as $id => $documentProcess) {
            if(!isset($documentProcess['document_id'])) {
                // missing document
                $result[$id] = [
                    'invalid_document' => 'Missing document'
                ];
            }
            if(!isset($documentProcess['document_type_id'])) {
                // missing document type
                $result[$id] = [
                    'invalid_document' => 'Missing document type'
                ];
                continue;
            }
            if(!isset($documentProcess['document_type_id']['json_schema'])) {
                // missing document schema
                $result[$id] = [
                    'invalid_document' => 'Missing document schema'
                ];
                continue;
            }
            if(!isset($documentProcess['document_id']['document_json'])) {
                // missing document json
                $result[$id] = [
                    'invalid_document' => 'Missing document json payload'
                ];
                continue;
            }
            try {
                $data = \eQual::run('get', 'json-validate', ['json' => $documentProcess['document_id']['document_json'], 'schema_id' => $documentProcess['document_type_id']['json_schema']]);
                if(isset($data['errors']) && count($data['errors'])) {
                    $result[$id] = [
                        'invalid_document' => $data['errors_string']
                    ];
                    // #todo - append errors to log
                    continue;
                }
            }
            catch(\Exception $e) {
                trigger_error("APP::unable to validate JSON :" . $e->getMessage(), EQ_REPORT_WARNING);
            }
        }
        return $result;
    }

    public static function onbeforeRecord($self) {
    }

    /**
     * Create accounting entries according to linked document
     */
    public static function onbeforeIntegrate($self) {
        // #todo - to complete according to document types
        $self->read(['document_type_code', 'document_invoice_id' => ['status'], 'document_bank_statement_id' => ['status']]);
        foreach($self as $id => $documentProcess) {
            switch($documentProcess['document_type_code']) {
                case 'invoice':
                    if($documentProcess['document_invoice_id']['status'] === 'proforma') {
                        PurchaseInvoice::id($documentProcess['document_invoice_id']['id'])->transition('post');
                    }
                    break;
                case 'bank_statement':
                    if($documentProcess['document_bank_statement_id']['status'] === 'proforma') {
                        BankStatement::id($documentProcess['document_bank_statement_id']['id'])->transition('post');
                    }
                    break;
                // #todo - add other document types
                default:
                    trigger_error("APP::onbeforeIntegrate - Document type {$documentProcess['document_type_code']} is not supported for integration.", EQ_REPORT_WARNING);
                    break;
            }
        }
    }

    /**
     * Handle data update (i.e. file upload).
     * This method is used to:
     *  - create the document based on received data
     *  - and initiate the processing.
     */
    public static function onupdateData($self) {
        $self->read(['name', 'data']);
        foreach($self as $id => $documentProcess) {
            // create a new document
            // #memo - at this stage the Document remains local (no UUID), an attempt to push to EDMS instance will be performed after assignment of condo_id, if matching succeeds
            $document = Document::create(['name' => $documentProcess['name'], 'data' => $documentProcess['data']])->first();
            // remove data from current object (to avoid data redundancy)
            self::id($id)
                ->update([
                    'document_id' => $document['id'],
                    'data'        => null
                ]);
        }

        // #todo - check if completion.auto enabled

        try {
            $self
                ->do('perform_identification')
                ->do('perform_extraction')
                ->do('perform_matching')
                ->do('perform_drafting');
        }
        catch(\Exception $e) {
            // do not interrupt - Documents might not be automatically analyzed
            // at early stage, user is allowed to manually encode data
            trigger_error("APP::issue in automated tasks" . $e->getMessage(), EQ_REPORT_WARNING);
        }
    }

    public static function onupdateCondoId($self) {
        $self->read(['condo_id', 'document_id']);
        foreach($self as $id => $documentProcess) {
            if(isset($documentProcess['document_id'])) {
                Document::id($documentProcess['document_id'])->update(['condo_id' => $documentProcess['condo_id']]);
            }
        }
    }

    public static function onupdateDocumentTypeId($self) {
        $self->read(['document_type_id', 'document_id']);
        foreach($self as $id => $documentProcess) {
            if(isset($documentProcess['document_id'])) {
                Document::id($documentProcess['document_id'])
                    ->update(['document_type_id' => $documentProcess['document_type_id']]);
            }
        }

        // #memo - for now we leave this as manual action
        /*
        try {
            // document_type has changed, re-attempt to extract data
            $self->do('perform_extraction');
        }
        catch(\Exception $d) {
        }
        */
    }

    public static function onupdateDocumentSubtypeId($self) {
        $self->read(['document_subtype_id', 'document_id']);
        foreach($self as $id => $documentProcess) {
            if(isset($documentProcess['document_id'])) {
                Document::id($documentProcess['document_id'])->update(['document_subtype_id' => $documentProcess['document_subtype_id']]);
            }
        }
    }

    public static function onupdateSupplierId($self) {
        $self->read(['supplier_id', 'document_id']);
        foreach($self as $id => $documentProcess) {
            if(isset($documentProcess['document_id'])) {
                Document::id($documentProcess['document_id'])->update(['supplier_id' => $documentProcess['supplier_id']]);
            }
        }
    }

    public static function onupdateOwnershipId($self) {
        $self->read(['ownership_id', 'document_id']);
        foreach($self as $id => $documentProcess) {
            if(isset($documentProcess['document_id'])) {
                Document::id($documentProcess['document_id'])->update(['ownership_id' => $documentProcess['ownership_id']]);
            }
        }
    }

    /**
     * DocumentProcess is used to upload and create a new Document.
     * We rely on the same strategy than regular Document upload, by receiving document meta from UI with onchange event.
     */
    public static function onchange($event, $values) {
        $result = [];

        if(isset($event['data']['name'])) {
            $result['name'] = $event['data']['name'];
        }

        return $result;
    }

    /**
     * This method is called from the targeted objects, providing a map of values updates.
     * No consistency check is performed here.
     */
    protected static function doUpdateDocumentJson($self, $values) {
        $self->read(['status', 'document_id' => ['has_document_json', 'document_json']]);

        $recursiveUpdate = function(array &$data, array $updates) use (&$recursiveUpdate) {
            foreach($updates as $key => $value) {
                if(!array_key_exists($key, $data)) {
                    trigger_error("APP::property $key does not exist in document JSON", E_USER_WARNING);
                    throw new \Exception('invalid_document_json_field', EQ_ERROR_INVALID_PARAM);
                }

                if(is_array($value) && is_array($data[$key])) {
                    $recursiveUpdate($data[$key], $value);
                }
                else {
                    $data[$key] = $value;
                }
            }
        };

        foreach($self as $id => $documentProcess) {
            if($documentProcess['status'] !== 'created') {
                trigger_error("APP::Update skipped for document process already encoded.", EQ_REPORT_WARNING);
                continue;
            }
            if(!$documentProcess['document_id']) {
                throw new \Exception('missing_document_id', EQ_ERROR_INVALID_PARAM);
            }
            if(!$documentProcess['document_id']['has_document_json']) {
                throw new \Exception('missing_document_json', EQ_ERROR_INVALID_PARAM);
            }
            $data = json_decode($documentProcess['document_id']['document_json'], true);

            if(!is_array($data)) {
                throw new \Exception('invalid_document_json', EQ_ERROR_INVALID_PARAM);
            }

            $recursiveUpdate($data, $values);

            Document::id($documentProcess['document_id']['id'])->update(['document_json' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)]);
        }
    }

    protected static function doPerformIdentification($self) {
        $self->read(['document_id', 'report_html']);
        foreach($self as $id => $documentProcess) {
            $values = [];
            $logs = [];
            $logs[] = "<b>Identification</b>";

            $identification = \eQual::run('get', 'documents_processing_identify', ['id' => $documentProcess['document_id']]);

            if(isset($identification['document_type']['id'])) {

                $values['document_type_id'] = $identification['document_type']['id'];
                $logs[] = "Retrieved document type: " . $identification['document_type']['code'];

                if(isset($identification['document_subtype']['id'])) {
                    $values['document_subtype_id'] = $identification['document_subtype']['id'];
                    $logs[] = "Retrieved document sub-type: " . $identification['document_subtype']['code'];
                }

                $report_html = $documentProcess['report_html'];
                if(strlen($report_html) > 0) {
                    $report_html .= "<br />";
                }

            }

            $report_html = $documentProcess['report_html'];
            if(strlen($report_html) > 0) {
                $report_html .= "<br />";
            }

            $values['report_html'] = $report_html . implode("<br />", $logs);

            self::id($id)->update($values);
        }
    }

    protected static function doPerformExtraction($self) {
        $self->read(['document_type_id' => ['code', 'json_schema'], 'document_id', 'report_html']);

        foreach($self as $id => $documentProcess) {
            if(!$documentProcess['document_id']) {
                continue;
            }

            if(!$documentProcess['document_type_id']) {
                continue;
            }

            $values = [];
            $logs = [];
            $logs[] = "<b>Extraction</b>";

            // extract data based on document type
            try {
                switch($documentProcess['document_type_id']['code']) {
                    case 'invoice':
                    case 'credit_note':
                        $data = \eQual::run('get', 'documents_processing_purchaseInvoice_extract', ['document_id' => $documentProcess['document_id']]);
                        break;
                    case 'bank_statement':
                        $data = \eQual::run('get', 'documents_processing_bankStatement_extract', ['document_id' => $documentProcess['document_id']]);
                        if(!is_array($data)) {
                            trigger_error("APP::unexpected bank statement returned as a non-array for process {$id} ({$documentProcess['document_type_id']['code']})", EQ_REPORT_WARNING);
                            throw new \Exception('invalid_document', EQ_ERROR_INVALID_PARAM);
                        }
                        if(count($data) > 1) {
                            trigger_error("APP::unexpected bank statement returned with more than one statement for process {$id} ({$documentProcess['document_type_id']['code']})", EQ_REPORT_WARNING);
                            // #memo - file holding several bank statements
                            throw new \Exception('invalid_document', EQ_ERROR_INVALID_PARAM);
                        }
                        $data = current($data);
                        break;
                    default:
                        throw new \Exception('unsupported_document_type', EQ_ERROR_INVALID_PARAM);
                }

                if(!empty($data)) {
                    // #memo - we don't really need to validate : at this stage we're only interested in raw data, but we must ensure data are consistent
                    $validation = \eQual::run('get', 'json-validate', ['json' => json_encode($data), 'schema_id' => $documentProcess['document_type_id']['json_schema']]);

                    if(isset($validation['errors']) && count($validation['errors'])) {
                        foreach((array) $validation['errors'] as $section => $errors) {
                            foreach($errors as $error) {
                                $logs[] = $error;
                            }
                        }
                        ob_start();
                        print_r($validation['errors']);
                        $out = ob_get_clean();
                        trigger_error("APP::received validation errors for process {$id} ({$documentProcess['document_type_id']['code']}): " . $out, EQ_REPORT_WARNING);
                        throw new \Exception('invalid_document_data', EQ_ERROR_INVALID_PARAM);
                    }

                    // log extraction result
                    $schema = \eQual::run('get', 'json-schema', ['id' => $documentProcess['document_type_id']['json_schema']]);
                    $logs = array_merge($logs, ['Data retrieved from document descriptor:'], self::computeLogsFromSchema($schema['properties'] ?? [], $data));

                    // #memo - document_json is meant to receive a JSON representation of the content, according to schema, and independent from origin (ex.: parsed Mindee, parsed UBL, ...)
                    $doc_values = [
                            'document_json' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
                        ];

                    Document::id($documentProcess['document_id'])->update($doc_values);
                }
                else {
                    // extraction failed : populat with empty document_json descriptor
                    switch($documentProcess['document_type_id']['code']) {
                        case 'invoice':
                        case 'credit_note':
                            $data = \eQual::run('get', 'documents_processing_purchaseInvoice_empty');
                            break;
                        case 'bank_statement':
                            $data = \eQual::run('get', 'documents_processing_bankStatement_empty');
                    }

                    // #memo - document_json is meant to receive a JSON representation of the content, according to schema, and independent from origin (ex.: parsed Mindee, parsed UBL, ...)
                    $doc_values = [
                            'document_json' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
                        ];

                    Document::id($documentProcess['document_id'])->update($doc_values);
                }
            }
            catch(\Exception $e) {
                // unexpected error
                $logs[] = "Extraction error : " . $e->getMessage();
                $logs[] = "Non supported document type or Document does not match expected format.";
                trigger_error("APP::unable to extract document for process {$id} ({$documentProcess['document_type_id']['code']}): " . $e->getMessage(), EQ_REPORT_WARNING);
            }

            $report_html = $documentProcess['report_html'];
            if(strlen($report_html) > 0) {
                $report_html .= "<br />";
            }

            $values['report_html'] = $report_html . implode("<br />", $logs);

            self::id($id)->update($values);
        }

    }

    /**
     * Attempts auto-linking to other entities according to document meta data.
     *
     *
     * For Document         -> condo_id
     * For DocumentProcess  -> condo_id, supplier_id
     *
     */
    protected static function doPerformMatching($self) {
        $self->read(['condo_id', 'supplier_id', 'report_html', 'document_type_code', 'document_id' => ['id', 'content_type', 'document_json']]);

        foreach($self as $id => $documentProcess) {
            if(!$documentProcess['document_id']) {
                continue;
            }

            $logs = [];
            $logs[] = "<b>Matching</b>";
            $values = [
                    'condo_id'          => $documentProcess['condo_id'],
                    'supplier_id'       => $documentProcess['supplier_id'],
                    'has_analysis'      => true
                ];

            try {

                // updates for the Document
                $doc_values = [];

                $data = json_decode($documentProcess['document_id']['document_json'], true);

                switch($documentProcess['document_type_code']) {
                    case 'invoice':
                    case 'credit_note':
                        if($documentProcess['document_type_code'] === 'invoice') {
                            $logs[] = "attempting to match invoice data";
                        }
                        else {
                            $logs[] = "attempting to match credit note data";
                        }

                        // attempt to retrieve supplier
                        if(!$values['supplier_id']) {
                            if(isset($data['supplier']['name'])) {
                                $suppliers_ids = Supplier::search(['legal_name', 'ilike', $data['supplier']['name'] . '%'])->ids();
                                if(count($suppliers_ids)) {
                                    $values['supplier_id'] = current($suppliers_ids);
                                    $logs[] = "supplier_id retrieved from '{$data['supplier']['name']}'";
                                }
                            }
                        }
                        // attempt to retrieve condominium by number
                        if(!$values['condo_id'] && $values['supplier_id']) {
                            // lookup into SuppliershipReference to identify the Condominium
                            $customer_refs = ['customer_number', 'contract_number', 'installation_number'];
                            foreach($customer_refs as $ref) {
                                if(!isset($data['customer'][$ref])) {
                                    continue;
                                }
                                $supplierReference = SuppliershipReference::search([
                                        ['supplier_id', '=', $values['supplier_id']],
                                        ['reference_type', '=', $ref],
                                        ['reference_value', '=', $data['customer'][$ref]]
                                    ])
                                    ->read(['condo_id'])
                                    ->first();

                                if($supplierReference) {
                                    $values['condo_id'] = $supplierReference['condo_id'];
                                    $doc_values['condo_id'] = $values['condo_id'];
                                    $logs[] = "condo_id retrieved from '{$data['customer'][$ref]}' ($ref)";
                                    break;
                                }
                            }
                        }

                        if(!isset($values['condo_id'])) {
                            // attempt to retrieve condominium by name
                            if(isset($data['customer']['name'])) {
                                $parts = explode(' ', trim($data['customer']['name'], " \n\r\t\v\0-_\/"));
                                $customer_name = implode(' ', array_filter($parts, function($a, $k) { return $k < 3 && !preg_match('/[^\p{L}\p{N}]/iu', $a); }, ARRAY_FILTER_USE_BOTH));
                                $condominiums_ids = Condominium::search(['legal_name', 'ilike', $customer_name . '%'])->ids();
                                if(count($condominiums_ids) === 1) {
                                    $values['condo_id'] = current($condominiums_ids);
                                    $doc_values['condo_id'] = $values['condo_id'];
                                    $logs[] = "condo_id retrieved from '{$customer_name}'";
                                }
                            }
                        }
                        break;
                    case 'bank_statement':
                        $logs[] = "attempting to match bank statement data";
                        if(!$values['condo_id']) {
                            if(isset($data['account_iban']) && strlen($data['account_iban'])) {
                                $bankAccount = CondominiumBankAccount::search(['bank_account_iban', '=', $data['account_iban']])->read(['condo_id'])->first();
                                if($bankAccount && $bankAccount['condo_id']) {
                                    $values['condo_id'] = $bankAccount['condo_id'];
                                    $doc_values['condo_id'] = $values['condo_id'];
                                    $logs[] = "condo_id retrieved from '{$data['account_iban']}'";
                                }
                            }
                        }
                        if(isset($data['bank_bic']) && strlen($data['bank_bic'])) {
                            $bank = Bank::search(['bic', '=', $data['bank_bic']])->first();
                            if($bank) {
                                $values['supplier_id'] = $bank['id'];
                                $logs[] = "supplier_id retrieved from '{$data['bank_bic']}'";
                            }
                        }
                        break;
                }

                if(count($doc_values)) {
                    Document::id($documentProcess['document_id']['id'])->update($doc_values);
                }
            }
            catch(\Exception $e) {
                // unexpected error
                // unable to extract or confidence level too low
                $logs[] = "Extraction error : " . $e->getMessage();
                $logs[] = "Unable to extract from given data.";
                trigger_error("APP::unable to extract document, or confidence level too low." . $e->getMessage(), EQ_REPORT_WARNING);
            }

            $report_html = $documentProcess['report_html'];
            if(strlen($report_html) > 0) {
                $report_html .= "<br />";
            }

            $values['report_html'] = $report_html . implode("<br />", $logs);

            self::id($id)->update($values);

        }

    }

    /**
     * Create the proforma target resource based on Document type and JSON data.
     *
     */
    protected static function doPerformDrafting($self) {
        $self->read(['condo_id', 'report_html', 'has_target_object', 'supplier_id', 'document_type_code', 'document_type_id', 'document_subtype_id', 'document_id' => ['name', 'document_json']]);
        foreach($self as $id => $documentProcess) {
            // ignore if mandatory info is missing
            if(!$documentProcess['document_type_code']) {
                continue;
            }
            // ignore if draft already exists
            if($documentProcess['has_target_object']) {
                continue;
            }

            $values = [];
            $logs = [];
            $logs[] = "<b>Drafting</b>";

            try {
                $data = json_decode($documentProcess['document_id']['document_json'], true);
                if(json_last_error() !== JSON_ERROR_NONE) {
                    trigger_error('APP::unexpected JSON decoding error: ' . json_last_error_msg(), EQ_REPORT_WARNING);
                    throw new \Exception('invalid_json', EQ_ERROR_INVALID_PARAM);
                }

                // find suppliership
                $suppliership = Suppliership::search([
                        ['condo_id', '=', $documentProcess['condo_id']],
                        ['supplier_id', '=',  $documentProcess['supplier_id']]
                    ])
                    ->first();

                if(!$suppliership) {
                    throw new \Exception('missing_suppliership', EQ_ERROR_INVALID_CONFIG);
                }

                $recordingRule = RecordingRule::search([
                        ['condo_id', '=', $documentProcess['condo_id']],
                        ['document_type_id', '=', $documentProcess['document_type_id']],
                        ['document_subtype_id', '=', $documentProcess['document_subtype_id']]
                    ])
                    ->first();

                if(!$recordingRule) {
                    $recordingRule = RecordingRule::search([
                            ['condo_id', '=', $documentProcess['condo_id']],
                            ['document_type_id', '=', $documentProcess['document_type_id']],
                        ])
                        ->first();
                }

                if($recordingRule) {
                    $recordingRuleLines = RecordingRuleLine::search(['recording_rule_id', '=', $recordingRule['id']])
                        ->read([
                            'account_id', 'apportionment_id', 'owner_share', 'tenant_share', 'share'
                        ]);
                }
                else {
                    // #memo - recording rules might not apply on specific documents (e.g. bank statements)
                    trigger_error("APP::No matching Recording Rule found for Process {$documentProcess['id']} - Document {$documentProcess['document_id']['name']} ({$documentProcess['document_id']['id']}) of type {$documentProcess['document_type_code']}/{$documentProcess['document_subtype_id']}", EQ_REPORT_WARNING);
                    // throw new \Exception('missing_recording_rule', EQ_ERROR_INVALID_CONFIG);
                }

                if($documentProcess['document_type_code'] === 'invoice' || $documentProcess['document_type_code'] === 'credit_note') {
                    $bankAccount = SuppliershipBankAccount::search([['suppliership_id', '=', $suppliership['id']], ['bank_account_iban', '=', $data['payment']['iban']]])->first();

                    // create invoice
                    $invoice = PurchaseInvoice::create([
                            'condo_id'                      => $documentProcess['condo_id'],
                            'suppliership_id'               => $suppliership['id'],
                            'supplier_invoice_number'       => $data['invoice_number'],
                            'suppliership_bank_account_id'  => $bankAccount['id'] ?? null,
                            'payment_reference'             => str_replace(['+', '/'], '', $data['payment']['payment_id']),
                            'emission_date'                 => strtotime($data['issue_date']),
                            'due_date'                      => strtotime($data['due_date']),
                            'has_fund_usage'                => false,
                            'has_instant_reinvoice'         => false,
                            'document_process_id'           => $id,
                            'document_id'                   => $documentProcess['document_id']['id']
                        ])
                        // posting_date triggers sync with fiscal year & period
                        ->update([
                            'posting_date'                  => strtotime($data['issue_date'])
                        ])
                        ->first();

                    foreach($recordingRuleLines as $recordingRuleLine) {
                        // add invoice lines
                        foreach($data['lines'] as $line) {
                            $vat_rate = 0.0;

                            if(!empty($line['tax']['percent'])) {
                                $vat_rate = round(floatval($line['tax']['percent']) / 100, 2);
                            }

                            PurchaseInvoiceLine::create([
                                'condo_id'              => $documentProcess['condo_id'],
                                'invoice_id'            => $invoice['id'],
                                // #todo - use LabelingRule
                                'description'           => $line['description'] ?? '',
                                'is_private_expense'    => false,
                                'has_instant_reinvoice' => false,
                                'expense_account_id'    => $recordingRuleLine['account_id'],
                                'apportionment_id'      => $recordingRuleLine['apportionment_id'],
                                'owner_share'           => $recordingRuleLine['owner_share'],
                                'tenant_share'          => $recordingRuleLine['tenant_share'],
                                'total'                 => round(floatval($line['amount']) * $recordingRuleLine['share'], 2),
                                'vat_rate'              => $vat_rate
                            ]);
                        }
                    }

                    $logs[] = "Drafted purchase invoice {$invoice['id']}";
                    self::id($id)->update(['document_invoice_id' => $invoice['id'], 'has_target_object' => true]);
                }
                elseif($documentProcess['document_type_code'] === 'bank_statement') {
                    $bankAccount = CondominiumBankAccount::search([['condo_id', '=', $documentProcess['condo_id']], ['bank_account_iban', '=', $data['account_iban']]])->first();

                    // create the BankStatement
                    // #memo - by convention new statements have their status set to 'pending'
                    $bankStatement = BankStatement::create([
                            'condo_id'              => $documentProcess['condo_id'],
                            'date'                  => time(),
                            'opening_date'          => strtotime($data['opening_date']),
                            'closing_date'          => strtotime($data['closing_date']),
                            'opening_balance'       => round(floatval($data['opening_balance']), 2),
                            'closing_balance'       => round(floatval($data['closing_balance']), 2),
                            'statement_currency'    => $data['statement_currency'],
                            'bank_account_id'       => $bankAccount['id'] ?? null,
                            'bank_account_iban'     => $data['account_iban'],
                            'bank_account_bic'      => $data['bank_bic'],
                            'document_process_id'   => $id,
                            'document_id'           => $documentProcess['document_id']['id']
                        ])
                        ->first();

                    // create statement lines
                    foreach($data['transactions'] as $txn) {

                        $communication = '';
                        $communication_type = 'free';

                        // when present, structured reference always prevails as communication
                        if(!empty($txn['structured_reference'])) {
                            $communication = $txn['structured_reference'];
                            if(preg_match('/^RF\d{2}[A-Z0-9]{1,21}$/', str_replace(' ', '', $communication))) {
                                $communication_type = 'RF';
                            }
                            elseif(preg_match('/^\+{3}\d{3}\/\d{4}\/\d{5}\+{3}$/', $communication)) {
                                $communication_type = 'VCS';
                            }
                            else {
                                $communication_type = 'SCOR';
                            }
                        }
                        elseif(!empty($txn['unstructured_reference'])) {
                            $communication = $txn['unstructured_reference'];
                        }

                        BankStatementLine::create([
                                'condo_id'                => $documentProcess['condo_id'],
                                'bank_statement_id'       => $bankStatement['id'],
                                'sequence_number'         => $txn['sequence_number'],
                                'date'                    => strtotime($txn['value_date']),
                                'amount'                  => round(floatval($txn['amount']), 2),
                                'account_iban'            => $txn['counterparty_iban'] ?? $data['account_iban'],
                                'account_holder'          => $txn['counterparty_name'] ?? null,
                                'communication'           => $communication,
                                'communication_type'      => $communication_type,
                                'status'                  => 'pending'
                            ]);
                    }

                    $logs[] = "Drafted bank statement {$bankStatement['id']}";
                    self::id($id)->update(['document_bank_statement_id' => $bankStatement['id'], 'has_target_object' => true]);
                }

            }
            catch(\Exception $e) {
                // unexpected error
                // unable to extract or confidence level too low
                $logs[] = "Drafting error : " . $e->getMessage();
                trigger_error("APP::unable to draft target object from document." . $e->getMessage(), EQ_REPORT_WARNING);
            }

            $report_html = $documentProcess['report_html'];
            if(strlen($report_html) > 0) {
                $report_html .= "<br />";
            }

            $values['report_html'] = $report_html . implode("<br />", $logs);

            self::id($id)->update($values);
        }
    }

    private static function computeLogsFromSchema(array $schema, array $data, string $prefix = '') {
        $logs = [];
        foreach($schema as $key => $definition) {
            $full_key = $prefix . $key;

            if(isset($definition['type']) && $definition['type'] === 'object' && isset($definition['properties'])) {
                if(isset($data[$key]) && is_array($data[$key])) {
                    $logs = array_merge($logs, self::computeLogsFromSchema($definition['properties'], $data[$key], $full_key . '.'));
                }
            }
            elseif(isset($definition['type']) && $definition['type'] === 'array') {
                if(!empty($data[$key]) && is_array($data[$key]) && isset($definition['items']['properties'])) {
                    foreach($data[$key] as $index => $item) {
                        if(is_array($item)) {
                            $logs = array_merge($logs, self::computeLogsFromSchema($definition['items']['properties'], $item, $full_key . "[$index]."));
                        }
                    }
                }
            }
            else {
                if(isset($data[$key]) && $data[$key] !== '') {
                    $depth = substr_count($full_key, '.');
                    $pad = str_repeat('-', 4 * $depth);
                    $logs[] = "{$pad}$full_key: '{$data[$key]}'";
                }
            }
        }
        return $logs;
    }

    protected static function calcDocumentLink($self) {
        $result = [];
        $self->read(['document_id']);
        foreach($self as $id => $invoice) {
            if($invoice['document_id']) {
                $result[$id] = '/document/' . $invoice['document_id'];
            }
        }
        return $result;
    }

    public static function canupdate($self) {
        $self->read(['status']);
        foreach($self as $id => $chart) {
            if($chart['status'] == 'integrated') {
                return ['status' => ['not_allowed' => 'Integrated document cannot be modified.']];
            }
        }
        return parent::canupdate($self);
    }

}
