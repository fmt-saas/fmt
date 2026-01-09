<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace realestate\governance;

use documents\Document;
use documents\DocumentSignature;
use documents\export\ExportingTask;
use documents\export\ExportingTaskLine;
use documents\navigation\Node;
use equal\html\HtmlTemplate;
use hr\role\RoleAssignment;
use realestate\ownership\Owner;
use realestate\ownership\Ownership;
use realestate\ownership\OwnershipCommunicationPreference;
use realestate\property\Apportionment;
use realestate\property\PropertyLotApportionmentShare;
use realestate\property\PropertyLotOwnership;

class Assembly extends \equal\orm\Model {

    public static function constants() {
        return ['L10N_TIMEZONE'];
    }

    public static function getColumns() {

        return [
            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the property lot belongs to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'required'          => true,
                'dependents'        => ['count_shares'],
                'onupdate'          => 'onupdateCondoId'
            ],

            'name' => [
                'type'              => 'string',
                'description'       => "Name of the assembly.",
                'required'          => true
            ],

            'assembly_organizer_identity_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'identity\Identity',
                'function'          => 'calcAssemblyOrganizerIdentityId',
                'description'       => 'Managing agent representative in charge of the organization of the Assembly.',
                'help'              => 'This identity relates directly or indirectly to the managing agent (owner or professional), and is in charge of the organization of the Assembly.',
                'store'             => true,
                // limiter aux identités des employés
                'domain'            => [['employee_id', '<>', null]]
            ],

