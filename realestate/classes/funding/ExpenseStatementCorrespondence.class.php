<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace realestate\funding;

class ExpenseStatementCorrespondence extends \documents\correspondence\DocumentCorrespondence {

    public function getTable() {
        return 'realestate_funding_expensestatementcorrespondence';
    }

    public static function getDescription() {
        return "Individual expense statement. A condominium Expense Statement generates at least one statement per ownership (representative).";
    }

    public static function getColumns() {

        return [
            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'relation'          => ['expense_statement_id' => 'name'],
                'store'             => true
            ],

            'expense_statement_id' => [
                'type'              => 'many2one',
                'description'       => "The assembly the invitation refers to.",
                'foreign_object'    => 'realestate\funding\ExpenseStatement',
                'required'          => true
            ],

            'mails_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'core\Mail',
                'foreign_field'     => 'object_id',
                'domain'            => ['object_class', '=', 'realestate\funding\ExpenseStatementCorrespondence'],
                'visible'           => ['communication_method', '=', 'email']
            ]
        ];
    }

}
