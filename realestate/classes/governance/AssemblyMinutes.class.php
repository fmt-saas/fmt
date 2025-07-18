<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace realestate\governance;

class AssemblyMinutes extends \equal\orm\Model {

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
                'description'       => "The assembly this minutes document belongs to.",
                'foreign_object'    => 'realestate\governance\Assembly',
                'required'          => true,
                'unique'            => true
            ],

            'heading_text' => [
                'type'              => 'string',
                'usage'             => 'text/plain.small',
                'description'       => "Heading text (type, date, time, location).",
                'editable'          => true,
                'computed'          => false
            ],

            'closing_text' => [
                'type'              => 'string',
                'usage'             => 'text/plain.small',
                'description'       => "Closing text (end of session).",
                'nullable'          => true
            ],

            'original_document_id' => [
                'type'              => 'many2one',
                'description'       => "Original generated version of the minutes (PDF, unsigned).",
                'foreign_object'    => 'documents\Document'
            ],

            'signed_document_id' => [
                'type'              => 'many2one',
                'description'       => "Final signed version (sealed with visual signatures).",
                'foreign_object'    => 'documents\Document',
                'nullable'          => true
            ],

            // #memo - signatures are set on the original document itself

            'minutes_entries_ids' => [
                'type'              => 'one2many',
                'description'       => "Entries for each agenda item.",
                'foreign_object'    => 'realestate\governance\AssemblyMinutesEntry',
                'foreign_field'     => 'assembly_minutes_id'
            ],

            'status' => [
                'type'              => 'string',
                'description'       => "Status of the minutes (pending, ready, signed).",
                'selection'         => [
                    'pending',
                    'ready',
                    'signed'
                ],
                'default'           => 'pending'
            ]
        ];
    }
}