            'register_document_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'documents\Document',
                'description'       => 'Generated document to serve as attendance register.',
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null]]
            ],

            'signed_register_document_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'documents\Document',
                'description'       => 'Generated document to serve as attendance register.',
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null]],
            ],

            'minutes_document_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'documents\Document',
                'description'       => 'Generated document holding the minutes of the assembly.',
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null]]
            ],

            'signed_minutes_document_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'documents\Document',
                'description'       => 'Generated document holding the minutes of the assembly.',
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null]]
            ],

            // #deprecated
            'heading_text_call' => [
                'deprecated'        => true,
                'type'              => 'string',
                'usage'             => 'text/plain.small',
                'description'       => "Text of the assembly call.",
                'required'          => false
            ],

            // #deprecated
            'heading_text_minutes' => [
                'deprecated'        => true,
                'type'              => 'string',
                'usage'             => 'text/plain.small',
                'description'       => "Heading text of the assembly minutes.",
                'required'          => false
            ],

            // #deprecated
            'closing_text_call' => [
                'deprecated'        => true,
                'type'              => 'string',
                'usage'             => 'text/plain.small',
                'description'       => "Closing text of the assembly call.",
                'required'          => false
            ],

            // #deprecated
            'closing_text_minutes' => [
                'deprecated'        => true,
                'type'              => 'string',
                'usage'             => 'text/plain.small',
                'description'       => "Closing text of the assembly minutes.",
                'required'          => false
            ],

            'has_call_option_majority' => [
                'type'              => 'boolean',
                'description'       => "Show required majorities in the assembly call.",
                'default'           => false
            ],

            'assembly_date' => [
                'type'              => 'date',
                'usage'             => 'date/plain',
                'description'       => "Scheduled date of the assembly."
                //  #memo - no default here : must be entered by hand
            ],

            'assembly_invitation_date' => [
                'type'              => 'datetime',
                'description'       => 'Scheduled date and time of the assembly (cannot be modified).',
                'help'              => "This date is set once, when the Assembly reaches the `sending` status."
            ],

            'assembly_location' => [
                'type'              => 'string',
                'description'       => "Location of the assembly (always announced in advance)."
            ],

            'assembly_type' => [
                'type'              => 'string',
                'description'       => 'Type of assembly.',
                'selection'         => [
                    'constitutive',     // First general meeting of a new condominium association – formal activation (property manager, budget, funds…)
                    'statutory',        // Mandatory annual general meeting (art. 3.87 §1 C.C.)
                    'extraordinary',    // Extraordinary general meeting convened outside the cycle for specific decision(s)
                    'recovery',         // General meeting for recovery after blockage, deficiency, or change of property manager
                    'special',          // Special cases: judicial general meeting, by block, by section, undivided ownership, etc.
                    'council_meeting'   // Meeting of the Condominium Council (non-decisional except with mandate)
                ],
                'default'           => 'statutory'
            ],

            'assembly_template_id' => [
                'type'              => 'many2one',
                'description'       => "Reference to the assembly template, if any.",
                'foreign_object'    => 'realestate\governance\AssemblyTemplate',
                'required'          => false,
                'onupdate'          => 'onupdateAssemblyTemplateId'
            ],

            'assembly_items_ids' => [
                'type'           => 'one2many',
                'description'    => "Items on the assembly agenda.",
                'foreign_object' => 'realestate\governance\AssemblyItem',
                'foreign_field'  => 'assembly_id',
                'domain'         => [ ['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null] ],
                'onupdate'       => 'onupdateAssemblyItemsIds',
                'order'          => 'order'
            ],

            'assembly_invitation_correspondences_ids' => [
                'type'           => 'one2many',
                'description'    => "Invitations sent for the assembly.",
                'foreign_object' => 'realestate\governance\AssemblyInvitationCorrespondence',
                'foreign_field'  => 'assembly_id'
            ],

            'assembly_minutes_correspondences_ids' => [
                'type'           => 'one2many',
                'description'    => "Invitations sent for the assembly.",
                'foreign_object' => 'realestate\governance\AssemblyMinutesCorrespondence',
                'foreign_field'  => 'assembly_id'
            ],

            'exporting_tasks_ids' => [
                'type'              => 'one2many',
                'description'       => "Reference to the task for exporting paper mails for assembly invitation, if any.",
                'foreign_object'    => 'documents\export\ExportingTask',
                'foreign_field'     => 'object_id',
                'domain'            => [
                    ['object_class', '=', 'realestate\governance\Assembly']
                ]
            ],

            'invitations_exporting_task_id' => [
                'type'              => 'many2one',
                'description'       => "Reference to the task for exporting paper mails for assembly invitation, if any.",
                'foreign_object'    => 'documents\export\ExportingTask'
            ],

            'minutes_exporting_task_id' => [
                'type'              => 'many2one',
                'description'       => "Reference to the task for exporting paper mails for assembly minutes, if any.",
                'foreign_object'    => 'documents\export\ExportingTask'
            ],

            'assembly_votes_ids' => [
                'type'           => 'one2many',
                'description'    => "Votes cast during the assembly.",
                'foreign_object' => 'realestate\governance\AssemblyVote',
                'foreign_field'  => 'assembly_id'
            ],

            'assembly_representations_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\governance\AssemblyRepresentation',
                'foreign_field'     => 'assembly_id',
                'description'       => "Validated representations for the assembly.",
                'help'              => "Representations are generated automatically, based on assembly_mandates_ids, and are used to link the attendee to the ownerships they represent in the assembly (directly or with a mandate).",
                'domain'            => [ ['condo_id', '=', 'object.condo_id'] ]
            ],

            'assembly_attendees_ids' => [
                'type'           => 'one2many',
                'description'    => "Attendees of the assembly.",
                'foreign_object' => 'realestate\governance\AssemblyAttendee',
                'foreign_field'  => 'assembly_id',
                'domain'         => [['condo_id', '=', 'object.condo_id']]
            ],

            'assembly_minutes_entries_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\governance\AssemblyMinutesEntry',
                'foreign_field'     => 'assembly_id',
                'description'       => "Minutes entries from all agenda items."
            ],

            'session_time_start' => [
                'type'           => 'time',
                'description'    => "Start time of the session.",
                'help'           => "This is the expected start of the session, which is preceded by presence checks."
                // #memo - no default here : must be entered by hand
                // #todo - it could be necessary ti make a distinction between the expected time of the Assembly start an the real moment at which it begun
            ],

            'session_time_end' => [
                'type'           => 'time',
                'description'    => "End time of the session.",
                'help'           => "This is the time at which the session was actually over, which is after the vote and minutes are finalized.",
                'required'       => false
            ],

            'is_valid' => [
                'type'           => 'boolean',
                'description'    => "Flag marking the assembly as valid.",
                'default'        => true
            ],

            'is_complete' => [
                'type'           => 'boolean',
                'description'    => "Flag marking the assembly as completed (all items have been reviewed).",
                'store'          => true,
                'instant'        => true
            ],

            'invalidity_reason' => [
                'type'        => 'string',
                'selection'   => [
                    'quorum_owners_not_met',       // Not enough owners present or represented
                    'quorum_shares_not_met'        // Not enough shares represented
                ],
                'description' => "Reason for invalidity of the Assembly.",
                'visible'     => ['is_valid', '=', false]
            ],

            'is_second_session' => [
                'type'           => 'boolean',
                'description'    => "Flag marking the assembly as a second session.",
                'default'        => false
            ],

            'has_second_session' => [
                'type'           => 'boolean',
                'description'    => "True if a second session is planned.",
                'help'           => "This is used to indicate that the quorum is not met and that a second session is needed.",
                'default'        => false,
                'visible'        => ['status', '=', 'adjourned']
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

            'ownerships_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'realestate\ownership\Ownership',
                'foreign_field'     => 'assemblies_ids',
                'rel_table'         => 'realestate_governance_assembly_rel_ownership',
                'rel_foreign_key'   => 'ownership_id',
                'rel_local_key'     => 'assembly_id',
                'description'       => 'Ownerships that are concerned by the assembly.',
                'help'              => 'This field is filled automatically at assembly publication. Independently from attendance.'
            ],

            'assembly_mandates_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\governance\AssemblyMandate',
                'foreign_field'     => 'assembly_id',
                'description'       => "The list of mandates registered for the assembly.",
            ],

            'documents_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'documents\Document',
                'foreign_field'     => 'assembly_id',
                'description'       => "One or more documents that relate to the point.",
                'domain'            => ['condo_id', '=', 'object.condo_id']
            ],

            'count_shares' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'description'       => "The total of statutory shares of the condominium.",
                'relation'          => ['condo_id' => 'total_shares'],
                'store'             => true,
                'readonly'          => true,
                'visible'           => ['status', 'in', ['in_progress', 'held', 'adjourned']]
            ],

            'count_represented_shares' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'description'       => "The number of statutory shares that are represented in the assembly.",
                'function'          => 'calcCountRepresentedShares',
                'store'             => true,
                'readonly'          => true,
                'visible'           => ['status', 'in', ['in_progress', 'held', 'adjourned']]
            ],

            'count_owners' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'description'       => "The theoretical number of owners that own at least one primary property lot.",
                'help'              => "This count considers the Ownerships and not individual owners.",
                'function'          => 'calcCountOwners',
                'store'             => true,
                'readonly'          => true
            ],

            'count_represented_owners' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'description'       => "The number of ownerships that are present or represented in the assembly.",
                'function'          => 'calcCountRepresentedOwners',
                'store'             => true,
                'readonly'          => true,
                'visible'           => ['status', 'in', ['in_progress', 'held', 'adjourned']]
            ],

            'step' => [
                'type'           => 'string',
                'description'    => "Step at which the assembly is currently being.",
                'default'        => 'opening',
                'selection'      => [
                    'opening',
                    'attendance_closure',
                    'mandate_validation',
                    'representation_validation',
                    'assembly_validation',
                    'agenda_processing',
                    'minutes_confirmation',
                    'minutes_signing',
                    'assembly_closing'
                ],
                'visible'         => ['status', '=', 'in_progress'],
                'dependents'      => ['count_shares', 'count_represented_shares', 'count_owners', 'count_represented_owners']
            ],

            'status' => [
                'type'           => 'string',
                'description'    => "Workflow status of the assembly.",
                'default'        => 'pending',
                'selection'      => [
                    'pending',
                    'published',
                    'sending',
                    'sent',
                    'in_progress',
                    'held',
                    'adjourned',
                    /*'closed'*/
                ]
            ]

        ];
    }

    public static function getWorkflow() {
        return [
            'pending' => [
                'description' => '',
                'icon' => 'sent',
                'transitions' => [
                    'publish' => [
                        'description'   => 'Marks the Assembly as open and start allowing attendee encoding.',
                        'policies'      => ['can_publish'],
                        'onafter'       => 'onafterPublish',
                        'status'        => 'published'
                    ]
                ]
            ],
            'published' => [
                'description' => '',
                'icon' => 'sent',
                'transitions' => [
                    'revert_publish' => [
                        'description'   => 'Revert Assembly to `pending` in order to allow changes.',
                        'policies'      => [/**/],
                        'status'        => 'pending'
                    ],
                    'send' => [
                        'description'   => 'Send the invitations, and mark the Assembly as sent.',
                        'policies'      => [/**/],
                        'onbefore'      => 'onbeforeSend',
                        'status'        => 'sending'
                    ]
                ]
            ],
            'sending' => [
                'description' => 'Invites for Assembly call have been generated are being sent.',
                'icon' => 'sent',
                'transitions' => [
                    'sent' => [
                        'description'   => 'Marks the Assembly invites as sent.',
                        'help'          => "This is used to manually mark all paper correspondences as sent (so far system has no mean to know it).
                            An additional test is made on email invites (`can_mark_sent`) and the action fails if some emails haven't been sent.",
                        'policies'      => ['can_mark_sent'],
                        'onafter'       => 'onafterSent',
                        'status'        => 'sent'
                    ]
                ]
            ],
            'sent' => [
                'description' => '',
                'icon' => 'sent',
                'transitions' => [
                    'open' => [
                        'description'   => 'Marks the Assembly as open and start allowing attendee encoding.',
                        'policies'      => [/**/],
                        'onafter'       => 'onafterOpen',
                        'status'        => 'in_progress'
                    ]
                ]
            ],
            'in_progress' => [
                'description'   => 'The Assembly has started and the steps are being processed.',
                'icon'          => 'autorenew',
                'transitions'   => [
                    'close' => [
                        'description'   => 'All steps have been processed and assembly can be closed. This cannot be undone.',
                        'policies'      => ['can_close'],
                        'onafter'       => 'onafterClose',
                        'status'        => 'held'
                    ],
                    'adjourn' => [
                        'description'   => '',
                        'onafter'       => 'onafterAdjourn',
                        'status'        => 'adjourned'
                    ]
                ]
            ]
        ];
    }

    public static function getActions() {
        return [

            'auto_assign_location' => [
                'description'   => 'Assign location based on Condominium address.',
                'policies'      => [],
                'function'      => 'doAutoAssignLocation'
            ],

            'refresh_items_order' => [
                'description'   => 'Force a re-compute of the order field of the assembly items.',
                'policies'      => [/* */],
                'function'      => 'doRefreshItemsOrder'
            ],

            'generate_ownerships' => [
                'description'   => 'Generate ownerships.',
                'help'          => 'i.e. Ownerships that are known, at the current date, to own at least one property lot having at least one share in the statutory apportionment. This action can be re-executed in order to refresh the list, in case a transfer took place in the meantime.',
                'policies'      => [],
                'function'      => 'doGenerateOwnerships'
            ],

            'generate_invitation_correspondences' => [
                'description'   => 'Generate invites.',
                'help'          => 'This is called in `onbeforeSend` handler.',
                'policies'      => [],
                'function'      => 'doGenerateInvitationCorrespondences'
            ],

            'send_invitation' => [
                'description'   => 'Send invites.',
                'help'          => 'Only email invites can be sent automatically. Mail & mandates must be sent manually.',
                'policies'      => [],
                'function'      => 'doSendInvitation'
            ],

            'close_attendance' => [
                'description'   => 'Close the attendance step and attempt to validate proxies.',
                'help'          => 'No more attendances can be added after this. Step to `mandate_validation`.',
                'policies'      => [],
                'function'      => 'doCloseAttendance'
            ],

            'validate_attendees' => [
                'description'   => 'Validate attendees, based on their role, mandates and ownerships.',
                'policies'      => [/* */],
                'function'      => 'doValidateAttendees'
            ],

            'generate_representations' => [
                'description'   => 'Generate the representations bases on the attendees and valid mandates.',
                'policies'      => [/* */],
                'function'      => 'doGenerateRepresentations'
            ],

            'generate_extra_representations' => [
                'description'   => 'Generate a representation for an extra attendee (added after attendance closing).',
                'policies'      => [/* */],
                'function'      => 'doAddExtraRepresentation'
            ],

            'generate_signable_attendance_register' => [
                'description'   => 'Create immutable version of the register to be signed.',
                'policies'      => [/* */],
                'function'      => 'doGenerateSignableAttendanceRegister'
            ],

            'generate_printable_attendance_register' => [
                'description'   => 'Create printable version of the register and store it to EDMS.',
                'policies'      => [/* */],
                'function'      => 'doGeneratePrintableAttendanceRegister'
            ],

            'validate_all' => [
                'description'   => 'Verifies the validity of the mandates, attendees and assembly (in number).',
                'help'          => "This action is meant to allow handling  {validate_} actions in bulk, depending on current step.",
                'policies'      => [],
                'function'      => 'doValidateAll'
            ],

            'validate_mandates' => [
                'description'   => 'Verifies the validity of the proxies and generate links with ownerships.',
                'help'          => "This action is meant to be performed at the mandate_validation step. We check the validity conditions of each proxy.
                    If a proxy is invalid, we mark it as invalid and add the reason.
                    Triggers representations generation, and step to `representation_validation`.",
                'policies'      => [],
                'function'      => 'doValidateMandates'
            ],

            'validate_representations' => [
                'description'   => '.',
                'help'          => ".",
                'policies'      => [/* */],
                'function'      => 'doValidateRepresentations'
            ],

            'validate_assembly' => [
                'description'   => 'Verifies that the double-quorum is met.',
                'help'          => 'This action is meant to be performed at step `assembly_validation`',
                'policies'      => [/* */],
                'function'      => 'doValidateAssembly'
            ],

            'schedule_second_session' => [
                'description'   => 'Verifies that the double-quorum is met.',
                'help'          => 'This action is meant to be invoked during step `assembly_validation`',
                'policies'      => [ 'can_schedule_second_session' ],
                'function'      => 'doScheduleSecondSession'
            ],

            'refresh_is_complete' => [
                'description'   => 'Force a re-compute of the completion status.',
                'policies'      => [/* */],
                'function'      => 'doRefreshIsComplete'
            ],

            'generate_signable_minutes' => [
                'description'   => 'Create immutable version of the minutes to be signed.',
                'help'          => "The logic is to generate minutes (generate_minutes), but with a naming convention that makes a distinction between signable and printable (final) minutes.",
                'policies'      => ['can_generate_minutes'],
                'function'      => 'doGenerateSignableMinutes'
            ],

            'accept_minutes' => [
                'description'   => 'Create immutable version of the minutes to be signed.',
                'policies'      => [],
                'function'      => 'doAcceptMinutes'
            ],

            'close_minutes_signing' => [
                'description'   => 'Create immutable version of the minutes to be signed.',
                'policies'      => [ 'can_close_minutes_signing' ],
                'function'      => 'doCloseMinutesSigning'
            ],

            'generate_printable_minutes' => [
                'description'   => 'Create printable version of the minutes of the Assembly and store it to EDMS.',
                'policies'      => [/* */],
                'function'      => 'doGeneratePrintableMinutes'
            ],

            'generate_minutes_correspondences' => [
                'description'   => 'Generate invites.',
                'help'          => 'This is called in `onbeforeSend` handler.',
                'policies'      => [],
                'function'      => 'doGenerateMinutesCorrespondences'
            ],

            'send_minutes' => [
                'description'   => 'Send invites.',
                'help'          => 'Only email invites can be sent automatically. Mail & mandates must be sent manually.',
                'policies'      => [],
                'function'      => 'doSendMinutes'
            ]

        ];
    }

    public static function getPolicies(): array {
        return [
            'can_close' => [
                'description' => 'Verifies that the constituted assembly is valid, ',
                'help'        => "In order to be valid, the assembly must met the representation criteria depending on its type.",
                'function'    => 'policyCanClose'
            ],

            'can_publish' => [
                'description' => 'Verifies that all required info for the assembly are provided.',
                'function'    => 'policyCanPublish'
            ],

            'can_schedule_second_session' => [
                'description' => 'Verifies that the assembly is in a state that allows scheduling a second session.',
                'function'    => 'policyCanScheduleSecondSession'
            ],

            'can_mark_sent' => [
                'description' => 'Verifies that the assembly is in a state that allows scheduling a second session.',
                'function'    => 'policyCanMarkSent'
            ],

            'is_assembly_valid' => [
                'description' => 'Verifies that the constituted assembly is valid.',
                'help'        => "In order to be valid, the assembly must meet the representation criteria depending on its type.",
                'function'    => 'policyIsAssemblyValid'
            ],

            'can_generate_minutes' => [
                'description' => 'Verifies that a signable document of the assembly minutes is allowed to be generated.',
                'help'        => "If a minutes document has already been signed, a new one cannot be generated.",
                'function'    => 'policyCanGenerateMinutes'
            ],

            'can_close_minutes_signing' => [
                'description' => 'Verifies that the signin of the minutes is complete (mandatory signatures have been collected).',
                'function'    => 'policyCanCloseMinutesSigning'
            ]

            // 'can_be_open'
        ];
    }


    private static function computeOwnershipsIds($condo_id, $assembly_date) {
        // generate the ownerships_ids : list of expected Ownerships allowed to attend the Assembly
        $propertyLotOwnerships = PropertyLotOwnership::search([
                ['condo_id', '=', $condo_id]
            ])
            ->read(['date_from', 'date_to', 'ownership_id', 'property_lot_id' => ['is_primary']]);

        $map_ownerships_ids = [];

        foreach($propertyLotOwnerships as $propertyLotOwnership) {
            if(
                (!$propertyLotOwnership['date_from'] || $propertyLotOwnership['date_from'] <= $assembly_date)
                &&
                (!$propertyLotOwnership['date_to'] || $propertyLotOwnership['date_to'] > $assembly_date)
            ) {
                if($propertyLotOwnership['property_lot_id']['is_primary']) {
                    $map_ownerships_ids[$propertyLotOwnership['ownership_id']] = true;
                }
            }
        }
        return array_keys($map_ownerships_ids);
    }

    protected static function doRefreshIsComplete($self) {
        $self->read(['status', 'step', 'assembly_items_ids' => ['status']]);

        foreach($self as $id => $assembly) {
            $is_complete = true;
            foreach($assembly['assembly_items_ids'] as $assemblyItem) {
                if(!in_array($assemblyItem['status'], ['closed', 'adjourned'], true)) {
                    $is_complete = false;
                    break;
                }
            }
            $values = [
                'is_complete' => $is_complete
            ];
            if($is_complete) {
                $tz = new \DateTimeZone(constant('L10N_TIMEZONE'));
                $tz_offset = $tz->getOffset(new \DateTime('@' . time()));
                $local_time = time() + $tz_offset;
                $local_today = strtotime('today', $local_time);
                $values['session_time_end'] = $local_time - $local_today;
            }
            self::id($id)->update($values);
        }
    }

    protected static function doRefreshItemsOrder($self) {
        $self->read(['assembly_items_ids' => ['has_parent_group']]);
        foreach($self as $id => $assembly) {
            $order = 1;
            foreach($assembly['assembly_items_ids'] as $assembly_item_id => $assemblyItem) {
                if($assemblyItem['has_parent_group']) {
                    continue;
                }
                AssemblyItem::id($assembly_item_id)->update(['order' => $order]);
                ++$order;
            }
        }
    }

    protected static function doGenerateOwnerships($self) {
        $self->read(['condo_id', 'assembly_date', 'ownerships_ids']);

        foreach($self as $id => $assembly) {
            // remove any previously retrieved ownership
            $ids_to_remove = array_map(function ($a) {return -$a;}, $assembly['ownerships_ids']);
            self::id($id)->update(['ownerships_ids' => $ids_to_remove]);

            $ownerships_ids = self::computeOwnershipsIds($assembly['condo_id'], $assembly['assembly_date']);
            self::id($id)->update(['ownerships_ids' => $ownerships_ids]);
        }
    }

    protected static function calcAssemblyOrganizerIdentityId($self) {
        $result = [];
        $self->read(['condo_id' => ['managing_agent_id' => ['identity_id', 'agent_identity_type']]]);

        foreach($self as $id => $assembly) {
            if(!$assembly['condo_id']) {
                continue;
            }

            $roleAssignment = RoleAssignment::search([
                    ['condo_id', '=', $assembly['condo_id']['id']],
                    ['role_code', '=', 'condo_manager']
                ])
                ->read(['identity_id'])
                ->first();

            if($roleAssignment) {
                $result[$id] = $roleAssignment['identity_id'];
            }
            else {
                // #todo - dans tous les cas on va créer un Employé et des Roles pour l'ACP
                if($assembly['condo_id']['managing_agent_id']['agent_identity_type'] === 'owner') {
                    $result[$id] = $assembly['condo_id']['managing_agent_id']['identity_id'];
                }
                elseif($assembly['condo_id']['managing_agent_id']['agent_identity_type'] === 'professional') {
                    $assignment = RoleAssignment::search([
                            ['condo_id', '=', $assembly['condo_id']['id']],
                            ['role_code', '=', 'condo_manager']
                        ])
                        ->read(['identity_id'])
                        ->first();
                    if($assignment) {
                        $result[$id] = $assignment['identity_id'];
                    }
                }
            }
        }
        return $result;
    }

    protected static function onafterPublish($self) {
        // generate the ownerships_ids : list of expected Ownerships allowed to attend the Assembly
        $self
            ->do('generate_ownerships')
            ->update(['count_shares' => null, 'count_represented_shares' => null, 'count_owners' => null, 'count_represented_owners' => null]);
    }


    /**
     * Mark all non-email invitations correspondences as sent.
     */
    protected static function onafterSent($self) {
        $self->read(['assembly_invitation_correspondences_ids' => ['@domain' => ['communication_method', '<>', 'email'], 'id', 'communication_method']]);
        $map_invitations_ids = [];

        foreach($self as $id => $assembly) {
            foreach($assembly['assembly_invitation_correspondences_ids'] as $assembly_invitation_id => $assemblyInvitation) {
                if($assemblyInvitation['communication_method'] === 'email') {
                    continue;
                }
                $map_invitations_ids[$assembly_invitation_id] = true;
            }
        }

        AssemblyInvitationCorrespondence::ids(array_keys($map_invitations_ids))
            ->update([
                    'is_sent'   => true,
                    'sent_date' => time()
                ]);
    }

    /**
     * Generate the attendance document (required for signatures)
     *
     */
    protected static function doGenerateSignableAttendanceRegister($self) {
        $self->read(['condo_id', 'register_document_id']);
        foreach($self as $id => $assembly) {

            // remove previous version (there shouldn't be any)
            if($assembly['register_document_id']) {
                Document::id($assembly['register_document_id'])->delete(true);
            }

            // generate a new doc
            try {
                $data = \eQual::run('get', 'realestate_governance_Assembly_attendanceregister_render-pdf', ['id' => $id]);

                $document = Document::create([
                        'name'      => 'Liste de présences',
                        'data'      => $data,
                        'condo_id'  => $assembly['condo_id']
                    ])
                    ->first();

                // #memo - original documents remain "invisible", only signed version should be accessible through EDMS fs tree
                self::id($id)
                    ->update([
                        'register_document_id' => $document['id']
                    ]);
            }
            catch(\Exception $e) {
                trigger_error("APP::unable to generate attendance register:" . $e->getMessage(), EQ_REPORT_ERROR);
                throw($e);
            }
        }
    }

    protected static function doGeneratePrintableAttendanceRegister($self) {
        $self->read([
                'condo_id',
                'register_document_id'
            ]);

        foreach($self as $id => $assembly) {

            if(!$assembly['register_document_id']) {
                throw new \Exception('missing_mandatory_document', EQ_ERROR_INVALID_PARAM);
            }

            // generate a new doc
            try {
                $data = \eQual::run('get', 'realestate_governance_Assembly_attendanceregister_render-pdf', [
                        'id'                    => $id,
                        'signed'                => true
                    ]);

                // store document in related General Assembly folder
                $parentNode = Node::search([
                        ['condo_id', '=', $assembly['condo_id']],
                        ['node_type', '=', 'folder'],
                        ['code', '=', 'general_meetings']
                    ])
                    ->first();

                $document = Document::create([
                        'name'           => 'Liste de présences signée',
                        'data'           => $data,
                        'condo_id'       => $assembly['condo_id'],
                    ])
                    ->update(['parent_node_id' => $parentNode['id'] ?? null])
                    ->first();

                // link back original doc to signed doc
                // #memo - original documents remain "invisible", only signed version should be accessible through EDMS fs tree
                Document::id($assembly['register_document_id'])
                    ->update(['signed_document_id' => $document['id']]);

                self::id($id)->update(['signed_register_document_id' => $document['id']]);
            }
            catch(\Exception $e) {
                trigger_error("APP::unable to generate signed attendance register:" . $e->getMessage(), EQ_REPORT_ERROR);
            }
        }
    }

    protected static function onafterOpen($self) {

        $self
            ->do('generate_signable_attendance_register')
            // #todo - confirm need for distinction between assembly_time & session_time_start
            // ->update(['session_time_start' => time()])
            ->read(['condo_id', 'assembly_organizer_identity_id', 'assembly_items_ids']);

        foreach($self as $id => $assembly) {
            // create a special attendee as 'secretary' relating to the organizer (can be manually modified afterwards)
            AssemblyAttendee::create([
                    'assembly_id'   => $id,
                    'condo_id'      => $assembly['condo_id'],
                    'identity_id'   => $assembly['assembly_organizer_identity_id'],
                    'attendee_role' => 'secretary'
                ]);
        }
    }

    protected static function onafterAdjourn($self, $dispatch) {
        foreach($self as $id => $assembly) {
            // invalidity alert is not relevant anymore
            $dispatch->cancel('realestate.governance.assembly.invalid', 'realestate\governance\Assembly', $id);
        }
    }



    /**
     * Marks the general assembly as valid, held and closed.
     *
     * Indicates that the assembly (AG) was valid, took place successfully,
     * and is now finished. The meeting minutes (PV) have been produced and
     * signed by the secretary and the president, as well as by the attendees
     * who were present.
     *
     * Use when finalizing an assembly: update status, attach signed minutes,
     * and prevent further modifications to the assembly record.
     */
    protected static function onafterClose($self) {
        $self
            ->do('generate_minutes_correspondences')
            ->do('send_minutes');

        // #todo - planifier les éventuelles tâches liées à des décisions prises durant l'assemblée
        // use dedicated action
        $self->read(['assembly_minutes_entries_ids' => ['id']]);
        foreach($self as $id => $assembly) {
        }
    }

    protected static function onbeforeSend($self) {
        $self
            ->do('generate_ownerships')
            ->do('generate_invitation_correspondences')
            ->do('send_invitation');
    }


    /**
     * field assembly_location  must be encoded manually
     * #todo - set AssemblVenue with preferences for each Condo
     */
    protected static function doAutoAssignLocation($self) {
        $self->read(['assembly_location', 'condo_id' => ['address_street', 'address_zip', 'address_city']]);
        foreach($self as $id => $assembly) {
            /*
            if(strlen($assembly['assembly_location'] ?? '') > 0) {
                continue;
            }
            $location = $assembly['condo_id']['address_street'];
            if($assembly['condo_id']['address_zip']) {
                $location .= ' ' . $assembly['condo_id']['address_zip'];
            }
            if($assembly['condo_id']['address_city']) {
                $location .= ' ' . $assembly['condo_id']['address_city'];
            }
            self::id($id)->update(['assembly_location' => $location]);
            */
        }
    }

    protected static function onupdateCondoId($self) {
        $self->do('auto_assign_location');
    }

    /**
     * Create the structure of the assembly based on selected template
     *
     */
    protected static function onupdateAssemblyTemplateId($self) {
        $self->read(['condo_id', 'assembly_template_id']);
        foreach($self as $id => $assembly) {
            $assemblyTemplate = AssemblyTemplate::id($assembly['assembly_template_id'])
                ->read([
                    'heading_text_call',
                    'heading_text_minutes',
                    'closing_text_call',
                    'closing_text_minutes'
                ])
                ->first();

            self::id($id)->update([
                    'heading_text_call'     => $assemblyTemplate['heading_text_call'],
                    'heading_text_minutes'  => $assemblyTemplate['heading_text_minutes'],
                    'closing_text_call'     => $assemblyTemplate['closing_text_call'],
                    'closing_text_minutes'  => $assemblyTemplate['closing_text_minutes']
                ]);

            // remove existing assembly items
            AssemblyItem::search(['assembly_id', '=', $id])->delete(true);

            // create apportionment map
            $map_apportionments = [];
            $apportionments = Apportionment::search(['condo_id', '=', $assembly['condo_id']])->read(['code']);

            foreach($apportionments as $apportionment_id => $apportionment) {
                $map_apportionments[$apportionment['code']] = $apportionment_id;
            }

            // we must perform creation in 2-pass in order to map group ids, if any
            $map_parent_groups_ids = [];

            // pass-1
            $assemblyItemTemplates = AssemblyItemTemplate::search([
                    ['assembly_template_id', '=', $assembly['assembly_template_id']],
                    ['is_group', '=', true]
                ])
                ->read([
                    'name',
                    'order',
                    'code',
                    'is_group',
                    'has_parent_group'
                ]);

            foreach($assemblyItemTemplates as $item_template_id => $itemTemplate) {
                $groupItem = AssemblyItem::create([
                        'condo_id'              => $assembly['condo_id'],
                        'assembly_id'           => $id,
                        'name'                  => $itemTemplate['name'],
                        'code'                  => $itemTemplate['code'],
                        'order'                 => $itemTemplate['order'],
                        'assembly_template_id'  => $assembly['assembly_template_id'],
                        'is_group'              => $itemTemplate['is_group'],
                        'has_parent_group'      => $itemTemplate['has_parent_group']
                    ])
                    ->first();

                $map_parent_groups_ids[$item_template_id] = $groupItem['id'];
            }

            // pass-2
            $assemblyItemTemplates = AssemblyItemTemplate::search([
                    ['assembly_template_id', '=', $assembly['assembly_template_id']],
                    ['is_group', '=', false]
                ])
                ->read([
                    'name',
                    'code',
                    'order',
                    'assembly_template_id',
                    'is_group',
                    'has_parent_group',
                    'parent_group_id',
                    'description_call',
                    'description_minutes',
                    'description_ballot',
                    'has_vote_required',
                    'majority',
                    'apportionment_code'
                ]);

            foreach($assemblyItemTemplates as $itemTemplate) {
                $parent_group_id = null;
                if($itemTemplate['has_parent_group']) {
                    $parent_group_id = $map_parent_groups_ids[$itemTemplate['parent_group_id']] ?? null;
                }
                $item = AssemblyItem::create([
                        'condo_id'              => $assembly['condo_id'],
                        'assembly_id'           => $id,
                        'name'                  => $itemTemplate['name'],
                        'code'                  => $itemTemplate['code'],
                        'order'                 => $itemTemplate['order'],
                        'assembly_template_id'  => $assembly['assembly_template_id'],
                        'is_group'              => $itemTemplate['is_group'],
                        'has_parent_group'      => $itemTemplate['has_parent_group'],
                        'parent_group_id'       => $parent_group_id,
                        'description_call'      => $itemTemplate['description_call'],
                        'description_minutes'   => $itemTemplate['description_minutes'],
                        'description_ballot'    => $itemTemplate['description_ballot'],
                        'has_vote_required'     => $itemTemplate['has_vote_required'],
                        'majority'              => $itemTemplate['majority']
                    ])
                    ->first();

                // assign apportionment based on code
                if($itemTemplate['apportionment_code']) {
                    AssemblyItem::id($item['id'])
                        ->update([
                            'apportionment_id' => $map_apportionments[$itemTemplate['apportionment_code']] ?? null
                        ]);
                }
            }

        }
    }

    protected static function onupdateAssemblyItemsIds($self) {
        $self->do('refresh_items_order');
    }

    /**
     * Generate invites for each ownership.
     */
    protected static function doGenerateInvitationCorrespondences($self) {
        $self->read(['condo_id', 'ownerships_ids' => ['representative_owner_id']]);
        foreach($self as $id => $assembly) {
            // remove any previously created invite
            AssemblyInvitationCorrespondence::search(['assembly_id', '=', $id])->delete(true);

            foreach($assembly['ownerships_ids'] as $ownership_id => $ownership) {
                if(!$ownership['representative_owner_id']) {
                    continue;
                }

                // init prefs
                $communication_methods = [
                        'email'                     => false,
                        'postal'                    => false,
                        'postal_registered'         => false,
                        'postal_registered_receipt' => false
                    ];

                // fetch Ownership communication preferences
                $communicationPreference = OwnershipCommunicationPreference::search([
                        ['condo_id', '=', $assembly['condo_id']],
                        ['ownership_id', '=', $ownership_id],
                        ['communication_reason', '=', 'general_assembly_call']
                    ])
                    ->read([
                        'has_channel_email',
                        'has_channel_postal',
                        'has_channel_postal_registered',
                        'has_channel_postal_registered_receipt'
                    ])
                    ->first();

                if($communicationPreference) {
                    $communication_methods = [
                            'email'                     => $communicationPreference['has_channel_email'],
                            'postal'                    => $communicationPreference['has_channel_postal'],
                            'postal_registered'         => $communicationPreference['has_channel_postal_registered'],
                            'postal_registered_receipt' => $communicationPreference['has_channel_postal_registered_receipt']
                        ];
                }

                // if not requested otherwise, invite must be sent through registered postal mail
                if(!in_array(true, $communication_methods, true)) {
                    $communication_methods['postal_registered'] = true;
                }

                foreach($communication_methods as $communication_method => $communication_method_flag) {
                    if(!$communication_method_flag) {
                        continue;
                    }

                    AssemblyInvitationCorrespondence::create([
                        'condo_id'              => $assembly['condo_id'],
                        'assembly_id'           => $id,
                        'ownership_id'          => $ownership_id,
                        'owner_id'              => $ownership['representative_owner_id'],
                        'communication_method'  => $communication_method
                    ]);
                }
            }

        }
    }



    /**
     * Generate minutes report for each ownership.
     */
    protected static function doGenerateMinutesCorrespondences($self) {
        $self->read(['condo_id', 'ownerships_ids' => ['representative_owner_id']]);
        foreach($self as $id => $assembly) {
            // remove any previously created invite
            AssemblyInvitationCorrespondence::search(['assembly_id', '=', $id])->delete(true);

            foreach($assembly['ownerships_ids'] as $ownership_id => $ownership) {
                if(!$ownership['representative_owner_id']) {
                    continue;
                }

                // init prefs
                $communication_methods = [
                        'email'                     => false,
                        'postal'                    => false,
                        'postal_registered'         => false,
                        'postal_registered_receipt' => false
                    ];

                // fetch Ownership communication preferences
                $communicationPreference = OwnershipCommunicationPreference::search([
                        ['condo_id', '=', $assembly['condo_id']],
                        ['ownership_id', '=', $ownership_id],
                        ['communication_reason', '=', 'general_assembly_call']
                    ])
                    ->read([
                        'has_channel_email',
                        'has_channel_postal',
                        'has_channel_postal_registered',
                        'has_channel_postal_registered_receipt'
                    ])
                    ->first();

                if($communicationPreference) {
                    $communication_methods = [
                            'email'                     => $communicationPreference['has_channel_email'],
                            'postal'                    => $communicationPreference['has_channel_postal'],
                            'postal_registered'         => $communicationPreference['has_channel_postal_registered'],
                            'postal_registered_receipt' => $communicationPreference['has_channel_postal_registered_receipt']
                        ];
                }

                // if not requested otherwise, invite must be sent through registered postal mail
                if(!in_array(true, $communication_methods, true)) {
                    $communication_methods['postal_registered'] = true;
                }

                foreach($communication_methods as $communication_method => $communication_method_flag) {
                    if(!$communication_method_flag) {
                        continue;
                    }

                    AssemblyMinutesCorrespondence::create([
                        'condo_id'              => $assembly['condo_id'],
                        'assembly_id'           => $id,
                        'ownership_id'          => $ownership_id,
                        'owner_id'              => $ownership['representative_owner_id'],
                        'communication_method'  => $communication_method
                    ]);
                }
            }

        }
    }


    /**
     * Handle minutes sending for each ownership.
     *   - Queue Emails (Only owners with contact preference set as email are handled)
     *   - Create an exporting task that will asynchronously generate the export lines & related documents.
     *
     */
    protected static function doSendMinutes($self, $cron) {
        $self->read([
            'name',
            'condo_id',
            'minutes_exporting_task_id',
            'assembly_minutes_correspondences_ids' => ['communication_method']
        ]);

        foreach($self as $id => $assembly) {

            // remove previously created exporting task (and lines), if any
            if($assembly['minutes_exporting_task_id']) {
                ExportingTask::id($assembly['minutes_exporting_task_id'])->delete(true);
            }

            $map_communication_methods = [];

            foreach($assembly['assembly_minutes_correspondences_ids'] as $assembly_invitation_id => $assemblyMinutesCorrespondence) {
                // update global map to acknowledge that at least one invitation uses that communication method
                $map_communication_methods[$assemblyMinutesCorrespondence['communication_method']] = true;
            }

            if(isset($map_communication_methods['email'])) {
                // schedule queuing of invite emails
                $cron->schedule(
                    "realestate.assembly.send-minutes.{$id}",
                    time() + (5 * 60),
                    'realestate_governance_Assembly_send-minutes',
                    [
                        'id'  => $id
                    ]
                );
            }

            // handle non-digital communication methods
            if(count(array_diff(array_keys($map_communication_methods), ['email'])) > 0) {

                // schedule generation of a zip archive containing printable documents
                $exportingTask = ExportingTask::create([
                        'name'          => "{$assembly['name']} - Export des courriers du PV",
                        'condo_id'      => $assembly['condo_id'],
                        'object_class'  => static::class,
                        'object_id'     => $id
                    ])
                    ->first();

                foreach($map_communication_methods as $communication_method => $flag) {
                    if($communication_method === 'email') {
                        continue;
                    }
                    ExportingTaskLine::create([
                            'exporting_task_id' => $exportingTask['id'],
                            'name'              => "{$assembly['name']} - Export du PV - {$communication_method}",
                            'controller'        => 'realestate_governance_Assembly_export-minutes',
                            'params'            => json_encode([
                                    'id'                    => $id,
                                    'communication_method'  => $communication_method
                                ])
                        ]);
                }

                self::id($id)->update([
                        'minutes_exporting_task_id' => $exportingTask['id']
                    ]);
            }
        }
    }

    /**
     * Handle invites sending for each ownership.
     *   - Queue Emails (Only owners with contact preference set as email are handled)
     *   - Create an exporting task that will asynchronously generate the export lines & related documents.
     *
     */
    protected static function doSendInvitation($self, $cron) {
        $self->read([
            'name',
            'condo_id',
            'invitations_exporting_task_id',
            'assembly_invitation_correspondences_ids' => ['communication_method']
        ]);

        foreach($self as $id => $assembly) {

            // remove previously created exporting task (and lines), if any
            if($assembly['invitations_exporting_task_id']) {
                ExportingTask::id($assembly['invitations_exporting_task_id'])->delete(true);
            }

            $map_communication_methods = [];

            foreach($assembly['assembly_invitation_correspondences_ids'] as $assembly_invitation_id => $assemblyInvitation) {
                // update global map to acknowledge that at least one invitation uses that communication method
                $map_communication_methods[$assemblyInvitation['communication_method']] = true;
            }

            if(isset($map_communication_methods['email'])) {
                // schedule queuing of invite emails
                $cron->schedule(
                    "realestate.assembly.send-invitation.{$id}",
                    // #todo - increase delay if necessary
                    time() + (1 * 60),
                    'realestate_governance_Assembly_send-invitation',
                    [
                        'id'  => $id
                    ]
                );
            }

            // handle non-digital communication methods
            if(count(array_diff(array_keys($map_communication_methods), ['email'])) > 0) {

                // schedule generation of a zip archive containing printable documents
                $exportingTask = ExportingTask::create([
                        'name'          => "{$assembly['name']} - Export des courriers des invitations",
                        'condo_id'      => $assembly['condo_id'],
                        'object_class'  => static::class,
                        'object_id'     => $id
                    ])
                    ->first();

                foreach($map_communication_methods as $communication_method => $flag) {
                    if($communication_method === 'email') {
                        continue;
                    }
                    ExportingTaskLine::create([
                            'exporting_task_id' => $exportingTask['id'],
                            'name'              => "{$assembly['name']} - Export des invitations - {$communication_method}",
                            'controller'        => 'realestate_governance_Assembly_export-invitation',
                            'params'            => json_encode([
                                    'id'                    => $id,
                                    'communication_method'  => $communication_method
                                ])
                        ]);
                }

                self::id($id)->update([
                        'assembly_invitation_date'      => time(),
                        'invitations_exporting_task_id' => $exportingTask['id']
                    ]);
            }
        }
    }

    /**
     * This action is called when all mandates have been validated and that we're ready to finalize attendance
     *
     *
     */
    protected static function doGenerateRepresentations($self) {
        $self->read(['condo_id', 'step', 'assembly_attendees_ids']);
        // Loop through all assembly attendees
        // For each attendee:
        //   1. If the attendee is a direct owner of a single ownership (i.e. not in conflict or co-representation):
        //      - Create a representation (type: OWNER)
        //      - If a proxy-based representation already exists for this ownership, remove it
        //        (since the physical presence of the owner overrides any mandate).
        //
        //   2. For each valid mandate that the attendee holds (i.e. person gave power to represent):
        //      - Create a representation (type: PROXY) for the ownership covered by the mandate,
        //        BUT only if no representation already exists for this ownership:
        //          - If the owner is present (representation type: OWNER) → skip
        //          - If the ownership is already represented by someone else via a valid mandate → skip
        foreach($self as $id => $assembly) {
            if(in_array($assembly['step'], ['opening', 'attendance_closure', 'mandate_validation'])) {
                continue;
            }
            // remove any previous AssemblyRepresentation
            AssemblyRepresentation::search(['assembly_id', '=', $id])->delete(true);

            $attendees = AssemblyAttendee::ids($assembly['assembly_attendees_ids'])
                ->read(['identity_id', 'assembly_mandates_ids' => ['status', 'is_valid', 'ownership_id']]);

            foreach($attendees as $attendee_id => $attendee) {

                // 1) add the ownerships corresponding to the identity of the attendee (there might be none)
                $owners = Owner::search([
                        ['condo_id', '=', $assembly['condo_id']],
                        ['identity_id', '=', $attendee['identity_id']]
                    ])
                    ->read(['ownership_id' => ['id', 'ownership_type', 'representative_owner_id']]);

                foreach($owners as $owner_id => $owner) {
                    // #todo - add only if owner is the official mandatory for the joint ownership
                    // if ($owner['ownership_id']['ownership_type'] === 'joint' && $owner['ownership_id']['representative_owner_id'] === $owner_id) {}
                    $ownership_id = $owner['ownership_id']['id'];
                    $existingRepresentations = AssemblyRepresentation::search([
                            ['assembly_id', '=', $id],
                            ['ownership_id', '=', $ownership_id]
                        ])
                        ->read(['representation_type']);

                    foreach($existingRepresentations as $representation_id => $representation) {
                        if($representation['representation_type'] === 'proxy') {
                            AssemblyRepresentation::id($representation_id)->delete(true);
                        }
                        else {
                            // ownership is already represented by an owner, skip representation
                            continue 2;
                        }
                    }
                    AssemblyRepresentation::create([
                        'condo_id'              => $assembly['condo_id'],
                        'assembly_id'           => $id,
                        'attendee_id'           => $attendee_id,
                        'ownership_id'          => $ownership_id,
                        'representation_type'   => 'owner'
                    ]);
                }

                // 2) add ownerships from valid mandates
                foreach($attendee['assembly_mandates_ids'] as $mandate_id => $assemblyMandate) {
                    if($assemblyMandate['status'] === 'validated' && $assemblyMandate['is_valid'] && $assemblyMandate['ownership_id']) {
                        $existingRepresentations = AssemblyRepresentation::search([
                                ['assembly_id', '=', $id],
                                ['ownership_id', '=', $assemblyMandate['ownership_id']]
                            ]);
                        if($existingRepresentations->count() > 0) {
                            continue;
                        }
                        AssemblyRepresentation::create([
                            'condo_id'              => $assembly['condo_id'],
                            'assembly_id'           => $id,
                            'attendee_id'           => $attendee_id,
                            'ownership_id'          => $assemblyMandate['ownership_id'],
                            'representation_type'   => 'proxy',
                            'assembly_mandate_id'   => $mandate_id
                        ]);
                    }
                }
            }

            AssemblyAttendee::ids($assembly['assembly_attendees_ids'])->update(['has_representation' => null]);

        }
    }

    protected static function doAddExtraRepresentation($self, $values) {
        $self->read(['condo_id', 'step']);
        foreach($self as $id => $assembly) {
            // #memo - only while in `agenda_processing`
            if(in_array($assembly['step'], ['opening', 'attendance_closure', 'mandate_validation'])) {
                continue;
            }

            $attendee_id = $values['assembly_attendee_id'];

            if(!$attendee_id) {
                continue;
            }

            $attendee = AssemblyAttendee::id($attendee_id)
                ->read(['identity_id']);

            // 1) add the ownerships corresponding to the identity of the attendee
            $owners = Owner::search([['condo_id', '=', $assembly['condo_id']], ['identity_id', '=', $attendee['identity_id']]])
                ->read(['ownership_id' => ['id', 'ownership_type', 'representative_owner_id']]);

            foreach($owners as $owner_id => $owner) {
                // #todo - add only if owner is the official mandatory for the joint ownership
                // if ($owner['ownership_id']['ownership_type'] === 'joint' && $owner['ownership_id']['representative_owner_id'] === $owner_id) {}
                $ownership_id = $owner['ownership_id']['id'];
                $existingRepresentations = AssemblyRepresentation::search([['assembly_id', '=', $id], ['ownership_id', '=', $ownership_id]])->read(['representation_type']);


                // #todo - ? allow to void mandate representation and replace with the actual attendee
                foreach($existingRepresentations as $representation_id => $representation) {
                    if($representation['representation_type'] === 'proxy') {
                        AssemblyRepresentation::id($representation_id)->delete(true);
                    }
                    else {
                        // ownership is already represented by an owner, skip representation
                        continue 2;
                    }
                }

                AssemblyRepresentation::create([
                    'condo_id'              => $assembly['condo_id'],
                    'assembly_id'           => $id,
                    'attendee_id'           => $attendee_id,
                    'ownership_id'          => $ownership_id,
                    'representation_type'   => 'owner'
                ]);
            }

            AssemblyAttendee::ids($attendee_id)->update(['has_representation' => null]);

        }
    }

    protected static function doCloseAttendance($self) {
        $self
            ->do('validate_attendees')
            ->update(['step' => 'mandate_validation']);
    }

    protected static function doValidateAttendees($self) {
        $self->read(['condo_id', 'ownerships_ids', 'assembly_attendees_ids' => ['identity_id', 'attendee_role', 'assembly_mandates_ids']]);
        foreach($self as $id => $assembly) {
            /*
                    'invalid_attendee',      // Attendee not designated or not authorized
                    'double_attendance',     // The representation is redundant (owner prevails if present)
                    'missing_mandate',       // Joint ownership without mandate
                    'conflict'               // disagreement between 2 or more owners from a joint ownership
            */

            // remove any pending (draft) mandates
            AssemblyMandate::search([['assembly_id','=', $id], ['status', '=', 'pending']])->delete(true);

            foreach($assembly['assembly_attendees_ids'] as $assembly_attendee_id => $assemblyAttendee) {
                $attendee_ownerships_ids = [];
                $owners = Owner::search([['condo_id', '=', $assembly['condo_id']], ['identity_id', '=', $assemblyAttendee['identity_id']]])
                    ->read(['ownership_id' => ['id', 'ownership_type', 'representative_owner_id']]);

                foreach($owners as $owner_id => $owner) {
                    $attendee_ownerships_ids[] = $owner['ownership_id']['id'];
                }

                $assemblyMandates = AssemblyMandate::ids($assemblyAttendee['assembly_mandates_ids']);

                // an attendee who is not owner, has no proxy and is neither president nor secretary, is invalid
                if(
                    $assemblyMandates->count() <= 0
                    && count(array_intersect($assembly['ownerships_ids'], $attendee_ownerships_ids)) <= 0
                    && !in_array($assemblyAttendee['attendee_role'], ['president', 'secretary'])
                ) {
                    AssemblyAttendee::id($assembly_attendee_id)->update(['is_valid' => false, 'invalidity_reason' => 'invalid_attendee']);
                    continue;
                }

                // #todo - complete if necessary
            }

        }
    }

    /**
     *
     * #memo - we must wait before the assembly validation since encoding or choice of applicable mandate should be possible while assembly is not yet constituted
     */
    protected static function doValidateMandates($self) {
        $self->read(['count_shares', 'ownerships_ids', 'assembly_attendees_ids' => ['is_valid', 'assembly_mandates_ids']]);
        foreach($self as $id => $assembly) {
            $map_covered_ownerships_ids = [];

            foreach($assembly['assembly_attendees_ids'] as $assemblyAttendee) {

                $assemblyMandates = AssemblyMandate::ids($assemblyAttendee['assembly_mandates_ids'])
                    ->read(['mandate_shares', 'mandate_type', 'has_wet_signature', 'ownership_id', 'mandate_document_id']);

                $count_mandate_shares = 0;
                $count_proxies = 0;

                foreach($assemblyMandates as $assembly_mandate_id => $assemblyMandate) {
                    if(!$assemblyAttendee['is_valid']) {
                        AssemblyMandate::id($assembly_mandate_id)->update(['is_valid' => false, 'invalidity_reason' => 'invalid_attendee']);
                        continue;
                    }

                    ++$count_proxies;

                    if(!in_array($assemblyMandate['ownership_id'], $assembly['ownerships_ids'])) {
                        AssemblyMandate::id($assembly_mandate_id)->update(['is_valid' => false, 'invalidity_reason' => 'not_owner']);
                        continue;
                    }
                    $count_mandate_shares += $assemblyMandate['mandate_shares'];
                    if($count_mandate_shares > $assembly['count_shares'] * 0.1) {
                        if($count_proxies > 3) {
                            AssemblyMandate::id($assembly_mandate_id)->update(['is_valid' => false, 'invalidity_reason' => 'too_many_proxies']);
                            continue;
                        }
                    }
                    if($assemblyMandate['mandate_type'] === 'written') {
                        if(!$assemblyMandate['has_wet_signature']) {
                            AssemblyMandate::id($assembly_mandate_id)->update(['is_valid' => false, 'invalidity_reason' => 'no_signature']);
                            continue;
                        }
                    }
                    // electronic signature
                    else {
                        // retrouver le doc et vérifier la signature électronique
                    }

                    if(isset($map_covered_ownerships_ids[$assemblyMandate['ownership_id']])) {
                        AssemblyMandate::id($assembly_mandate_id)->update(['is_valid' => false, 'invalidity_reason' => 'duplicated_owner']);
                        continue;
                    }

                    $map_covered_ownerships_ids[$assemblyMandate['ownership_id']] = true;
                }
            }
        }
        $self
            ->update(['step' => 'representation_validation'])
            ->do('generate_representations');
    }

    protected static function doValidateRepresentations($self) {
        $self
            ->update(['step' => 'assembly_validation'])
            ->do('generate_printable_attendance_register');
    }

    protected static function canupdate($self, $values) {
        $self->read(['status', 'assembly_template_id', 'assembly_date', 'session_time_start']);
        foreach($self as $id => $assembly) {
            if($assembly['assembly_template_id'] && isset($values['assembly_template_id'])) {
                return ['assembly_template_id' => ['template_cannot_be_changed' => 'Once set, assembly template cannot be changed.']];
            }
            if($assembly['assembly_date'] && isset($values['assembly_date'])) {
                // return ['assembly_date' => ['date_cannot_be_changed' => 'Once set, assembly date cannot be changed.']];
            }
            if($assembly['session_time_start'] && isset($values['session_time_start'])) {
                // return ['session_time_start' => ['time_cannot_be_changed' => 'Once set, assembly session start time cannot be changed.']];
            }
            $allowed_fields = [];
            switch($assembly['status']) {
                case 'pending':
                    // all changes are allowed
                    break;
                case 'published':
                    // en principe on ne peut plus rien modifier à partir d'ici
                    // on peut ajouter des documents sur les assembly_items
                    break;
                case 'sending':
                    break;
                case 'sent':
                case 'in_progress':
                    // on peut modifier les votes sur les assembly_items
                    // on peut générer les minutes
                    break;
                case 'held':
                case 'adjourned':
                    $allowed_fields = ['minutes_exporting_task_id'];
                    if(count(array_diff(array_keys($values), $allowed_fields)) > 0) {
                        return ['status' => ['not_allowed' => 'Published assembly cannot be modified.']];
                    }
                    break;
            }
        }
        return parent::canupdate($self, $values);
    }

    protected static function policyCanPublish($self) {
        $result = [];
        $self->read(['assembly_location', 'assembly_date', 'assembly_organizer_identity_id', 'session_time_start', 'session_time_end']);

        foreach($self as $id => $assembly) {

            if(strlen($assembly['assembly_location']  ?? '') <= 0) {
                $result[$id] = [
                    'missing_assembly_location' => 'The assembly location is mandatory.'
                ];
                continue;
            }

            if(!$assembly['assembly_date'] || $assembly['assembly_date'] <= 0) {
                $result[$id] = [
                    'missing_assembly_date' => 'The assembly date must be provided.'
                ];
                continue;
            }

            if($assembly['assembly_date'] <= strtotime(date('Y-m-d'))) {
                $result[$id] = [
                    'passed_assembly_date' => 'Assembly date must be in the future.'
                ];
                continue;
            }

            if(!$assembly['session_time_start'] || $assembly['session_time_start'] <= 0) {
                $result[$id] = [
                    'missing_session_time_start' => 'The assembly time start must be provided.'
                ];
                continue;
            }

            if(!$assembly['assembly_organizer_identity_id']) {
                $result[$id] = [
                    'missing_assembly_organizer' => 'A person must be designated as the assembly organization.'
                ];
                continue;
            }

            // #todo - complete consistency tests
            $assemblyItems = AssemblyItem::search(['assembly_id', '=', $id])->read(['has_choices', 'assembly_item_choices_ids']);
            foreach($assemblyItems as $assembly_item_id => $assemblyItem) {
                if($assemblyItem['has_choices']) {
                    if(count($assembly['assembly_item_choices_ids']) <= 0) {
                        $result[$id] = [
                            'missing_assembly_item_choices' => 'At least one item marked with choices has no choices.'
                        ];
                        continue 2;
                    }
                    if(count($assembly['assembly_item_choices_ids']) > 5) {
                        $result[$id] = [
                            'exceeded_assembly_item_choices' => 'An assembly item cannot have more than 5 choices.'
                        ];
                        continue 2;
                    }
                }
            }

        }

        return $result;
    }

    /**
     * A valid Assembly in progress can only be closed if all its AssemblyItems (resolutions) are closed.
     *
     */
    protected static function policyCanClose($self) {
        $result = [];
        $self->read(['status', 'step', 'assembly_items_ids' => ['status']]);

        foreach($self as $id => $assembly) {
            if($assembly['status'] === 'in_progress' && $assembly['step'] === 'agenda_processing') {
                $result[$id] = [
                    'wrong_status' => 'Assembly can only be closed while in progress.'
                ];
            }
            foreach($assembly['assembly_items_ids'] as $assemblyItem) {
                if(!in_array($assemblyItem['status'], ['closed', 'adjourned'], true)) {
                    $result[$id] = [
                        'assembly_item_not_closed' => 'At least one assembly item is not closed.'
                    ];
                    continue 2;
                }
            }
        }

        return $result;
    }

    protected static function policyCanMarkSent($self) {
        $result = [];
        $self->read([
                'invitations_exporting_task_id' => ['status', 'is_exported'],
                'assembly_invitation_correspondences_ids' => ['@domain' => ['communication_method', '=', 'email'], 'id']
            ]);

        foreach($self as $id => $assembly) {
            // run invites check and dispatch/dismiss alerts if necessary (non-blocking)
            \eQual::run('do', 'realestate_governance_Assembly_check-invitation-completeness', ['id' => $id]);
            if($assembly['invitations_exporting_task_id']) {
                if($assembly['invitations_exporting_task_id']['status'] !== 'ready') {
                    $result[$id] = [
                        'invite_export_task_not_ready' => 'Archive holding invitation correspondences not marked as ready.'
                    ];
                    continue;
                }
                if(!$assembly['invitations_exporting_task_id']['is_exported']) {
                    $result[$id] = [
                        'invite_export_not_downloaded' => 'The archive with invitation correspondences has not been downloaded.'
                    ];
                    continue;
                }
            }
            foreach($assembly['assembly_invitation_correspondences_ids'] as $assembly_invitation_correspondence_id => $assemblyInvitationCorrespondence) {
                // #todo #demo - reactivate - for demo only
                /*
                // find related email (there should be only one)
                $email = \core\Mail::search([['object_id', '=', $assembly_invitation_correspondence_id], ['object_class', '=', 'realestate\governance\AssemblyInvitationCorrespondence']])
                    ->read(['status'])
                    ->first();
                if(!$email) {
                    trigger_error("APP::Missing \core\Mail object for AssemblyInvitationCorrespondence[{$assembly_invitation_correspondence_id}]", EQ_REPORT_ERROR);
                    $result[$id] = [
                        'invite_email_not_found' => 'At least one correspondence is not attached to a mandatory email.'
                    ];
                    break;
                }
                if($email['status'] !== 'sent') {
                    trigger_error("APP::Non-sent email for AssemblyInvitationCorrespondence[{$assembly_invitation_correspondence_id}]", EQ_REPORT_WARNING);
                    $result[$id] = [
                        'invite_email_not_sent' => 'At least one email correspondence has not been sent.'
                    ];
                    break;
                }
                */
            }
        }
        return $result;
    }

    protected static function policyCanScheduleSecondSession($self) {
        $result = [];
        $self->read(['status', 'is_valid', 'has_second_session']);

        foreach($self as $id => $assembly) {
            if($assembly['has_second_session']) {
                $result[$id] = [
                    'assembly_already_adjourned' => 'A second session has already been scheduled.'
                ];
                continue;
            }
            if($assembly['is_valid']) {
                $result[$id] = [
                    'assembly_is_valid' => 'A valid assembly cannot be adjourned.'
                ];
                continue;
            }
            if($assembly['status'] !== 'adjourned') {
                $result[$id] = [
                    'assembly_not_adjourned' => 'Assembly must be adjourned to schedule a second session.'
                ];
                continue;
            }
        }

        return $result;
    }


    protected static function policyCanCloseMinutesSigning($self) {
        $result = [];
        $self->read(['step', 'assembly_attendees_ids' => ['attendee_role', 'has_signed_minutes']]);

        foreach($self as $id => $assembly) {
            if($assembly['step'] !== 'minutes_signing') {
                $result[$id] = [
                    'invalid_step' => 'Current step prohibits closing the signing of the minutes document.'
                ];
                continue;
            }

            $count_president = 0;
            $count_secretary = 0;

            foreach($assembly['assembly_attendees_ids'] as $attendee_id => $assemblyAttendee) {
                if($assemblyAttendee['attendee_role'] === 'president') {
                    ++$count_president;
                    if(!$assemblyAttendee['has_signed_minutes']) {
                        $result[$id] = [
                            'missing_president_signature' => 'President must sign the minutes.'
                        ];
                        continue 2;
                    }
                }
                if($assemblyAttendee['attendee_role'] === 'secretary') {
                    ++$count_secretary;
                    if(!$assemblyAttendee['has_signed_minutes']) {
                        $result[$id] = [
                            'missing_secretary_signature' => 'Secretary must sign the minutes.'
                        ];
                        continue 2;
                    }
                }

            }

            if($count_president <= 0) {
                $result[$id] = [
                    'missing_president_attendee' => 'A president must be elected amongst the attendees.'
                ];
                continue;
            }

            if($count_secretary <= 0) {
                $result[$id] = [
                    'missing_secretary_attendee' => 'A secretary must be chosen amongst the attendees.'
                ];
                continue;
            }
        }

        return $result;
    }

    protected static function policyCanGenerateMinutes($self) {
        $result = [];
        $self->read(['status', 'step', 'minutes_document_id', 'signed_minutes_document_id']);

        foreach($self as $id => $assembly) {
            if(!in_array($assembly['step'], ['agenda_processing', 'minutes_confirmation'])) {
                $result[$id] = [
                    'minutes_generation_not_allowed' => 'Current step prohibits creation of the minutes document.'
                ];
                continue;
            }
            if($assembly['signed_minutes_document_id']) {
                $result[$id] = [
                    'minutes_already_signed' => 'A signed version of the minutes already exists.'
                ];
                continue;
            }
        }
        return $result;
    }

    protected static function policyIsAssemblyValid($self) {
        $result = [];
        $self->read(['condo_id', 'assembly_date', 'count_shares', 'count_represented_shares', 'count_owners', 'count_represented_owners']);

        foreach($self as $id => $assembly) {

            if($assembly['count_owners'] <= 0) {
                $result[$id] = [
                    'invalid_count_owners' => 'No owners are censed for the assembly.'
                ];
                continue;
            }

            if($assembly['count_shares'] <= 0) {
                $result[$id] = [
                    'quorum_shares_not_met' => 'Not enough shares represented.'
                ];
                continue;
            }

            // double-quorum is only applicable if less than 3/4 of the shares are represented
            if( ($assembly['count_represented_shares'] / $assembly['count_shares']) <= 0.75 ) {

                // strictly more than 50% of the ownerships
                if( ($assembly['count_represented_owners'] / $assembly['count_owners']) <= 0.5 ) {
                    $result[$id] = [
                        'quorum_owners_not_met' => 'Not enough owners present or represented.'
                    ];
                    continue;
                }

                // at least half of the shares of the statutory apportionment
                if( ($assembly['count_represented_shares'] / $assembly['count_shares']) < 0.5) {
                    $result[$id] = [
                        'invalid_count_shares' => 'Less that 50% of the shares are censed for the assembly.'
                    ];
                    continue;
                }
            }

            $ownerships_ids = self::computeOwnershipsIds($assembly['condo_id'], $assembly['assembly_date']);

            if($assembly['count_owners'] != count($ownerships_ids)) {
                $result[$id] = [
                    'invalid_count_owners' => 'Some Owners should be present but are missing.'
                ];
                continue;
            }

            // Among the attendees, there must be exactly one president and one secretary.
            $attendees = AssemblyAttendee::search(['assembly_id', '=', $id])
                ->read('attendee_role');

            $count_president = 0;
            $count_secretary = 0;
            foreach($attendees as $attendee) {
                switch($attendee['attendee_role']) {
                    case 'president':
                        ++$count_president;
                        break;
                    case 'secretary':
                        ++$count_secretary;
                        break;
                }
            }

            if($count_president < 1) {
                // #memo - president might not be known yet at validation (an election might be required)
                /*
                $result[$id] = [
                    'missing_president' => 'A president must be selected amongst attendees.'
                ];
                continue;
                */
            }
            elseif($count_president > 1) {
                $result[$id] = [
                    'multiple_presidents' => 'Only one president can be selected amongst attendees.'
                ];
                continue;
            }

            if($count_secretary < 1) {
                $result[$id] = [
                    'missing_secretary' => 'A secretary must be selected amongst attendees.'
                ];
                continue;
            }
            elseif($count_secretary > 1) {
                $result[$id] = [
                    'multiple_secretaries' => 'Only one secretary can be selected amongst attendees.'
                ];
                continue;
            }


        }
        return $result;
    }

    protected static function doScheduleSecondSession($self) {
        $self->read([
            'condo_id', 'name', 'assembly_type', 'assembly_template_id',
            'assembly_date',
            'heading_text_call',
            'heading_text_minutes',
            'closing_text_call',
            'closing_text_minutes'
        ]);

        foreach($self as $id => $assembly) {

            $secondSessionAssembly = Assembly::create([
                'condo_id'              => $assembly['condo_id'],
                'name'                  => $assembly['name'],
                'assembly_type'         => $assembly['assembly_type'],
                'assembly_date'         => $assembly['assembly_date'] + (15 * 86400),
                'is_second_session'     => true,
                'related_assembly_id'   => $id,
                'heading_text_call'     => $assembly['heading_text_call'],
                'heading_text_minutes'  => $assembly['heading_text_minutes'],
                'closing_text_call'     => $assembly['closing_text_call'],
                'closing_text_minutes'  => $assembly['closing_text_minutes']
            ])
            ->first();

            self::id($id)->update([
                    'has_second_session' => true,
                    'second_session_assembly_id' => $secondSessionAssembly['id']
                ]);

            // we must perform creation in 2-pass in order to map group ids, if any
            $map_parent_groups_ids = [];

            // pass-1 - create groups
            $assemblyItems = AssemblyItem::search([
                    ['assembly_id', '=', $id],
                    ['is_group', '=', true]
                ])
                ->read([
                    'name',
                    'order',
                    'code',
                    'is_group',
                ]);

            foreach($assemblyItems as $assembly_item_id => $assemblyItem) {
                $groupItem = AssemblyItem::create([
                        'condo_id'              => $assembly['condo_id'],
                        'assembly_id'           => $id,
                        'name'                  => $assemblyItem['name'],
                        'code'                  => $assemblyItem['code'],
                        'order'                 => $assemblyItem['order'],
                        'assembly_template_id'  => $assembly['assembly_template_id'],
                        'is_group'              => $assemblyItem['is_group']
                    ])
                    ->first();

                $map_parent_groups_ids[$assembly_item_id] = $groupItem['id'];
            }

            // pass-2
            $assemblyItems = AssemblyItem::search([
                    ['assembly_id', '=', $id],
                    ['is_group', '=', false]
                ])
                ->read([
                    'name',
                    'code',
                    'order',
                    'assembly_template_id',
                    'is_group',
                    'has_parent_group',
                    'parent_group_id',
                    'description_call',
                    'description_minutes',
                    'description_ballot',
                    'has_vote_required',
                    'majority',
                    'apportionment_id'
                ]);

            foreach($assemblyItems as $assemblyItem) {
                $parent_group_id = null;
                if($assemblyItem['has_parent_group']) {
                    $parent_group_id = $map_parent_groups_ids[$assemblyItem['parent_group_id']] ?? null;
                }
                $item = AssemblyItem::create([
                        'condo_id'              => $assembly['condo_id'],
                        'assembly_id'           => $id,
                        'name'                  => $assemblyItem['name'],
                        'code'                  => $assemblyItem['code'],
                        'order'                 => $assemblyItem['order'],
                        'assembly_template_id'  => $assembly['assembly_template_id'],
                        'is_group'              => $assemblyItem['is_group'],
                        'has_parent_group'      => $assemblyItem['has_parent_group'],
                        'parent_group_id'       => $parent_group_id,
                        'description_call'      => $assemblyItem['description_call'],
                        'description_minutes'   => $assemblyItem['description_minutes'],
                        'description_ballot'    => $assemblyItem['description_ballot'],
                        'has_vote_required'     => $assemblyItem['has_vote_required'],
                        'majority'              => $assemblyItem['majority'],
                        'apportionment_id'      => $assemblyItem['apportionment_id']
                    ])
                    ->first();
            }

        }
    }

    protected static function doValidateAll($self) {
        $self->read(['status', 'step']);
        foreach($self as $id => $assembly) {
            if($assembly['status'] !== 'in_progress') {
                continue;
            }
            if($assembly['step'] === 'mandate_validation') {
                self::id($id)
                    ->do('validate_mandates')
                    ->do('validate_representations')
                    ->do('validate_assembly');
            }
            elseif($assembly['step'] === 'representation_validation') {
                self::id($id)
                    ->do('validate_representations')
                    ->do('validate_assembly');
            }
            elseif($assembly['step'] === 'assembly_validation') {
                self::id($id)
                    ->do('validate_assembly');
            }
        }
    }

    /**
     * At this stage, we have validated the mandates and created related ownership links.
     *
     */
    protected static function doValidateAssembly($self, $access, $dispatch) {
        $self->read(['step', 'count_shares', 'count_represented_shares', 'count_owners', 'count_represented_owners']);

        foreach($self as $id => $assembly) {
            if(!in_array($assembly['step'], ['representation_validation', 'assembly_validation'])) {
                throw new \Exception('wrong_step', EQ_ERROR_INVALID_PARAM);
            }
            $inconsistencies = $access->isCompliant('is_assembly_valid', self::getType(), [$id]);
            if(count($inconsistencies)) {
                if(isset($inconsistencies['quorum_owners_not_met'])) {
                    self::id($id)->update([
                            'is_valid'          => false,
                            'invalidity_reason' => 'quorum_owners_not_met'
                        ]);
                }
                elseif(isset($inconsistencies['quorum_shares_not_met'])) {
                    self::id($id)->update([
                            'is_valid'          => false,
                            'invalidity_reason' => 'quorum_shares_not_met'
                        ]);
                }
                else {
                    throw new \Exception(serialize(['is_valid' => $inconsistencies]), EQ_ERROR_INVALID_PARAM);
                }

                $dispatch->dispatch('realestate.governance.assembly.invalid', 'realestate\governance\Assembly', $id, 'important');
                continue;
            }

            // remove previous alert (if any)
            $dispatch->cancel('realestate.governance.assembly.invalid', 'realestate\governance\Assembly', $id);
            // assembly is valid, move one step forward
            self::id($id)->update(['step' => 'agenda_processing']);
        }
    }

    protected static function doAcceptMinutes($self) {
        $self->update(['step' => 'minutes_signing']);
    }


    protected static function doCloseMinutesSigning($self) {
        $self
            ->update(['step' => 'assembly_closing'])
            ->do('generate_printable_minutes')
            ->transition('close');
        // #todo - use a specific controller for confirming the auto actions to perform
    }

    protected static function doGeneratePrintableMinutes($self) {
        $self->read([
                'condo_id',
                'minutes_document_id'
            ]);

        foreach($self as $id => $assembly) {

            if(!$assembly['minutes_document_id']) {
                throw new \Exception('missing_mandatory_document', EQ_ERROR_INVALID_PARAM);
            }

            // generate a new doc
            try {
                $data = \eQual::run('get', 'realestate_governance_Assembly_minutes_render-pdf', [
                        'id'                    => $id,
                        'signed'                => true
                    ]);

                // store document in related General Assembly folder
                $parentNode = Node::search([
                        ['condo_id', '=', $assembly['condo_id']],
                        ['node_type', '=', 'folder'],
                        ['code', '=', 'general_meetings']
                    ])
                    ->first();

                $document = Document::create([
                        'name'           => 'PV d\'Assemblée signé',
                        'data'           => $data,
                        'condo_id'       => $assembly['condo_id'],
                    ])
                    ->update(['parent_node_id' => $parentNode['id'] ?? null])
                    ->first();

                // link back original doc to signed doc
                // #memo - original documents remain "invisible", only signed version should be accessible through EDMS fs tree
                Document::id($assembly['minutes_document_id'])
                    ->update(['signed_document_id' => $document['id']]);

                self::id($id)->update(['signed_minutes_document_id' => $document['id']]);
            }
            catch(\Exception $e) {
                trigger_error("APP::unable to generate signed minutes:" . $e->getMessage(), EQ_REPORT_ERROR);
            }
        }
    }

    protected static function doGenerateSignableMinutes($self) {
        $self
            ->update(['step' => 'minutes_confirmation'])
            ->read(['condo_id', 'minutes_document_id']);

        foreach($self as $id => $assembly) {

            // remove previous version (there shouldn't be any)
            if($assembly['minutes_document_id']) {
                Document::id($assembly['register_document_id'])->delete(true);
            }

            // generate a new doc
            try {
                $data = \eQual::run('get', 'realestate_governance_Assembly_minutes_render-pdf', ['id' => $id]);

                $document = Document::create([
                        'name'      => 'PV d\'Assemblée',
                        'data'      => $data,
                        'condo_id'  => $assembly['condo_id']
                    ])
                    ->first();

                // #memo - original documents remain "invisible", only signed version should be accessible through EDMS fs tree
                self::id($id)
                    ->update([
                        'minutes_document_id' => $document['id']
                    ]);
            }
            catch(\Exception $e) {
                trigger_error("APP::unable to generate minutes document:" . $e->getMessage(), EQ_REPORT_ERROR);
                throw($e);
            }
        }
    }


    protected static function calcCountOwners($self) {
        $result = [];
        $self->read(['status', 'ownerships_ids']);
        foreach($self as $id => $assembly) {
            if($assembly['status'] === 'pending') {
                continue;
            }
            $result[$id] = count($assembly['ownerships_ids']);
        }
        return $result;
    }

    protected static function calcCountRepresentedOwners($self) {
        $result = [];
        $self->read(['step', 'assembly_representations_ids']);
        foreach($self as $id => $assembly) {
            // compute only when all attendees have been encoded and proxies have been validated
            // #memo - this is commented since it is useful for users to have a temporary value, as indication of completeness of the Assembly
            /*
            if(in_array($assembly['step'], ['opening', 'attendance_closure', 'mandate_validation', 'representation_validation'])) {
                continue;
            }
            */

            $map_ownerships_ids = [];

            $representations = AssemblyRepresentation::ids($assembly['assembly_representations_ids'])
                ->read(['ownership_id']);

            foreach($representations as $representation) {
                $map_ownerships_ids[$representation['ownership_id']] = true;
            }

            $result[$id] = count($map_ownerships_ids);
        }
        return $result;
    }

    protected static function calcCountRepresentedShares($self) {
        $result = [];
        $self->read(['status', 'step', 'condo_id', 'assembly_representations_ids', 'assembly_date']);
        foreach($self as $id => $assembly) {

            // #memo - this is commented since it is useful for users to have a temporary value, as indication of completeness of the Assembly
            /*
            if(in_array($assembly['step'], ['opening', 'attendance_closure', 'mandate_validation', 'representation_validation'])) {
                continue;
            }
            */

            $property_lots_ids = [];

            // 1) identify the lots
            $representations = AssemblyRepresentation::ids($assembly['assembly_representations_ids'])
                ->read(['ownership_id']);

            foreach($representations as $representation) {
                $ownership = Ownership::id($representation['ownership_id'])
                    ->read(['property_lot_ownerships_ids' => ['property_lot_id', 'date_to']])
                    ->first();

                foreach($ownership['property_lot_ownerships_ids'] as $propertyLotOwnership) {
                    if(!$propertyLotOwnership['date_to'] || $propertyLotOwnership['date_to'] > $assembly['assembly_id']['assembly_date']) {
                        $property_lots_ids[] = $propertyLotOwnership['property_lot_id'];
                    }
                }

            }

            // 2) find the statutory key
            $apportionment = Apportionment::search([['condo_id', '=', $assembly['condo_id']], ['is_statutory', '=', true]])->first();

            // 3) get the total shares for the targeted lots
            if(!$apportionment) {
                continue;
            }

            $apportionmentShares = PropertyLotApportionmentShare::search([
                    ['apportionment_id', '=', $apportionment['id']],
                    ['property_lot_id', 'in', $property_lots_ids],
                ])
                ->read(['property_lot_shares']);

            $shares = 0;
            foreach($apportionmentShares as $apportionmentShare) {
                $shares += $apportionmentShare['property_lot_shares'];
            }

            $result[$id] = $shares;
        }
        return $result;
    }


    // #todo - ability to clone AG
    protected static function doCloneAssembly() {
    }

}
