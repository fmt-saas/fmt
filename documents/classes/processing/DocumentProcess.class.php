<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace documents\processing;
use equal\orm\Model;
use documents\Document;
use documents\DocumentType;
use purchase\supplier\Supplier;
use purchase\supplier\SuppliershipReference;
use realestate\property\Condominium;

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
                'domain'            => ['condo_id', '=', 'object.condo_id']
            ],

            'analysis_version' => [
                'type'              => 'string',
                'description'       => 'Provider and version of the API used for the document analysis.'
            ],

            'analysis_json' => [
                'type'              => 'string',
                'usage'             => 'text/plain.medium',
                'description'       => 'JSON result of the document analysis.',
                'help'              => 'This field is meant to receive the result of the document parsing (whatever the method) and might remain empty (depending on the feeding strategy associated to the document type).'
            ],

            'has_analysis_json' => [
                'type'              => 'boolean',
                'description'       => 'Does the document have a JSON version of its content.',
                'default'           => false
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

            'supplier_id' => [
                'type'              => 'many2one',
                'description'       => "The supplier the document originates from.",
                'foreign_object'    => 'purchase\supplier\Supplier',
                'onupdate'          => 'onupdateSupplierId',
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
                'help'              => 'Indicates how the identity data was obtained.',
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
                    'integrated'
                ],
                'default'           => 'created'
            ],

            /*

                info relating to invoice document
                links to possible documents being processed
                list depends on DocumentTypes
                which must be associated to a valid JSON schema ()

                On utilise les infos contenues dans document_json pour compléter ces informations.
                Dans le cas où elles ne sont pas présentes, l'utilisateur peut les ajouter à la main.



            */

            'document_invoice_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\purchase\accounting\invoice\Invoice',
                'visible'           => ['document_type_code', '=', 'invoice']
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
                        'policies'    => ['is_complete'],
                        'status'      => 'completed'
                    ]
                ]
            ],
            'completed' => [
                'description' => 'Completed document, waiting to be validated.',
                'icon'        => 'done',
                'transitions' => [
                    'validate' => [
                        'description' => 'Update the document to `validated`.',
                        'policies'    => ['is_valid'],
                        'status'      => 'validated'
                    ]
                ]
            ],
            'validated' => [
                'description' => 'Validated document, waiting to be processed.',
                'icon'        => 'edit',
                'transitions' => [
                    'record' => [
                        'description' => 'Update the document to `recorded`.',
                        'policies'    => [],
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
                        'policies'    => [],
                        'status'      => 'confirmed'
                    ]
                ]
            ],
            'confirmed' => [
                'description' => 'Recorded document, waiting to be integrated.',
                'icon'        => 'edit',
                'transitions' => [
                    'integrate' => [
                        'description' => 'Update the document to `integrated`.',
                        'policies'    => [],
                        'status'      => 'integrated'
                    ]
                ]
            ]
        ];
    }

    public static function getPolicies(): array {
        return [
            'can_perform_analysis' => [
                'description' => 'Verifies that a fiscal year can be opened according its configuration.',
                'function'    => 'policyCanPerformAnalysis'
            ],
            'is_complete' => [
                'description' => 'Verifies that a fiscal year can be opened according its configuration.',
                'function'    => 'policyIsComplete'
            ],
            'is_valid' => [
                'description' => 'Verifies that a fiscal year can be opened according its configuration.',
                'function'    => 'policyIsValid'
            ]
        ];
    }

    public static function getActions() {
        return array_merge(parent::getActions(), [
            'perform_analysis' => [
                'description'   => 'Attempt to retrieve meta info of the document.',
                'policies'      => ['can_perform_analysis'],
                'function'      => 'doPerformAnalysis'
            ]
        ]);
    }

    public static function policyCanPerformAnalysis($self): array {
        $result = [];
        $self->read(['status', 'document_id' => ['has_document_json']]);
        foreach($self as $id => $documentProcess) {
            if($documentProcess['status'] != 'created' || $documentProcess['document_id']['has_document_json']) {
                $result[$id] = [
                    'invalid_status' => 'Document already has analysis data.'
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
                    continue;
                }
            }
            catch(\Exception $e) {
                trigger_error("APP::unable to validate JSON :" . $e->getMessage(), EQ_REPORT_WARNING);
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
        // as a first draft, we use the same check as for completeness
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
                    continue;
                }
            }
            catch(\Exception $e) {
                trigger_error("APP::unable to validate JSON :" . $e->getMessage(), EQ_REPORT_WARNING);
            }
        }
        return $result;
    }

    /**
     * Create the proforma accounting resource based on Document type and JSON data.
     */
    public static function onbeforeRecord($self) {

    }

    /**
     * This method is used to create the document based on received data, and start the processing.
     */
    public static function onupdateData($self) {
        $self->read(['name', 'data']);
        foreach($self as $id => $documentProcess) {
            $document = Document::create(['name' => $documentProcess['name'], 'data' => $documentProcess['data']])->first();
            self::id($id)->update(['document_id' => $document['id']]);
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
                Document::id($documentProcess['document_id'])->update(['document_type_id' => $documentProcess['document_type_id']]);
            }
        }
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
     * Auto analysis/completion of the document meta data.
     */
    public static function doPerformAnalysis($self) {
        $self->read(['document_id' => ['id', 'content_type'], 'condo_id', 'supplier_id', 'report_html']);

        static $supported_content_types = [
                'application/pdf',
                'image/webp',
                'image/png',
                'image/jpg',
                'image/jpeg'
            ];

        foreach($self as $id => $documentProcess) {
            if(!$documentProcess['document_id']) {
                continue;
            }
            if(!in_array($documentProcess['document_id']['content_type'], $supported_content_types)) {
                continue;
            }

            $logs = [];

            try {
                $data = \eQual::run('get', 'documents_analyze-mindee', ['id' => $documentProcess['document_id']['id']]);
                $logs[] = "data retrieved from document analysis";
            }
            catch(\Exception $e) {
                // unable to extract or confidence level too low
                trigger_error("APP::unable to perform document analysis: " . $e->getMessage(), EQ_REPORT_WARNING);
                continue;
            }

            if(!isset($data['document']['inference']['prediction'])) {
                // invalid Mindee response
                trigger_error("APP::invalid Mindee response", EQ_REPORT_WARNING);
                continue;
            }

            try {
                $prediction = $data['document']['inference']['prediction'];

                $data = \eQual::run('get', 'documents_parse-mindee', ['json' => json_encode($data['document']['inference']['prediction'])]);

                // attempt to enrich with additional data
                try {
                    $text = \eQual::run('get', 'documents_extract-text', ['id' => $id]);
                    $info = \eQual::run('get', 'documents_parse-text', ['text' => $text]);

                    if(!isset($data['customer']['customer_number']) && isset($info['customer_number'])) {
                        $data['customer']['customer_number'] = $info['customer_number'];
                        $logs[] = "customer_number: {$info['customer_number']} - retrieved from parsing text ";
                    }

                    if(!isset($data['customer']['contract_number']) && isset($info['contract_number'])) {
                        $data['customer']['contract_number'] = $info['contract_number'];
                        $logs[] = "contract_number: {$info['contract_number']} - retrieved from parsing text ";
                    }

                    if(!isset($data['customer']['installation_number']) && isset($info['installation_number'])) {
                        $data['customer']['installation_number'] = $info['installation_number'];
                        $logs[] = "installation_number: {$info['installation_number']} - retrieved from parsing text ";
                    }

                    if(!isset($data['payment']['payment_id']) && isset($info['payment_id'])) {
                        $data['payment']['payment_id'] = $info['payment_id'];
                    }

                    if(!isset($data['payment']['iban']) && isset($info['iban'])) {
                        $data['payment']['iban'] = $info['iban'];
                        $logs[] = "iban: {$info['iban']} - retrieved from parsing text ";
                    }

                    if(!isset($data['invoice_period']) && isset($info['period_start'], $info['period_end'])) {
                        $data['invoice_period'] = [
                            'start_date' => $info['period_start'],
                            'end_date'   => $info['period_end']
                        ];
                        $logs[] = "invoice_period: {$info['period_start']}-{$info['period_end']} - retrieved from parsing text ";
                    }

                    $data['payment']['bic'] = self::computeBicFromIban($data['payment']['iban']);
                    $logs[] = "bic: {$data['payment']['bic']} - retrieved from IBAN";
                }
                catch(\Exception $e) {
                    // ignore attempt failure
                    trigger_error("APP::unable to extract text from document. Error: " . $e->getMessage(), EQ_REPORT_WARNING);
                }

                $values = [
                        'condo_id'          => $documentProcess['condo_id'],
                        'supplier_id'       => $documentProcess['supplier_id'],
                        'analysis_version'  => 'mindee_v4',
                        'analysis_json'     => json_encode($prediction, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'has_analysis_json' => true
                    ];

                // #memo - document_json is meant to receive a final content according to schema and independent from origin (ex.: parsed Mindee, parsed UBL, ...)
                $doc_values = [
                        'has_document_json' => true,
                        'document_json'     => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
                    ];

                if(isset($data['document_type'])) {
                    $documentType = DocumentType::search(['code', '=', $data['document_type']])->first();
                    if($documentType) {
                        $values['document_type_id'] = $documentType['id'];
                    }
                }

                // attempt to retrieve supplier
                if(isset($data['supplier']['name'])) {
                    $suppliers_ids = Supplier::search(['legal_name', 'ilike', $data['supplier']['name'] . '%'])->ids();
                    if(count($suppliers_ids)) {
                        $values['supplier_id'] = current($suppliers_ids);
                        $logs[] = "supplier_id retrieved from {$data['supplier']['name']}";
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
                        $reference = SuppliershipReference::search([
                                ['supplier_id', '=', $values['supplier_id']],
                                ['reference_type', '=', $ref],
                                ['reference_value', '=', $data['customer'][$ref]]
                            ])
                            ->read(['condo_id'])
                            ->first();

                        if($reference) {
                            $values['condo_id'] = $reference['condo_id'];
                            $logs[] = "condo_id retrieved from $ref {$data['customer'][$ref]}";
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
                            $logs[] = "condo_id retrieved from {$customer_name}";
                        }
                    }
                }

                Document::id($documentProcess['document_id']['id'])->update($doc_values);

                $report_html = $documentProcess['report_html'];
                if(strlen($report_html) > 0) {
                    $report_html .= "\n";
                }
                $values['report_html'] = $report_html . implode("\n", $logs);

                self::id($id)->update($values);
            }
            catch(\Exception $e) {
                // unable to extract or confidence level too low
                trigger_error("APP::unable to extract document, or confidence level too low.", EQ_REPORT_WARNING);
            }

        }

    }

    private static function computeBicFromIban($iban) {
        static $map_bic;
        $result = null;

        if(!$iban) {
            return null;
        }

        $country = substr($iban, 0, 2);
        $bank_code = substr($iban, 4, 3);

        if(!$map_bic) {
            $file = EQ_BASEDIR . "/packages/identity/i18n/en/bic/{$country}.json";
            if(file_exists($file)) {
                $data = file_get_contents($file);
                $map_bic = json_decode($data, true);
            }
        }

        $result = $map_bic[$bank_code]['bic'] ?? null;

        return $result;
    }

}