<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

namespace documents;

use documents\navigation\Node;
use documents\processing\DocumentProcess;
use equal\http\HttpRequest;
use equal\orm\Model;
use identity\User;
use purchase\supplier\Suppliership;

class Document extends Model {

    public static function getLink() {
        return "/documents/#/document/object.id";
    }

    public static function constants() {
        return ['ENV_MODE', 'FMT_INSTANCE_TYPE', 'FMT_API_URL_EDMS'];
    }

    public static function getColumns() {
        return [
            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the document belongs to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'onupdate'          => 'onupdateCondoId'
            ],

            'uuid' => [
                'type'              => 'string',
                'usage'             => 'text/plain:36',
                // #memo - commented for testing because items are on the same instance
                // #todo - uncomment for PROD
                // 'unique'            => true,
                'description'       => 'Unique identifier from the Master instance.'
            ],

            'ownership_id' => [
                'type'              => 'many2one',
                'description'       => "The ownership that the document relates to, if any.",
                'foreign_object'    => 'realestate\ownership\Ownership',
                'domain'            => ['condo_id', '=', 'object.condo_id']
            ],

            'supplier_id' => [
                'type'              => 'many2one',
                'description'       => "The supplier the document originates from.",
                'foreign_object'    => 'purchase\supplier\Supplier',
                'dependents'        => ['suppliership_id'],
            ],

            'suppliership_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'purchase\supplier\Suppliership',
                'description'       => "The supplier the document originates from.",
                'function'          => 'calcSuppliershipId',
                'store'             => true
            ],

            'name' => [
                'type'              => 'string',
                'required'          => true
            ],

            'data' => [
                'type'              => 'binary',
                // #memo - prevent resetting after voiding local data
                'dependents'        => ['hash', 'content_type', 'content_size', 'extension', 'readable_size', 'preview_image']
            ],

