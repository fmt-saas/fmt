<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace documents\labeling;
use equal\orm\Model;

class LabelingRuleLine extends Model {

    public static function getName() {
        return "Document Validation Rule Line";
    }

    public static function getDescription() {
        return "Document Validation rules have one or more lines associating them with specific condition a document has to meet.";
    }

    public static function getColumns() {
        return [
            'name' => [
                'type'              => 'string',
                'description'       => "Short description of the validation check.",
                'required'          => true
            ],

            'labeling_rule_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'documents\labeling\LabelingRule',
                'description'       => "Parent validation rule this line relates to.",
                'required'          => true
            ],

            'document_type_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'documents\DocumentType',
                'description'       => 'Document type linked to the rule.'
            ],

            'document_subtype_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'documents\DocumentSubtype',
                'description'       => 'Document subtype linked to the rule.'
            ],

            /* list of possible formatting elements depends on type.subtype */

            'document_format' => [
                'type'              => 'string',
                'description'       => 'Format to use for referencing the document.',
                'multilang'         => true
            ],

            'allocation_line_format' => [
                'type'              => 'string',
                'description'       => 'Format to use for referencing allocation lines.',
                'multilang'         => true
            ]

        ];
    }


}