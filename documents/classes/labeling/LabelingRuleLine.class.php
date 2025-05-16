<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
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

            /* la liste des éléments de format possibles dépend de type.subtype */

            'document_format' => [
                'type'              => 'string',
                'description'       => 'Format to use for referencing the document.',
                'multilang'         => true
            ],

            'allocation_line_format' => [
                'type'              => 'string',
                'description'       => 'Format to use for allocation lines.',
                'multilang'         => true
            ]

        ];
    }


}