            'document_process_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'documents\processing\DocumentProcess',
                'description'       => 'Processing Job of the document, if any.',
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null]]
            ],

            'has_document_json' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'description'       => 'Does the document have a JSON version of its content.',
                'store'             => false,
                'function'          => 'calcHasDocumentJson'
            ],

            'document_json' => [
                'type'              => 'string',
                'usage'             => 'text/json.medium',
                'description'       => 'Standard JSON descriptor of the document, using a schema matching the document_type_id.',
                'help'              => 'This field is meant to receive the result of the document parsing (whatever the method) and is used at the `completion` step for validating the completeness of the document.'
            ],

            'has_analysis_json' => [
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
                'usage'             => 'text/json.medium',
                'description'       => 'JSON result of the document analysis.',
                'help'              => 'This field is meant to receive the result of the document parsing (whatever the method) and might remain empty (depending on the feeding strategy associated to the document type).'
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
                'description'       => 'Document subtype associated with the document, if any.',
                'domain'            => ['document_type_id', '=', 'object.document_type_id'],
                'dependents'        => ['document_subtype_code']
            ],

            'document_type_code' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'relation'          => ['document_type_id' => 'code'],
                'store'             => true,
                'instant'           => true
            ],

            'document_subtype_code' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'relation'          => ['document_subtype_id' => 'code'],
                'store'             => true,
                'instant'           => true
            ],

            'parent_node_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'documents\navigation\Node',
                'description'       => 'Parent Node the document-node should be linked with.',
                'help'              => 'This is a virtual field used for creating a Node for the document when necessary (according to document_type_id).',
                'domain'            => [['node_type', '=', 'folder'], ['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null]],
                'onupdate'          => 'onupdateParentNodeId'
            ],

            'node_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'documents\navigation\Node',
                'description'       => 'Node the document is linked with.',
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null]]
            ],

            'document_visibility' => [
                'type'              => 'string',
                'selection'         => [
                    'public',       // visible to all condo owners + syndic
                    'protected',    // visible only to syndic
                    'private'       // visible only a single owner (to which the document is linked) + syndic
                ],
                'default'           => 'public',
                'description'       => 'Defines who can access the document.',
                'help'              => 'This field is synchronized with the node and updates automatically when the parent node visibility changes.'
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

            'hash' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'usage'             => 'text/plain:32',
                'store'             => true,
                'instant'           => true,
                'function'          => 'calcHash',
                'description'       => 'MD5 hash of the document.'
            ],

            'hash_sha256' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'usage'             => 'text/plain:64',
                'function'          => 'calcHashSha256',
                'description'       => 'SHA256 hash of the document.',
                'help'              => 'This field holds the hexadecimal value of the hash and might require a conversion to base64 for exchanges.',
                'store'             => true,
                'readonly'          => true
            ],

            'link' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'usage'             => 'uri/url.relative',
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

            'signed_document_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'documents\Document',
                'description'       => 'Targets the final printable version of the document.',
                'help'              => 'Optional version of the document with signatures on it, applicable for signed documents only. Has no legal value.'
            ],

            'document_signatures_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'documents\DocumentSignature',
                'foreign_field'     => 'document_id',
                'description'       => 'Signatures applied on the document, if any.',
            ],

            'is_origin' => [
                'type'              => 'boolean',
                'default'           => false,
                'readonly'          => true,
                'description'       => 'Document is the origin of the processing of the referenced accounting document.',
                'help'              => 'Document is a primary attachment. The binary data has been imported or generated and is linked to an accounting document (invoice, bank statement, ...).',
            ],

            'is_source' => [
                'type'              => 'boolean',
                'default'           => false,
                'readonly'          => true,
                'description'       => 'Document is the source of an accounting document (but not necessarily the origin).',
                'help'              => 'The document might have been created in order to provide a reference holding a consistent binary data, that was used to generate the accounting document that references it back as source.'
            ],

            'origin_document_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'documents\Document',
                'description'       => 'Targets the final printable version of the document.',
                'help'              => 'Optional version of the document with signatures on it, applicable for signed documents only. Has no legal value.',
                'visible'           => ['is_source', '=', true]
            ],

            /* fields below serve as link between the document and the entity it originates from, independently from its type, and are mutually exclusive */

            'case_file_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'tracking\CaseFile',
                'description'       => 'Optional link to the related case file (incident, quote, etc.).',
            ],

            'email_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'communication\email\Email',
                'description'       => 'Optional link to the received Email the document is an attachment of, if any.'
            ],

            /*
            // #memo - those info can be retrieved through document_process_id if necessary
            'purchase_invoice_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\purchase\accounting\invoice\PurchaseInvoice',
                'description'       => 'Optional link to the related purchase invoice.',
                'visible'           => ['document_type_code', '=', 'invoice']
            ],

            'bank_statement_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\bank\BankStatement',
                'description'       => 'Optional link to the related bank statement.',
                'visible'           => ['document_type_code', '=', 'bank_statement']
            ],
            */

            'expense_statement_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\funding\ExpenseStatement',
                'description'       => 'The fund request execution targeted by the funding, if any.',
                'help'              => 'As a convention, this field is set when a funding relates to a fund request. Fund request executions are sale invoices (with invoice_type set to fund_request).',
                'visible'           => ['funding_type', '=', 'expense_statement'],
                'readonly'          => true
            ],

            'fund_request_execution_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\funding\FundRequestExecution',
                'description'       => 'The fund request execution targeted by the funding, if any.',
                'help'              => 'As a convention, this field is set when a funding relates to a fund request. Fund request executions are sale invoices (with invoice_type set to fund_request).',
                'visible'           => ['funding_type', '=', 'fund_request'],
                'readonly'          => true
            ],

            'misc_operation_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\MiscOperation',
                'description'       => 'Miscellaneous operation targeted by the funding, if any.',
                'help'              => 'This is for the unexpected movements, for which the Funding was created at bank statement line reconcile.',
                'readonly'          => true,
                'visible'           => ['funding_type', '=', 'misc'],
            ],


            'ownership_transfer_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\property\OwnershipTransfer',
                'description'       => 'Optional link to the related bank statement.',
                'visible'           => ['document_type_code', '=', 'ownership_transfer_correspondence']
            ],

            // #todo - handle general_assembly_minutes

            'ownership_transfer_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'realestate\property\OwnershipTransfer',
                'foreign_field'     => 'attached_documents_ids',
                'rel_table'         => 'realestate_ownership_transfer_rel_documents',
                'rel_foreign_key'   => 'transfer_id',
                'rel_local_key'     => 'document_id',
                'domain'            => ['condo_id', '=', 'object.condo_id'],
                'description'       => 'Ownership transfers for which the document is selected as attachment.'
            ],

            'assembly_items_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'realestate\governance\AssemblyItem',
                'foreign_field'     => 'documents_ids',
                'rel_table'         => 'realestate_governance_assembly_item_rel_document',
                'rel_foreign_key'   => 'assembly_item_id',
                'rel_local_key'     => 'document_id',
                'description'       => "The assembly items that refer to the document.",
                'domain'            => ['condo_id', '=', 'object.condo_id']
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

    public static function getPolicies(): array {
        return [
            'can_start_processing' => [
                'description' => 'Verifies that the state of the processing allows drafting.',
                'function'    => 'policyCanStartProcessing'
            ]
        ];
    }

    public static function getActions() {
        return [
            'start_processing' => [
                'description'   => 'Attempt to identity document type and subtype.',
                'policies'      => ['can_start_processing'],
                'function'      => 'doStartProcessing'
            ]
        ];
    }

    protected static function policyCanStartProcessing($self) {
        $result = [];
        $self->read(['document_process_id']);
        foreach($self as $id => $document) {
            if(isset($document['document_process_id'])) {
                $result[$id] = [
                    'already_processing' => 'Document is already attached to a processing job.'
                ];
                continue;
            }
        }
        return $result;
    }

    protected static function doStartProcessing($self, $auth) {
        $self->read(['condo_id', 'name']);
        $user = User::id($auth->userId())->read(['employee_id'])->first();

        foreach($self as $id => $document) {
            DocumentProcess::create([
                    'condo_id'              => $document['condo_id'] ?? null,
                    'name'                  => $document['name'],
                    'assigned_employee_id'  => $user['employee_id'] ?? null
                ])
                // #memo - this will sync back the document_process_id
                ->update(['document_id' => $id]);
            self::id($id)->update(['is_origin' => true]);
        }
    }

    protected static function onupdateDocumentTypeId($self) {
        $self->read(['condo_id', 'document_type_id']);
        foreach($self as $id => $document) {
            if(isset($document['document_type_id'], $document['condo_id'])) {
                // assign the folder
                $documentType = DocumentType::id($document['document_type_id'])->read(['folder_code'])->first();
                $node = Node::search([['condo_id', '=', $document['condo_id']], ['code', '=', $documentType['folder_code']]])->first();
                if($node) {
                    self::id($id)->update(['parent_node_id' => $node['id']]);
                }
            }
        }
    }

    protected static function onupdateParentNodeId($self) {
        $self->read(['name', 'parent_node_id', 'node_id', 'condo_id']);
        foreach($self as $id => $document) {
            if(!$document['node_id']) {
                $node = Node::create([
                        'name'          => $document['name'],
                        'node_type'     => 'document',
                        'document_id'   => $id,
                        'condo_id'      => $document['condo_id']
                    ])
                    // #memo - triggers nodes_count update
                    ->update(['parent_id' => $document['parent_node_id']])
                    ->first();

                self::id($id)->update(['node_id' => $node['id']]);
            }
            else {
                Node::id($document['node_id'])->update(['parent_id' => $document['parent_node_id']]);
            }
        }
    }

    protected static function onupdateCondoId($self, $adapt) {
        $self->read(['condo_id', 'document_type_id', 'parent_node_id']);
        foreach($self as $id => $document) {
            if(!$document['parent_node_id'] && isset($document['document_type_id'], $document['condo_id'])) {
                // assign the folder
                $documentType = DocumentType::id($document['document_type_id'])->read(['folder_code'])->first();
                $node = Node::search([['condo_id', '=', $document['condo_id']], ['code', '=', $documentType['folder_code']]])->first();
                if($node) {
                    self::id($id)->update(['parent_node_id' => $node['id']]);
                }
            }
        }
    }

    protected static function calcSuppliershipId($self) {
        $result = [];
        $self->read(['condo_id', 'supplier_id']);
        foreach($self as $id => $document) {
            // find suppliership
            $suppliership = Suppliership::search([
                    ['condo_id', '=', $document['condo_id']],
                    ['supplier_id', '=',  $document['supplier_id']]
                ])
                ->first();

            if($suppliership) {
                $result[$id] = $suppliership['id'];
            }
        }
        return $result;
    }

    protected static function calcHash($self) {
        $result = [];
        $self->read(['data']);
        foreach($self as $id => $document) {
            if(empty($document['data'])) {
                continue;
            }
            $result[$id] = md5($document['data']);
        }
        return $result;
    }

    protected static function calcHashSha256($self) {
        $result = [];
        $self->read(['data']);
        foreach($self as $id => $document) {
            if(isset($document['data'])) {
                $result[$id] = hash('sha256', $document['data']);
            }
        }
        return $result;
    }

    protected static function calcLink($self) {
        $result = [];
        foreach($self as $id => $document) {
            $result[$id] = '/document/' . $id;
        }
        return $result;
    }

    protected static function calcExtension($self) {
        $result = [];
        $self->read(['content_type']);

        foreach($self as $id => $document) {
            if(isset($document['content_type'])) {
                $result[$id] = self::computeExtensionFromType($document['content_type']);
            }
        }

        return $result;
    }

    protected static function calcContentSize($self) {
        $result = [];
        $self->read(['data']);

        foreach($self as $id => $document) {
            if(isset($document['data'])) {
                $result[$id] = strlen($document['data'] ?? '');
            }
        }

        return $result;
    }

    protected static function calcReadableSize($self) {
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

    protected static function calcContentType($self) {
        $result = [];
        $self->read(['data']);

        foreach($self as $id => $document) {
            if(!isset($document['data'])) {
                continue;
            }
            try {
                $content = $document['data'] ?? '';

                // retrieve content_type from MIME
                $finfo = new \finfo(FILEINFO_MIME_TYPE);

                $mime = $finfo->buffer($content);

                if(empty($mime)) {
                    $result[$id] = 'application/octet-stream';
                    continue;
                }

                $result[$id] = $mime;

            }
            catch(\Exception $e) {
                // failed retrieving content type from content: ignore
            }

        }

        return $result;
    }

    protected static function calcHasDocumentJson($self) {
        $result = [];
        $self->read(['document_json']);
        foreach($self as $id => $document) {
            $result[$id] = ($document['document_json'] && strlen($document['document_json']) > 0);
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
                    throw new \Exception('not_an_image');
                }

                $parts = explode('/', $document['content_type']);

                if(count($parts) < 2) {
                    throw new \Exception('invalid_content_type');
                }

                $image_type = strtolower($parts[1]);

                if(!in_array($image_type, ['avif', 'apng', 'bmp', 'png', 'gif', 'jpeg', 'svg+xml', 'webp', 'x-icon'])) {
                    throw new \Exception('non_supported_format');
                }

                $src_image = imagecreatefromstring($document['data']);

                if(!$src_image) {
                    throw new \Exception('malformed_image_data');
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
            catch(\Exception $e) {
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


}
