<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace realestate\governance;

class Assembly extends \equal\orm\Model {

    public static function getColumns() {

        return [
            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the property lot belongs to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'required'          => true
            ],

            'name' => [
                'type'              => 'string',
                'description'       => "Name of the assembly template.",
                'required'          => true
            ],

            'heading_text' => [
                'type'              => 'text',
                'description'       => "Text of the assembly call.",
                'required'          => false
            ],

            'assembly_date' => [
                'type'              => 'datetime',
                'description'       => "Scheduled date and time of the assembly (cannot be modified).",
                'required'          => true
            ],

            'assembly_location' => [
                'type'              => 'string',
                'description'       => "Location of the assembly (always announced in advance).",
                'required'          => true
            ],

            'assembly_type' => [
                'type'              => 'string',
                'selection'         => ['statutory', 'takeover', 'ordinary', 'extraordinary', 'partial'],
                'required'          => true,
                'description'       => "Type of assembly."
            ],

            'template_id' => [
                'type'              => 'many2one',
                'description'       => "Reference to the assembly template.",
                'foreign_object'    => 'realestate\governance\AssemblyTemplate',
                'required'          => false
            ],

            'assembly_minutes_id' => [
                'type'           => 'many2one',
                'description'    => "Reference to the minutes of the assembly.",
                'foreign_object' => 'realestate\governance\AssemblyMinutes',
                'required'       => false
            ],

            'assembly_items_ids' => [
                'type'           => 'one2many',
                'description'    => "Items on the assembly agenda.",
                'foreign_object' => 'realestate\governance\AssemblyItem',
                'foreign_field'  => 'assembly_id',
                'required'       => false
            ],

            'assembly_invitations_ids' => [
                'type'           => 'one2many',
                'description'    => "Invitations sent for the assembly.",
                'foreign_object' => 'realestate\governance\AssemblyInvitation',
                'foreign_field'  => 'assembly_id',
                'required'       => false
            ],

            'assembly_votes_ids' => [
                'type'           => 'one2many',
                'description'    => "Votes cast during the assembly.",
                'foreign_object' => 'realestate\governance\AssemblyVote',
                'foreign_field'  => 'assembly_id',
                'required'       => false
            ],

            'assembly_attendees_ids' => [
                'type'           => 'one2many',
                'description'    => "Attendees of the assembly.",
                'foreign_object' => 'realestate\governance\AssemblyAttendee',
                'foreign_field'  => 'assembly_id',
                'required'       => false
            ],

            'session_time_start' => [
                'type'           => 'datetime',
                'description'    => "Start time of the session.",
                'help'           => "This is the expected start of the session, which is preceded by presence checks.",
                'required'       => false
            ],

            'session_time_end' => [
                'type'           => 'datetime',
                'description'    => "End time of the session.",
                'help'           => "This is the time at which the session was actually over, which is after the vote and minutes are finalized.",
                'required'       => false
            ],

            'is_second_session' => [
                'type'           => 'boolean',
                'description'    => "Flag marking the assembly as a second session.",
                'required'       => false
            ],

            'has_second_session' => [
                'type'           => 'boolean',
                'description'    => "True if a second session is planned.",
                'help'           => "This is used to indicate that the quorum is not met and that a second session is needed.",
                'required'       => false
            ],

            'related_assembly_id' => [
                'type'           => 'many2one',
                'description'    => "Reference to the parent assembly if this is a second session.",
                'foreign_object' => 'realestate\governance\Assembly',
                'visible'        => ['is_second_session', '=', true]
            ],

            'second_session_assembly_id' => [
                'type'           => 'many2one',
                'description'    => "Reference to the second session assembly if planned.",
                'foreign_object' => 'realestate\governance\Assembly',
                'visible'        => ['has_second_session', '=', true]
            ],

            'status' => [
                'type'           => 'string',
                'description'    => "Workflow status of the assembly.",
                'required'       => true,
                'selection'      => [
                    'pending',
                    'ready',
                    'sent',
                    'held',
                    'adjourned',
                    'closed'
                ]
            ]

        ];
    }
}
