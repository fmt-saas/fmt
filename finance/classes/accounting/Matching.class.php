<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/

namespace finance\accounting;

use equal\orm\Model;

class Matching extends Model {

    public static function getDescription() {
        return 'A Matching allows mark several accounting entries as linked and to reconcile their movements.';
    }

    public static function getColumns() {

        return [

            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the funding refers to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'readonly'          => true
            ],

            // #todo - we should have a sequence for auto assignment of sequential code, by condominium
            'code' => [
                'type'              => 'string',
                'description'       => 'Display name of funding.'
            ],

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => 'Display name of matching.',
                'function'          => 'calcName',
                'store'             => true
            ],

            'description' => [
                'type'              => 'string',
                'description'       => 'Optional description to identify the funding.',
                'help'              => "In case the Matching is a Funding, it holds a description similar to the name"
            ],


            'accounting_entries_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'finance\accounting\AccountingEntry',
                'foreign_field'     => 'matching_id',
                'description'       => 'Accounting entries linked to the matching.'
            ],

            'is_balanced' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'description'       => 'A matching is balanced/completed, if the total debited amount equals the total credited amount.',
                'function'          => 'calcIsBalanced',
                'store'             => true
            ],


        ];
    }

    protected static function calcName($self) {
        $result = [];
        $self->read(['id', 'code', 'created']);
        foreach($self as $id => $matching) {
            $result[$id] = sprintf("%08d - %s", $matching['id'], date('Y-m-d', $matching['created']));
        }

        return $result;
    }


    protected static function calcIsBalanced($self) {
        $result = [];
        $self->read(['accounting_entries_ids' => ['debit', 'credit']]);
        foreach($self as $id => $matching) {
            $credit = 0.0;
            $debit = 0.0;
            foreach($matching['accounting_entries_ids'] as $accounting_entry) {
                $credit += $accounting_entry['credit'];
                $debit  += $accounting_entry['debit'];
            }
            $result[$id] = (abs(abs($credit) - abs($debit)) < 0.01);
        }
        return $result;
    }


/*
    public function getUnique() {
        return [
            ['condo_id', 'code'],
        ];
    }
*/
}
