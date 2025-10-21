<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

namespace realestate\funding;

class FundRequestLine extends \equal\orm\Model {

    public static function getName() {
        return 'Fund Request Line';
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
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\funding\FundRequest',
                'description'       => "Fund request the line relates to.",
                'ondelete'          => 'cascade',
                'required'          => true
            ],

            'apportionment_id' => [
                'type'              => 'many2one',
                'description'       => "The key that the apportionment refers to.",
                'foreign_object'    => 'realestate\property\Apportionment',
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['is_statutory', '=', false]],
                'required'          => true
            ],

            'request_amount' => [
                'type'              => 'float',
                'usage'             => 'amount/money:4',
                'required'          => true,
                'description'       => 'Total requested amount of the fund call.'
            ],

            'allocated_amount' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:4',
                'function'          => 'calcCalledAmount',
                'description'       => 'Total amount currently requested to co-owners.',
                'store'             => true
            ],

            'line_entries_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\funding\FundRequestLineEntry',
                'foreign_field'     => 'request_line_id',
                'description'       => "Lines of the Fund request."
            ],

            'entry_lots_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\funding\FundRequestLineEntryLot',
                'foreign_field'     => 'request_line_id',
                'description'       => "Lines of the Fund request."
            ]

        ];
    }

    public static function calcCalledAmount($self) {
        $result = [];
        $self->read(['line_entries_ids' => ['allocated_amount']]);
        foreach($self as $id => $requestLine) {
            if(empty($requestLine['line_entries_ids'])) {
                continue;
            }
            $result[$id] = 0.0;
            foreach($requestLine['line_entries_ids'] as $lineEntry) {
                $result[$id] += $lineEntry['allocated_amount'];
            }
        }
        return $result;
    }

}
