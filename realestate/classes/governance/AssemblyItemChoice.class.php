<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace realestate\governance;

use Exception;
use realestate\ownership\Ownership;
use realestate\property\Apportionment;
use realestate\property\PropertyLotApportionmentShare;

class AssemblyItemChoice extends \equal\orm\Model {

    public static function getColumns() {

        return [
            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the property lot belongs to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'required'          => true
            ],

            'assembly_id' => [
                'type'              => 'many2one',
                'description'       => "The assembly the invitation refers to.",
                'foreign_object'    => 'realestate\governance\Assembly',
                'required'          => true,
                'onupdate'          => 'onupdateAssemblyId'
            ],

            'assembly_item_id' => [
                'type'              => 'many2one',
                'description'       => "The assembly item the choice relates to.",
                'help'              => 'This field is not relevant here, and present only to override parent definition.',
                'foreign_object'    => 'realestate\governance\AssemblyItem',
            ],

            'name' => [
                'type'              => 'string',
                'description'       => "Value of the choice. Must be unique for the assembly item.",
                'help'              => 'Arbitrary value of the choice, to be displayed in the vote and in the result summary.',
                'required'          => true
            ],

            'description' => [
                'type'              => 'string',
                'usage'             => 'text/plain:small',
                'description'       => "Short description of the choice, if relevant."
            ],

            'assembly_votes_ids' => [
                'type'              => 'one2many',
                'description'       => "Votes cast related to the assembly item.",
                'foreign_object'    => 'realestate\governance\AssemblyVote',
                'foreign_field'     => 'assembly_item_choice_id'
            ],

            'votes_count' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'function'          => 'calcVotesCount',
                'description'       => "Number of votes granted to the choice."
            ]

        ];
    }

    protected static function calcVotesCount($self) {
        $result = [];
        $self->read(['assembly_votes_ids' => ['vote_value']]);
        foreach($self as $id => $assemblyItemChoice) {
            $count_for = 0;
            foreach($assemblyItemChoice['assembly_votes_ids'] as $assembly_vote_id => $assemblyVote) {
                if($assemblyVote['vote_value'] === 'for') {
                    ++$count_for;
                }
            }
            $result[$id] = $count_for;
        }
        return $result;
    }
}
