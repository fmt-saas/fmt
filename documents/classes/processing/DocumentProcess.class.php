<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace documents\processing;
use equal\orm\Model;
use documents\Document;

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
                'foreign_object'    => 'realestate\property\Condominium'
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

            'document_type_code' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'relation'          => ['document_id' => ['document_type_id' => 'code']],
                'store'             => true
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

                On utilise les infos contenues dans document_json pour compléter ces informations.
                Dans le cas où elles ne sont pas présentes, l'utilisateur peut les ajouter à la main.

                We use typing\Document[...] classes for holding temporary data extracted from imported document.

            */

            'document_invoice_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'documents\typing\DocumentInvoice',
                'visible'           => ['document_type_code', '=', 'invoice']
            ],

            'document_bank_statement_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'documents\typing\DocumentInvoice',
                'visible'           => ['document_type_code', '=', 'bank_statement']
            ],


        ];
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

    public static function onchange($event, $values) {
        $result = [];

        if(isset($event['data']['name'])) {
            $result['name'] = $event['data']['name'];
        }

        return $result;
    }
}