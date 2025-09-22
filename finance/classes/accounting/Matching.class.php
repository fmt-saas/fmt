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
        return 'A Matching allows marking several accounting entry lines (records) as linked, and to reconcile their movements.';
    }

    public static function getColumns() {

        return [

            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the funding refers to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'readonly'          => true
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

            // #todo - we should have a sequence for auto assignment of sequential code, by condominium
            'code' => [
                'type'              => 'string',
                'description'       => 'Display name of the matching.'
            ],

            'accounting_account_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\Account',
                'description'       => "Accounting account the matching relates to.",
                'required'          => true,
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null], ['is_control_account', '=', false]]
            ],

            'accounting_entry_lines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'finance\accounting\AccountingEntryLine',
                'foreign_field'     => 'matching_id',
                'description'       => 'Accounting entry lines (records) linked to the matching.',
                'domain'            => ['account_id', '=', 'object.accounting_account_id'],
                'onupdate'          => 'onupdateAccountingEntryLinesIds'
            ],

            'balance_amount' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'description'       => 'A matching is balanced/completed, if the total debited amount equals the total credited amount.',
                'function'          => 'calcBalanceAmount',
                'store'             => true
            ],

            'is_balanced' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'description'       => 'A matching is balanced/completed, if the total debited amount equals the total credited amount.',
                'function'          => 'calcIsBalanced',
                'store'             => true
            ],

            'matching_level' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'usage'             => 'icon',
                'selection'         => [
                    'part',
                    'full'
                ],
                'function'          => 'calcMatchingLevel',
                'store'             => true
            ]

        ];
    }

    public static function getActions() {
        return [
            'refresh_matching_level' => [
                'description'   => 'Update status according to currently paid amount.',
                'policies'      => [],
                'function'      => 'doRefreshMatchingLevel'
            ],
            'check_emptiness' => [
                'description'   => 'Check remaining linked accounting entry lines, if empty matching is removed.',
                'policies'      => [],
                'function'      => 'doCheckEmptiness'
            ]
        ];
    }

    protected static function onupdateAccountingEntryLinesIds($self) {
        $self
            ->do('refresh_matching_level')
            ->do('check_emptiness');
    }

    protected static function doCheckEmptiness($self) {
        $self->read(['accounting_entry_lines_ids']);
        foreach($self as $id => $matching) {
            if(count($matching['accounting_entry_lines_ids']) <= 0) {
                self::id($id)->delete(true);
            }
        }
    }

    protected static function doRefreshMatchingLevel($self) {
        $self->read(['accounting_entry_lines_ids']);

        $self
            ->update(['balance_amount' => null, 'is_balanced' => null, 'matching_level' => null])
            ->read(['balance_amount', 'is_balanced', 'matching_level']);

        foreach($self as $id => $matching) {
            AccountingEntryLine::ids($matching['accounting_entry_lines_ids'])->do('refresh_matching_level');
        }

    }

    protected static function calcName($self) {
        $result = [];
        $self->read(['id', 'description']);
        foreach($self as $id => $matching) {
            $parts = [];
            $parts[] = sprintf("%08d", $matching['id']);
            if(strlen($matching['description'] ?? '') > 0) {
                $parts[] = $matching['description'];
            }
            $result[$id] = implode(' - ', $parts);
        }
        return $result;
    }

    protected static function calcBalanceAmount($self) {
        $result = [];
        $self->read(['accounting_entry_lines_ids' => ['debit', 'credit']]);
        foreach($self as $id => $matching) {
            $credit = 0.0;
            $debit = 0.0;
            foreach($matching['accounting_entry_lines_ids'] as $accounting_entry) {
                $credit += $accounting_entry['credit'];
                $debit  += $accounting_entry['debit'];
            }
            $result[$id] = round($debit - $credit, 2);
        }
        return $result;
    }

    protected static function calcIsBalanced($self) {
        $result = [];
        $self->read(['balance_amount']);
        foreach($self as $id => $matching) {
            $result[$id] = abs($matching['balance_amount']) < 0.01;
        }
        return $result;
    }

    protected static function calcMatchingLevel($self) {
        $result = [];
        $self->read(['is_balanced']);
        foreach($self as $id => $matching) {
            $result[$id] = 'part';
            if($matching['is_balanced']) {
                $result[$id] = 'full';
            }
        }
        return $result;
    }

    /**
     * Revoke link between accounting entries & matching.
     *
     */
    protected static function onbeforedelete($self) {
        $self->read(['accounting_entry_lines_ids']);
        foreach($self as $id => $matching) {
            AccountingEntryLine::ids($matching['accounting_entry_lines_ids'])->update(['matching_id' => null, 'matching_level' => null]);
        }
    }


/*
    public function getUnique() {
        return [
            ['condo_id', 'code'],
        ];
    }
*/
}
