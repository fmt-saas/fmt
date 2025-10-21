<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace documents\validation;
use equal\orm\Model;

class ValidationRuleLine extends Model {

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

            'validation_rule_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'documents\validation\ValidationRule',
                'description'       => "Parent validation rule this line relates to.",
                'required'          => true
            ],

            'controller' => [
                'type'              => 'string',
                'description'       => 'Controller holding the logic for validating the document.',
                'required'          => true
            ],


        ];
    }


}