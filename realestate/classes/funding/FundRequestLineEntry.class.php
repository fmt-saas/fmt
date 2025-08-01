<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/

namespace realestate\funding;

class FundRequestLineEntry extends \equal\orm\Model {

    public static function getName() {
        return 'Fund Request Line Entry';
    }

    public static function getColumns() {
        return [
            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the property lot belongs to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'required'          => true
            ],

            'fund_request_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'realestate\funding\FundRequest',
                'description'       => "Fund request the entry relates to.",
                'relation'          => ['request_line_id' => 'fund_request_id'],
                'store'             => true
            ],

            'request_line_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\funding\FundRequestLine',
                'description'       => "Fund request the line relates to.",
                'dependents'        => ['fund_request_id'],
                'ondelete'          => 'cascade',
                'required'          => true
            ],

            'ownership_id' => [
                'type'              => 'many2one',
                'description'       => "The ownership that the owner refers to.",
                'foreign_object'    => 'realestate\ownership\Ownership',
                // 'required'          => true,
                'readonly'          => true
            ],

            'allocated_amount' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:4',
                'function'          => 'calcAllocatedAmount',
                'store'             => true,
                'description'       => 'Total amount currently requested to co-owners.'
            ],

            'apportionment_shares' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'usage'             => 'amount/natural',
                'function'          => 'calcApportionmentShares',
                'store'             => true,
                'description'       => "Amount of shares the owner has for related apportionment, based on property lot.",
            ],

            'entry_lots_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\funding\FundRequestLineEntryLot',
                'foreign_field'     => 'line_entry_id',
                'description'       => "Lines of the Fund request."
            ],

            'execution_lines_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'realestate\funding\FundRequestExecutionLine',
                'foreign_field'     => 'line_entries_ids',
                'rel_table'         => 'funding_lineentry_rel_funding_executionline',
                'rel_foreign_key'   => 'execution_line_id',
                'rel_local_key'     => 'line_entry_id',
                'description'       => "Request fund execution lines the entry relates to, if any."
            ]

        ];
    }

    protected static function calcApportionmentShares($self) {
        $result = [];
        $self->read(['entry_lots_ids' => ['apportionment_shares']]);
        foreach($self as $id => $lineEntry) {
            if(empty($lineEntry['entry_lots_ids'])) {
                continue;
            }
            $result[$id] = 0.0;
            foreach($lineEntry['entry_lots_ids'] as $entryLot) {
                $result[$id] += $entryLot['apportionment_shares'];
            }
        }
        return $result;
    }

    public static function calcAllocatedAmount($self) {
        $result = [];
        $self->read(['entry_lots_ids' => ['allocated_amount']]);
        foreach($self as $id => $lineEntry) {
            if(empty($lineEntry['entry_lots_ids'])) {
                continue;
            }
            $result[$id] = 0.0;
            foreach($lineEntry['entry_lots_ids'] as $entryLot) {
                $result[$id] += $entryLot['allocated_amount'];
            }
        }
        return $result;
    }

}
