<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace documents;

use documents\navigation\Node;
use eQual;
use equal\http\HttpRequest;
use equal\orm\Model;
use Exception;
use purchase\supplier\Supplier;
use realestate\property\Condominium;

class Document extends Model {

    public static function getLink() {
        return "/documents/#/document/object.id";
    }

    public static function constants() {
        return ['FMT_INSTANCE_TYPE', 'FMT_API_URL_EDMS'];
    }

    public static function getColumns() {
        return [
            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the document belongs to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'onupdate'          => 'onupdateCondoId'
            ],

            'ownership_id' => [
                'type'              => 'many2one',
                'description'       => "The ownership that the owner refers to.",
                'foreign_object'    => 'realestate\ownership\Ownership',
                'domain'            => ['condo_id', '=', 'object.condo_id']
            ],

            'supplier_id' => [
                'type'              => 'many2one',
                'description'       => "The supplier the document originates from.",
                'foreign_object'    => 'purchase\supplier\Supplier'
            ],

            'case_file_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'tracking\CaseFile',
                'description'       => 'Optional link to the related case file (incident, quote, etc.).',
            ],

            'email_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'communication\email\Email',
                'description'       => 'Email the document is an attachment of, if any.'
            ],

            'name' => [
                'type'              => 'string',
                'required'          => true
            ],

            'data' => [
                'type'              => 'binary',
                // #memo - prevent resetting after voiding local data
                // 'dependents'        => ['content_type', 'content_size', 'extension', 'readable_size', 'preview_image'],
                'onupdate'          => 'onupdateData'
            ],

            'has_document_json' => [
                'type'              => 'boolean',
                'description'       => 'Does the document have a JSON version of its content.',
                'default'           => false
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

            'document_json' => [
                'type'              => 'string',
                'usage'             => 'text/plain.medium',
                'description'       => 'Standard JSON descriptor of the document, using a schema matching the document_type_id.',
                'help'              => 'This field is meant to receive the result of the document parsing (whatever the method) and is used at the `completion` step for validating the completeness of the document.'
            ],

            'document_type_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'documents\DocumentType',
                'description'       => 'Document type associated with the document.',
                'dependents'        => ['document_type_code']
            ],

            'document_type_code' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'relation'          => ['document_type_id' => 'code'],
                'store'             => true,
                'instant'           => true
            ],

            'parent_node_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'documents\navigation\Node',
                'description'       => 'Node the document is linked with.',
                'domain'            => [['node_type', '=', 'folder'], ['condo_id', '=', 'object.condo_id']],
                'onupdate'          => 'onupdateParentNodeId'
            ],

            'node_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'documents\navigation\Node',
                'description'       => 'Node the document is linked with.',
                'domain'            => ['condo_id', '=', 'object.condo_id']
            ],

            'content_type' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'function'          => 'calcContentType',
                'store'             => true,
                'instant'           => true,
                'description'       => 'Content type of the document (from data).'
            ],

            'content_size' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'function'          => 'calcContentSize',
                'store'             => true,
                'instant'           => true,
                'dependents'        => ['readable_size'],
                'description'       => 'Size of the document, in octets (from data).'
            ],

            'extension' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'function'          => 'calcExtension',
                'store'             => true,
                'instant'           => true,
                'description'       => 'Filename extension of the document (from data).'
            ],

            'readable_size' => [
                'type'              => 'computed',
                'description'       => 'Readable size',
                'function'          => 'calcReadableSize',
                'result_type'       => 'string',
                'store'             => true,
                'instant'           => true,
                'readonly'          => true
            ],

            'uuid' => [
                'type'              => 'string',
                'usage'             => 'text/plain:36',
                // #memo - commented for testing
                // #todo - uncomment for PROD
                // 'unique'            => true,
                'description'       => 'Unique document identifier provided by EDMS'
            ],

            'link' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'usage'             => 'uri/url',
                'description'       => 'URL for visualizing the document.',
                'function'          => 'calcLink',
                'store'             => true,
                'readonly'          => true
            ],

            'lang_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'core\Lang',
                'description'       => 'Language used in the document.',
                'default'           => 1
            ],

            'category_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'documents\DocumentCategory',
                'description'       => 'Category of the document.',
                'default'           => 1
            ],

            'public' => [
                'type'              => 'boolean',
                'description'       => 'Accessibility of the document.',
                'default'           => false
            ],

            'preview_image' => [
                'type'              => 'computed',
                'result_type'       => 'binary',
                'usage'             => 'image/jpeg',
                'function'          => 'calcPreviewImage',
                'description'       => 'Thumbnail of the document.',
                'store'             => true
            ],

            'status' => [
                'type'              => 'string',
                'selection'         => [
                    'imported',
                    'pending',
                    'processed',
                    'ignored'
                ],
                'default'     => 'imported',
                'description' => 'Processing status of the document.'
            ],

            /*

            info relating to invoice document

            On utilise les infos contenues dans document_json pour compléter ces informations. Dans le cas où elles ne sont pas présentes, l'utilisateur peut les ajouter à la main.
            

            */

            'invoice_number' => [
                'type'              => 'string',
                'description'       => 'Does the invoice have a period.',
                'visible'           => ['document_type_code', '=', 'invoice']
            ],

            'invoice_type' => [
                'type'              => 'string',
                'description'       => 'Does the invoice have a period.',
                'selection'         => ['invoice', 'credit_note'],
                'visible'           => ['document_type_code', '=', 'invoice']
            ],

            'invoice_has_period' => [
                'type'              => 'boolean',
                'description'       => 'Does the invoice have a period.',
                'default'           => false,
                'visible'           => ['document_type_code', '=', 'invoice']
            ],

            'invoice_period_date_from' => [
                'type'              => 'date',
                'description'       => 'First date of invoice period.',
                'visible'           => ['document_type_code', '=', 'invoice']
            ],

            'invoice_period_date_to' => [
                'type'              => 'date',
                'description'       => 'Last date of invoice period.',
                'visible'           => ['document_type_code', '=', 'invoice']
            ],

            'invoice_issue_date' => [
                'type'              => 'date',
                'description'       => 'Last date of invoice period.',
                'visible'           => ['document_type_code', '=', 'invoice']
            ],

            'invoice_due_date' => [
                'type'              => 'date',
                'description'       => 'Last date of invoice period.',
                'visible'           => ['document_type_code', '=', 'invoice']
            ],

            'invoice_currency' => [
                'type'              => 'string',
                'description'       => 'Last date of invoice period.',
                'default'           => 'EUR',
                'visible'           => ['document_type_code', '=', 'invoice']
            ],

            'invoice_supplier_id' => [
                'type'              => 'date',
                'description'       => 'Last date of invoice period.',
                'visible'           => ['document_type_code', '=', 'invoice']
            ],

            'invoice_condo_id' => [
                'type'              => 'date',
                'description'       => 'Last date of invoice period.',
                'visible'           => ['document_type_code', '=', 'invoice']
            ],

            'invoice_condo_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\property\Condominium',
                'description'       => 'Last date of invoice period.',
                'visible'           => ['document_type_code', '=', 'invoice']
            ],

            'invoice_lines' => [
                'type'              => 'one2many',
                'foreign_object'    => 'documents\typing\DocumentInvoiceLine',
                'foreign_field'     => 'document_id',
                'description'       => 'Last date of invoice period.',
                'visible'           => ['document_type_code', '=', 'invoice']
            ],

            'invoice_total' => [
                'type'              => 'float',
                'usage'             => 'amount/money:2',
                'description'       => 'Total tax incl. of the invoice.',
                'visible'           => ['document_type_code', '=', 'invoice']
            ],

        ];
    }

    public static function getWorkflow() {
        return [
            'imported' => [
                'description' => 'Just imported document, waiting to be validated.',
                'icon'        => 'draw',
                'transitions' => [
                    'validate' => [
                        'description' => 'Update the document to `pending`.',
                        'policies'    => [],
                        'onbefore'    => 'onbeforeValidate',
                        'status'      => 'pending'
                    ]
                ]
            ],
            'pending' => [
                'description' => 'Validated document, waiting to be processed.',
                'icon'        => 'hourglass_top',
                'transitions' => [
                    'validate' => [
                        'description' => 'Update the document to `processed`.',
                        'policies'    => [],
                        'status'      => 'processed'
                    ]
                ]
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

    public static function getPolicies(): array {
        return [
            'can_perform_analysis' => [
                'description' => 'Verifies that a fiscal year can be opened according its configuration.',
                'function'    => 'policyCanPerformAnalysis'
            ]
        ];
    }

    public static function policyCanPerformAnalysis($self): array {
        $result = [];
        $self->read(['status', 'has_document_json']);
        foreach($self as $id => $document) {
            if($document['status'] != 'imported' || $document['has_document_json']) {
                $result[$id] = [
                    'invalid_status' => 'Document already has analysis data.'
                ];
                continue;
            }
        }
        return $result;
    }


    public static function doPerformAnalysis($self) {
        $self->read(['content_type', 'condo_id']);

        static $supported_content_types = [
                'application/pdf',
                'image/webp',
                'image/png',
                'image/jpg',
                'image/jpeg',
                'image/heic',
                'image/tiff',
                'image/tif'
            ];

        foreach($self as $id => $document) {

            if(!in_array($document['content_type'], $supported_content_types)) {
                continue;
            }

            $data = eQual::run('get', 'documents_analyze-mindee', ['id' => $id]);

            if(!isset($data['document']['inference']['prediction'])) {
                // invalid Mindee response
                trigger_error("APP::invalid Mindee response", EQ_REPORT_WARNING);
                continue;
            }

            try {
                $prediction = $data['document']['inference']['prediction'];

                $data = eQual::run('get', 'documents_parse-mindee', ['json' => json_encode($data['document']['inference']['prediction'])]);

                // attempt to enrich with additional data
                try {
                    $text = eQual::run('get', 'documents_extract-text', ['id' => $id]);
                    $info = eQual::run('get', 'documents_parse-text', ['text' => $text]);

                    if(!isset($data['customer']['customer_number']) && isset($info['customer_number'])) {
                        $data['customer']['customer_number'] = $info['customer_number'];
                    }

                    if(!isset($data['customer']['contract_number']) && isset($info['contract_number'])) {
                        $data['customer']['contract_number'] = $info['contract_number'];
                    }

                    if(!isset($data['customer']['installation_number']) && isset($info['installation_number'])) {
                        $data['customer']['installation_number'] = $info['installation_number'];
                    }

                    if(!isset($data['payment']['payment_id']) && isset($info['payment_id'])) {
                        $data['payment']['payment_id'] = $info['payment_id'];
                    }

                    if(!isset($data['payment']['iban']) && isset($info['iban'])) {
                        $data['payment']['iban'] = $info['iban'];
                    }

                    if(!isset($data['invoice_period']) && isset($info['period_start'], $info['period_end'])) {
                        $data['invoice_period'] = [
                            'start_date' => $info['period_start'],
                            'end_date'   => $info['period_end']
                        ];
                    }

                    $data['payment']['bic'] = self::computeBicFromIban($data['payment']['iban']);
                }
                catch(Exception $e) {
                    // ignore attempt failure
                    trigger_error("APP::unable to extract text from document", EQ_REPORT_WARNING);
                }

                $values = [
                        'has_document_json' => true,
                        'analysis_version'  => 'mindee_v4',
                        'analysis_json'     => json_encode($prediction, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'document_json'     => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
                    ];

                if(isset($document['condo_id'])) {
                    $values['condo_id'] = $document['condo_id'];
                }

                if(isset($data['document_type'])) {
                    $documentType = DocumentType::search(['code', '=', $data['document_type']])->first();
                    if($documentType) {
                        $values['document_type_id'] = $documentType['id'];
                        $values['document_type_code'] = $data['document_type'];
                    }
                }

                // attempt to retrieve supplier
                if(isset($data['supplier']['name'])) {
                    $supplier = Supplier::search(['legal_name', 'ilike', $data['supplier']['name'] . '%'])->first();
                    if($supplier) {
                        $values['supplier_id'] = $supplier['id'];
                        // attempt to retrieve condominium by number
                        if(!isset($values['condo_id']) && isset($data['customer']['number'])) {
                            // #todo
                        }
                    }
                }

                if(!isset($values['condo_id'])) {
                    // attempt to retrieve condominium by name
                    if(isset($data['customer']['name'])) {
                        $parts = explode(' ', trim($data['customer']['name'], " \n\r\t\v\0-_\/"));
                        $customer_name = implode(' ', array_filter($parts, function($a, $k) { return $k < 3 && !preg_match('/[^\p{L}\p{N}]/iu', $a); }, ARRAY_FILTER_USE_BOTH));
                        $condominium = Condominium::search(['legal_name', 'ilike', $customer_name . '%'])->first();
                        if($condominium) {
                            $values['condo_id'] = $condominium['id'];
                        }
                    }
                }

                if(isset($values['document_type_id'], $values['condo_id'])) {
                    // assign the folder
                    $documentType = DocumentType::id($values['document_type_id'])->read(['folder_code'])->first();
                    $node = Node::search([['condo_id', '=', $values['condo_id']], ['code', '=', $documentType['folder_code']]])->first();
                    if($node) {
                        $values['parent_node_id'] = $node['id'];
                    }
                }

                if(isset($data['invoice_period']['start_date'], $data['invoice_period']['end_date'])) {
                    $values['invoice_has_period'] = true;
                    $values['invoice_period_date_from'] = strtotime($data['invoice_period']['start_date']);
                    $values['invoice_period_date_to'] = strtotime($data['invoice_period']['end_date']);
                }

                // #memo - document_json is meant to receive either content from parsed Mindee or from parsed UBL
                self::id($id)->update($values);
            }
            catch(Exception $e) {
                // unable to extract or confidence level too low
                trigger_error("APP::unable to extract document, or confidence level too low.", EQ_REPORT_WARNING);
            }

        }

    }

    public static function onbeforeValidate($self) {
        $self->read(['document_json']);
        foreach($self as $id => $document) {
            $result = eQual::run('get', 'json-validate', ['json' => $document['document_json'], 'schema_id' => 'urn:fmt:json-schema:finance:purchase-invoice']);
            if(isset($result['errors']) && count($result['errors'])) {
                ob_start();
                print_r($result['errors']);
                $out = ob_get_clean();
                trigger_error('APP::unable to validate document: ' . $out, EQ_REPORT_INFO);
                throw new Exception('invalid_document_json', EQ_ERROR_INVALID_PARAM);
            }
        }
    }

    public static function onupdateParentNodeId($self) {
        $self->read(['name', 'parent_node_id', 'node_id', 'condo_id']);
        foreach($self as $id => $document) {
            if(!$document['node_id']) {
                Node::create([
                    'name'          => $document['name'],
                    'node_type'     => 'document',
                    'document_id'   => $id,
                    'condo_id'      => $document['condo_id']
                ])
                // #memo - triggers nodes_count update
                ->update(['parent_id' => $document['parent_node_id']]);
            }
            else {
                Node::id($document['node_id'])->update(['parent_id' => $document['parent_node_id']]);
            }
        }
    }

    public static function onupdateData($self, $adapt) {
        $instance_type = constant('FMT_INSTANCE_TYPE');
        if($instance_type === 'agency') {
            self::attemptToPush($self, $adapt);
        }
    }

    public static function onupdateCondoId($self, $adapt) {
        $instance_type = constant('FMT_INSTANCE_TYPE');
        if($instance_type === 'agency') {
            self::attemptToPush($self, $adapt);
        }
    }

    private static function attemptToPush($self, $adapt) {
        $instance_type = constant('FMT_INSTANCE_TYPE');
        if($instance_type === 'agency') {
            /** @var \equal\data\adapt\DataAdapter */
            $adapter = $adapt->get('json');

            $self->read(['condo_id', 'name', 'data']);
            foreach($self as $id => $document) {
                if(!$document['data']) {
                    continue;
                }
                if(!$document['condo_id']) {
                    continue;
                }

                // we need to relay document to EDMS in order to receive a UUID
                $url = constant('FMT_API_URL_EDMS');
                try {
                    $request = new HttpRequest('POST ' . $url . '?do=documents_push');
                    $response = $request
                        ->setBody([
                            'name'      => $document['name'],
                            'condo_id'  => $document['condo_id'],
                            'data'      => $adapter->adaptOut($document['data'], 'binary')
                        ])
                        ->send();
                    $result = $response->body();
                    if(isset($result['uuid'])) {
                        self::id($document['id'])
                            ->update([
                                // assign UUID
                                'uuid'          => $result['uuid'],
                                // remove local file (resets computed fields)
                                'data'          => null,
                                'content_type'  => $result['content_type'] ?? null,
                                'content_size'  => $result['content_size'] ?? null
                            ]);
                    }
                    else {
                        throw new \Exception('edms_response_without_uuid', EQ_ERROR_UNKNOWN);
                    }
                }
                catch(\Exception $e) {
                    trigger_error("APP:unable to store document on EDMS: ".$e->getMessage(), EQ_REPORT_ERROR);
                }
            }
        }
    }

    public static function calcLink($self) {
        $result = [];
        foreach($self as $id => $document) {
            $result[$id] = '/document/' . $id;
        }
        return $result;
    }

    public static function calcExtension($self) {
        $result = [];
        $self->read(['content_type']);

        foreach($self as $id => $document) {
            if(isset($document['content_type'])) {
                $result[$id] = self::computeExtensionFromType($document['content_type']);
            }
        }

        return $result;
    }

    public static function calcContentSize($self) {
        $result = [];
        $self->read(['data']);

        foreach($self as $id => $document) {
            if(isset($document['data'])) {
                $result[$id] = strlen($document['data'] ?? '');
            }
        }

        return $result;
    }

    public static function calcReadableSize($self) {
        $result = [];
        $self->read(['content_size']);
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        foreach($self as $id => $document) {
            $size = $document['content_size'];
            if($size) {
                $power = $size > 0 ? floor(log($size, 1024)) : 0;
                $result[$id] = number_format($size / pow(1024, $power), 1, '.', '') . ' ' . $units[$power];
            }
        }
        return $result;
    }

    public static function calcContentType($self) {
        $result = [];
        $self->read(['data']);

        foreach($self as $id => $document) {
            if(!isset($document['data'])) {
                continue;
            }
            try {
                $content = $document['data'] ?? '';

                // retrieve content_type from MIME
                $finfo = new \finfo(FILEINFO_MIME);

                $mime = $finfo->buffer($content);

                if($mime === false) {
                    throw new \Exception('missing_mime');
                }

                $content_type = explode(';', $mime)[0];

                if(empty($content_type)) {
                    throw new \Exception('invalid_mime');
                }

                $result[$id] = $content_type;

            }
            catch(\Exception $e) {
                // failed retrieving content type from content: ignore
            }

        }

        return $result;
    }

    /**
     * Retrieve and validate an extension from a content type and a filename.
     *
     * @param $content_type string  The content_type found for for the file.
     * @param $name string  (optional) The name of the file, if any.
     *
     * @return string | bool    In case of success, the extension is returned. If no extension matches the content type, the method returns false.
     */
    private static function computeExtensionFromType($content_type, $name = '') {

        static $map_extensions = [
            '3g2'   => 'video/3gpp2',
            '3gp'   => 'video/3gpp',
            '7z'    => 'application/x-7z-compressed',
            'aac'   => 'audio/aac',
            'ac3'   => 'audio/ac3',
            'ai'    => 'application/vnd.adobe.illustrator',
            'aif'   => 'audio/aiff',
            'aifc'  => 'audio/x-aiff',
            'aiff'  => 'audio/aiff',
            'apng'  => 'image/apng',
            'au'    => 'audio/basic',
            'avi'   => 'video/x-msvideo',
            'avif'  => 'image/avif',
            'bin'   => 'application/octet-stream',
            'bmp'   => 'image/bmp',
            'cdr'   => 'application/vnd.corel-draw',
            'cer'   => 'application/pkix-cert',
            'class' => 'application/java-vm',
            'cpt'   => 'application/mac-compactpro',
            'crl'   => 'application/pkix-crl',
            'crt'   => 'application/x-x509-ca-cert',
            'csr'   => 'application/pkcs10',
            'css'   => 'text/css',
            'csv'   => 'text/csv',
            'dcr'   => 'application/x-director',
            'der'   => 'application/x-x509-ca-cert',
            'dir'   => 'application/x-director',
            'dll'   => 'application/x-msdownload',
            'dms'   => 'application/octet-stream',
            'doc'   => 'application/msword',
            'docx'  => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'dot'   => 'application/msword',
            'dotx'  => 'application/vnd.openxmlformats-officedocument.wordprocessingml.template',
            'dvi'   => 'application/x-dvi',
            'dxr'   => 'application/x-director',
            'eml'   => 'message/rfc822',
            'eps'   => 'application/postscript',
            'exe'   => 'application/x-msdownload',
            'f4v'   => 'video/x-f4v',
            'flac'  => 'audio/flac',
            'flv'   => 'video/x-flv',
            'gif'   => 'image/gif',
            'gpg'   => 'application/pgp-encrypted',
            'gtar'  => 'application/x-gtar',
            'gz'    => 'application/gzip',
            'heic'  => 'image/heic',
            'heif'  => 'image/heif',
            'hqx'   => 'application/mac-binhex40',
            'htm'   => 'text/html',
            'html'  => 'text/html',
            'ical'  => 'text/calendar',
            'ico'   => 'image/vnd.microsoft.icon',
            'ics'   => 'text/calendar',
            'j2k'   => 'image/jp2',
            'jar'   => 'application/java-archive',
            'jp2'   => 'image/jp2',
            'jpe'   => 'image/jpeg',
            'jpeg'  => 'image/jpeg',
            'jpf'   => 'image/jpx',
            'jpg'   => 'image/jpeg',
            'jpg2'  => 'image/jp2',
            'jpm'   => 'image/jpm',
            'jpx'   => 'image/jpx',
            'js'    => 'application/javascript',
            'json'  => 'application/json',
            'kdb'   => 'application/octet-stream',
            'kml'   => 'application/vnd.google-earth.kml+xml',
            'kmz'   => 'application/vnd.google-earth.kmz',
            'lha'   => 'application/octet-stream',
            'log'   => 'text/plain',
            'lzh'   => 'application/octet-stream',
            'm3u'   => 'audio/x-mpegurl',
            'm4a'   => 'audio/mp4',
            'm4u'   => 'video/vnd.mpegurl',
            'mid'   => 'audio/midi',
            'midi'  => 'audio/midi',
            'mif'   => 'application/vnd.mif',
            'mj2'   => 'video/mj2',
            'mjp2'  => 'video/mj2',
            'mov'   => 'video/quicktime',
            'movie' => 'video/x-sgi-movie',
            'mp2'   => 'audio/mpeg',
            'mp3'   => 'audio/mpeg',
            'mp4'   => 'video/mp4',
            'mpe'   => 'video/mpeg',
            'mpeg'  => 'video/mpeg',
            'mpg'   => 'video/mpeg',
            'mpga'  => 'audio/mpeg',
            'oda'   => 'application/oda',
            'odc'   => 'application/vnd.oasis.opendocument.chart',
            'odf'   => 'application/vnd.oasis.opendocument.formula',
            'odg'   => 'application/vnd.oasis.opendocument.graphics',
            'odi'   => 'application/vnd.oasis.opendocument.image',
            'odm'   => 'application/vnd.oasis.opendocument.text-master',
            'odp'   => 'application/vnd.oasis.opendocument.presentation',
            'ods'   => 'application/vnd.oasis.opendocument.spreadsheet',
            'odt'   => 'application/vnd.oasis.opendocument.text',
            'ogg'   => 'application/ogg',
            'otc'   => 'application/vnd.oasis.opendocument.chart-template',
            'otf'   => 'application/x-font-otf',
            'otg'   => 'application/vnd.oasis.opendocument.graphics-template',
            'oth'   => 'application/vnd.oasis.opendocument.text-web',
            'oti'   => 'application/vnd.oasis.opendocument.image-template',
            'otp'   => 'application/vnd.oasis.opendocument.presentation-template',
            'ots'   => 'application/vnd.oasis.opendocument.spreadsheet-template',
            'ott'   => 'application/vnd.oasis.opendocument.text-template',
            'p10'   => 'application/pkcs10',
            'p12'   => 'application/x-pkcs12',
            'p7a'   => 'application/x-pkcs7-signature',
            'p7c'   => 'application/pkcs7-mime',
            'p7m'   => 'application/pkcs7-mime',
            'p7r'   => 'application/x-pkcs7-certreqresp',
            'p7s'   => 'application/pkcs7-signature',
            'pdf'   => 'application/pdf',
            'pem'   => 'application/x-pem-file',
            'pgp'   => 'application/pgp-encrypted',
            'php'   => 'application/x-httpd-php',
            'php3'  => 'application/x-httpd-php',
            'php4'  => 'application/x-httpd-php',
            'phps'  => 'application/x-httpd-php-source',
            'phtml' => 'application/x-httpd-php',
            'png'   => 'image/png',
            'ppt'   => 'application/vnd.ms-powerpoint',
            'pptx'  => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'ps'    => 'application/postscript',
            'psd'   => 'image/vnd.adobe.photoshop',
            'qt'    => 'video/quicktime',
            'ra'    => 'audio/x-pn-realaudio',
            'ram'   => 'audio/x-pn-realaudio',
            'rar'   => 'application/x-rar-compressed',
            'rm'    => 'application/vnd.rn-realmedia',
            'rpm'   => 'audio/x-pn-realaudio-plugin',
            'rsa'   => 'application/x-pkcs7',
            'rtf'   => 'application/rtf',
            'rtx'   => 'text/richtext',
            'rv'    => 'video/vnd.rn-realvideo',
            'sea'   => 'application/octet-stream',
            'shtml' => 'text/html',
            'sit'   => 'application/x-stuffit',
            'smi'   => 'application/smil',
            'smil'  => 'application/smil',
            'so'    => 'application/octet-stream',
            'srt'   => 'application/x-subrip',
            'sst'   => 'application/octet-stream',
            'svg'   => 'image/svg+xml',
            'swf'   => 'application/x-shockwave-flash',
            'tar'   => 'application/x-tar',
            'text'  => 'text/plain',
            'tgz'   => 'application/gzip',
            'tif'   => 'image/tiff',
            'tiff'  => 'image/tiff',
            'txt'   => 'text/plain',
            'vcf'   => 'text/vcard',
            'vlc'   => 'application/videolan',
            'vtt'   => 'text/vtt',
            'wav'   => 'audio/wav',
            'wbxml' => 'application/vnd.wap.wbxml',
            'webm'  => 'video/webm',
            'wma'   => 'audio/x-ms-wma',
            'wmlc'  => 'application/vnd.wap.wmlc',
            'wmv'   => 'video/x-ms-wmv',
            'word'  => 'application/msword',
            'xht'   => 'application/xhtml+xml',
            'xhtml' => 'application/xhtml+xml',
            'xl'    => 'application/excel',
            'xls'   => 'application/vnd.ms-excel',
            'xlsx'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'xml'   => 'application/xml',
            'xsl'   => 'application/xml',
            'xspf'  => 'application/xspf+xml',
            'z'     => 'application/x-compress',
            'zip'   => 'application/zip',
            'zsh'   => 'application/x-zsh'
        ];

        static $map_mime_commons = [
            'image/jpeg'                => 'jpg',
            'image/pjpeg'               => 'jpg',
            'image/png'                 => 'png',
            'image/gif'                 => 'gif',
            'image/webp'                => 'webp',
            'image/avif'                => 'avif',
            'image/tiff'                => 'tiff',
            'image/bmp'                 => 'bmp',
            'image/x-ms-bmp'            => 'bmp',
            'image/heif'                => 'heic',
            'image/heic'                => 'heic',
            'image/svg+xml'             => 'svg',
            'image/vnd.microsoft.icon'  => 'ico',
            'image/x-icon'              => 'ico',
            'image/x-adobe-dng'         => 'dng',
            'application/pdf'           => 'pdf',
            'application/msword'        => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel'  => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/vnd.ms-powerpoint' => 'ppt',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            'text/plain'                => 'txt',
            'text/csv'                  => 'csv',
            'text/html'                 => 'html',
            'application/rtf'           => 'rtf',
            'application/xml'           => 'xml',
            'application/json'          => 'json',
            'application/vnd.oasis.opendocument.text' => 'odt',
            'application/vnd.oasis.opendocument.spreadsheet' => 'ods',
            'application/vnd.oasis.opendocument.presentation' => 'odp',
            'application/zip'           => 'zip',
            'application/x-7z-compressed' => '7z',
            'application/x-rar-compressed' => 'rar',
            'application/x-tar'         => 'tar',
            'application/gzip'          => 'gz',
            'application/postscript'    => 'eps',
        ];

        // check against most common MIMEs
        if(isset($map_mime_commons[$content_type])) {
            return $map_mime_commons[$content_type];
        }

        // if $name holds an extension, check if the given content_type matches a valid MIME
        if(strlen($name)) {
            $extension = strtolower( ( ($n = strrpos($name, ".")) === false) ? "" : substr($name, $n + 1) );
            if(strlen($extension) && isset($map_extensions[$extension])) {
                if($map_extensions[$extension] == $content_type) {
                    return $extension;
                }
            }
        }
        // fallback to the first extension that matches $content_type
        foreach($map_extensions as $extension => $mime) {
            if($mime == $content_type) {
                return $extension;
            }
        }
        // unknown or invalid content-type
        return false;
    }

    /**
     * Generate the preview image.
     *
     * By convention, generated thumbnail is always a JPEG image.
     *
     */
    public static function calcPreviewImage($self) {
        $result = [];
        $target_width = 150;
        $target_height = 150;
        $self->read(['name', 'content_type', 'data']);
        foreach($self as $id => $document) {
            if(!$document['content_type']) {
                continue;
            }
            try {
                if(!$document['data'] || substr($document['content_type'], 0, 5) != 'image') {
                    throw new Exception('not_an_image');
                }

                $parts = explode('/', $document['content_type']);

                if(count($parts) < 2) {
                    throw new Exception('invalid_content_type');
                }

                $image_type = strtolower($parts[1]);

                if(!in_array($image_type, ['avif', 'apng', 'bmp', 'png', 'gif', 'jpeg', 'svg+xml', 'webp', 'x-icon'])) {
                    throw new Exception('non_supported_format');
                }

                $src_image = imagecreatefromstring($document['data']);

                if(!$src_image) {
                    throw new Exception('malformed_image_data');
                }

                $src_width = imageSX($src_image);
                $src_height = imageSY($src_image);

                $dst_image = imagecreatetruecolor($target_width, $target_height);

                // preserve transparency
                if ($image_type == 'png' || $image_type == 'gif') {
                    imagealphablending($dst_image, false);
                    imagesavealpha($dst_image, true);
                    $transparent = imagecolorallocatealpha($dst_image, 255, 255, 255, 127);
                    imagefilledrectangle($dst_image, 0, 0, $target_width, $target_height, $transparent);
                }
                else {
                    // fill background with white for non-transparent images
                    $white = imagecolorallocate($dst_image, 255, 255, 255);
                    imagefilledrectangle($dst_image, 0, 0, $target_width, $target_height, $white);
                }

                if( ($src_width / $src_height) < ($target_width / $target_height) ) {
                    $new_height = $target_height;
                    $new_width  = $src_width * $target_height / $src_height;
                }
                else {
                    $new_height = $src_height * $target_width / $src_width;
                    $new_width  = $target_width;
                }

                $offset_x  = round( ($target_width - $new_width) / 2 );
                $offset_y  = round( ($target_height - $new_height) / 2 );
                imagecopyresampled($dst_image, $src_image, $offset_x, $offset_y, 0, 0, $new_width, $new_height, $src_width, $src_height);

                // get binary value of generated image
                ob_start();
                imagejpeg($dst_image, null, 80);
                $buffer = ob_get_clean();

                // free mem
                imagedestroy($dst_image);
                imagedestroy($src_image);

                $result[$id] = $buffer;
            }
            // non-supported image type or non-image document: fallback to hardcoded default thumbnail
            catch(Exception $e) {
                trigger_error("APP:unable to generate dynamic thumbnail: " . $e->getMessage(), EQ_REPORT_INFO);
                $found = false;
                $extension = self::computeExtensionFromType($document['content_type'], $document['name']);
                if($extension) {
                    $filename = EQ_BASEDIR . '/packages/documents/assets/img/extensions/'.$extension.'.jpg';
                    if(is_file($filename)) {
                        $found = true;
                        $result[$id] = file_get_contents($filename);
                    }
                }
                if(!$found) {
                    $filename = EQ_BASEDIR . '/packages/documents/assets/img/extensions/unknown.jpg';
                    $result[$id] = file_get_contents($filename);
                }
            }
        }
        return $result;
    }

    public static function onchange($event, $values) {
        $result = [];

        if(isset($event['data']['name'])) {
            $result['name'] = $event['data']['name'];
        }

        return $result;
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
