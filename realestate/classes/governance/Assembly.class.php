<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace realestate\governance;

use documents\Document;
use documents\DocumentSignature;
use documents\navigation\Node;
use hr\role\RoleAssignment;
use realestate\ownership\Owner;
use realestate\ownership\Ownership;
use realestate\ownership\OwnershipCommunicationPreference;
use realestate\property\Apportionment;
use realestate\property\PropertyLotApportionmentShare;
use realestate\property\PropertyLotOwnership;

class Assembly extends \equal\orm\Model {

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
                'store'             => true
            ],

            'attendance_register_document_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'documents\Document',
                'description'       => 'Generated document to serve as attendance register.',
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null]]
            ],

            'minutes_document_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'documents\Document',
                'description'       => 'Generated document holding the minutes of the assembly.',
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null]]
            ],

            'heading_text_call' => [
                'type'              => 'string',
                'usage'             => 'text/plain.small',
                'description'       => "Text of the assembly call.",
                'required'          => false
            ],

            'heading_text_minutes' => [
                'type'              => 'string',
                'usage'             => 'text/plain.small',
                'description'       => "Heading text of the assembly minutes.",
                'required'          => false
            ],

            'closing_text_call' => [
                'type'              => 'string',
                'usage'             => 'text/plain.small',
                'description'       => "Closing text of the assembly call.",
                'required'          => false
            ],

            'closing_text_minutes' => [
                'type'              => 'string',
                'usage'             => 'text/plain.small',
                'description'       => "Closing text of the assembly minutes.",
                'required'          => false
            ],

            'assembly_date' => [
                'type'              => 'datetime',
                'description'       => "Scheduled date and time of the assembly (cannot be modified).",
                'default'           => time()
            ],

            'assembly_location' => [
                'type'              => 'string',
                'description'       => "Location of the assembly (always announced in advance)."
            ],

            'assembly_type' => [
                'type'              => 'string',
                'description'       => "Type of assembly.",
                'selection'         => [
                    'statutory',
                    'takeover',
                    'ordinary',
                    'extraordinary',
                    'partial'
                ],
                'default'           => 'ordinary'
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
                'order'          => 'order'
            ],

            'assembly_invitations_ids' => [
                'type'           => 'one2many',
                'description'    => "Invitations sent for the assembly.",
                'foreign_object' => 'realestate\governance\AssemblyInvitation',
                'foreign_field'  => 'assembly_id'
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
                'help'              => "Representations are generated automatically, based on assembly_proxies_ids, and are used to link the attendee to the ownerships they represent in the assembly (directly or with a mandate).",
                'domain'            => [ ['condo_id', '=', 'object.condo_id'] ]
            ],

            'assembly_attendees_ids' => [
                'type'           => 'one2many',
                'description'    => "Attendees of the assembly.",
                'foreign_object' => 'realestate\governance\AssemblyAttendee',
                'foreign_field'  => 'assembly_id',
                'domain'         => [['condo_id', '=', 'object.condo_id']]
            ],

            'minutes_entries_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\governance\AssemblyMinutesEntry',
                'foreign_field'     => 'assembly_id',
                'description'       => "Minutes entries from all agenda items."
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

            'is_valid' => [
                'type'           => 'boolean',
                'description'    => "Flag marking the assembly as valid.",
                'default'        => true
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

            'assembly_proxies_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\governance\AssemblyProxy',
                'foreign_field'     => 'assembly_id',
                'description'       => "The list of mandates registered for the assembly.",
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
                'function'          => 'calcCountOwners',
                'store'             => true,
                'readonly'          => true
            ],

            'count_represented_owners' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'description'       => "The number of owners that are present or represented in the assembly.",
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
                    'proxy_validation',
                    'representation_validation',
                    'assembly_validation',
                    'agenda_processing'
                ],
                'visible'         => ['status', '=', 'in_progress']
            ],

            'status' => [
                'type'           => 'string',
                'description'    => "Workflow status of the assembly.",
                'default'        => 'pending',
                'selection'      => [
                    'pending',
                    'published',
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
                    'revert' => [
                        'description'   => 'Revert Assembly to `pending` in order to allow changes.',
                        'policies'      => [/**/],
                        'status'        => 'pending'
                    ],
                    'send' => [
                        'description'   => 'Send the invitations, and mark the Assembly as sent.',
                        'policies'      => [/**/],
                        'onbefore'      => 'onbeforeSend',
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

            'generate_ownerships' => [
                'description'   => 'Generate ownerships.',
                'help'          => 'i.e. Ownerships that are known at the current date to own at least one property lots having at least one share in the statutory apportionment. This action can be re-executed in order to refresh the list, in case a transfer took place in the meantime.',
                'policies'      => [],
                'function'      => 'doGenerateOwnerships'
            ],

            'generate_invites' => [
                'description'   => 'Generate invites.',
                'policies'      => [],
                'function'      => 'doGenerateInvites'
            ],

            'send_invites' => [
                'description'   => 'Send invites.',
                'help'          => 'Only email invites can be sent automatically. Mail & mandates must be sent manually.',
                'policies'      => [],
                'function'      => 'doSendInvites'
            ],

            'close_attendance' => [
                'description'   => 'Close the attendance step and attempt to validate proxies.',
                'help'          => 'No more attendances can be added after this. Step to `proxy_validation`.',
                'policies'      => [],
                'function'      => 'doCloseAttendance'
            ],

            'validate_attendees' => [
                'description'   => 'Validate attendees, based on their role, mandates and ownerships.',
                'policies'      => [/* */],
                'function'      => 'doValidateAttendees'
            ],

            'validate_proxies' => [
                'description'   => 'Verifies the validity of the proxies and generate links with ownerships.',
                'help'          => "This action is meant to be performed at the proxy_validation step. We check the validity conditions of each proxy.
                    If a proxy is invalid, we mark it as invalid and add the reason.
                    Triggers representations generation, and step to `representation_validation`.",
                'policies'      => [],
                'function'      => 'doValidateProxies'
            ],

            'generate_representations' => [
                'description'   => 'Generate the representations bases on the attendees and valid mandates.',
                'policies'      => [/* */],
                'function'      => 'doGenerateRepresentations'
            ],

            'validate_representations' => [
                'description'   => '.',
                'help'          => ".",
                'policies'      => [/* */],
                'function'      => 'doValidateRepresentations'
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

            'validate_assembly' => [
                'description'   => 'Verifies that the double-quorum is met.',
                'help'          => 'This action is meant to be performed at step `assembly_validation`',
                'policies'      => [/* 'can_generate_accounting_entry' */],
                'function'      => 'doValidateAssembly'
            ],
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

            'is_assembly_valid' => [
                'description' => 'Verifies that the constituted assembly is valid, ',
                'help'        => "In order to be valid, the assembly must meet the representation criteria depending on its type.",
                'function'    => 'policyIsAssemblyValid'
            ],
            // 'can_be_open'
        ];
    }

    protected static function doGenerateOwnerships($self) {
        $self->read(['condo_id', 'assembly_date', 'ownerships_ids']);

        foreach($self as $id => $assembly) {

            // remove any previously retrieved ownership
            $ids_to_remove = array_map(function ($a) {return -$a;}, $assembly['ownerships_ids']);
            self::id($id)->update(['ownerships_ids' => $ids_to_remove]);

            // generate the ownerships_ids : list of expected Ownerships allowed to attend the Assembly
            $map_ownerships_ids = [];

            $propertyLotOwnerships = PropertyLotOwnership::search([
                    ['condo_id', '=', $assembly['condo_id']]
                ])
                ->read(['date_to', 'ownership_id', 'property_lot_id' => ['is_primary']]);

            foreach($propertyLotOwnerships as $propertyLotOwnership) {
                if(!$propertyLotOwnership['date_to'] || $propertyLotOwnership['date_to'] > $assembly['assembly_date']) {
                    if($propertyLotOwnership['property_lot_id']['is_primary']) {
                        $map_ownerships_ids[$propertyLotOwnership['ownership_id']] = true;
                    }
                }
            }
            self::id($id)->update(['ownerships_ids' => array_keys($map_ownerships_ids)]);
        }
    }

    protected static function calcAssemblyOrganizerIdentityId($self) {
        $result = [];
        $self->read(['condo_id' => ['managing_agent_id' => ['identity_id', 'agent_identity_type']]]);
        foreach($self as $id => $assembly) {
            if(!$assembly['condo_id']) {
                continue;
            }

            if($assembly['condo_id']['managing_agent_id']['agent_identity_type'] === 'owner') {
                $result[$id] = $assembly['condo_id']['managing_agent_id']['identity_id'];
            }
            elseif($assembly['condo_id']['managing_agent_id']['agent_identity_type'] === 'professional') {
                $assignment = RoleAssignment::search([
                        ['condo_id', '=', $assembly['condo_id']],
                        ['role_code', '=', 'condo_manager']
                    ])
                    ->read(['identity_id'])
                    ->first();
                if($assignment) {
                    $result[$id] = $assignment['identity_id'];
                }
            }
        }
        return $result;
    }

    protected static function onafterPublish($self) {
        // generate the ownerships_ids : list of expected Ownerships allowed to attend the Assembly
        $self->do('generate_ownerships');
    }


    /**
     * Generate the attendance document (required for signatures)
     *
     */
    protected static function doGenerateSignableAttendanceRegister($self) {
        $self->read(['condo_id', 'attendance_register_document_id']);
        foreach($self as $id => $assembly) {

            // remove previous version (there shouldn't be any)
            if($assembly['attendance_register_document_id']) {
                Document::id($assembly['attendance_register_document_id'])->delete(true);
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
                        'attendance_register_document_id' => $document['id']
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
                'attendance_register_document_id'
            ]);

        foreach($self as $id => $assembly) {

            if(!$assembly['attendance_register_document_id']) {
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
                Document::id($assembly['attendance_register_document_id'])
                    ->update(['signed_document_id' => $document['id']]);

            }
            catch(\Exception $e) {
                trigger_error("APP::unable to generate signed attendance register:" . $e->getMessage(), EQ_REPORT_ERROR);
            }

        }
    }

    protected static function onafterOpen($self) {

        $self
            ->do('generate_signable_attendance_register')
            ->update(['session_time_start' => time()])
            ->read(['condo_id', 'assembly_organizer_identity_id']);

        // create an special attendee as 'secretary' relating to the organizer
        // #memo - Attendee can be manually modified afterwards
        foreach($self as $id => $assembly) {
            AssemblyAttendee::create([
                    'assembly_id'   => $id,
                    'condo_id'      => $assembly['condo_id'],
                    'identity_id'   => $assembly['assembly_organizer_identity_id'],
                    'attendee_role' => 'secretary'
                ]);
        }
    }

    protected static function onafterClose($self) {
        // générer le PV
        // générer un document de PV et l'associer à l'assembly

// #todo - generate_minutes

        // faire signer le PV par le secrétaire et président + présents
        // -> il faut une trace des personnes ayant signé (ok)

    }

    protected static function onbeforeSend($self) {
        $self
            ->do('generate_invites')
            // only owners with contact preference set as email will be handled
            ->do('send_invites');
    }

    protected static function doAutoAssignLocation($self) {
        $self->read(['condo_id' => ['address_street', 'address_zip', 'address_city']]);
        foreach($self as $id => $assembly) {
            $location = $assembly['condo_id']['address_street'];
            if($assembly['condo_id']['address_zip']) {
                $location .= ' ' . $assembly['condo_id']['address_zip'];
            }
            if($assembly['condo_id']['address_city']) {
                $location .= ' ' . $assembly['condo_id']['address_city'];
            }
            self::id($id)->update(['assembly_location' => $location]);
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

            // we mut perform creation in 2-pass in order to map group ids, if any
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
                ]);

            foreach($assemblyItemTemplates as $item_template_id => $itemTemplate) {
                $groupItem = AssemblyItem::create([
                        'condo_id'              => $assembly['condo_id'],
                        'assembly_id'           => $id,
                        'name'                  => $itemTemplate['name'],
                        'code'                  => $itemTemplate['code'],
                        'order'                 => $itemTemplate['order'],
                        'assembly_template_id'  => $assembly['assembly_template_id'],
                        'is_group'              => $itemTemplate['is_group']
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

    protected static function doGenerateInvites($self) {
        $self->read(['condo_id', 'ownerships_ids' => ['representative_owner_id']]);
        foreach($self as $id => $assembly) {
            foreach($assembly['ownerships_ids'] as $ownership_id => $ownership) {
                if(!$ownership['representative_owner_id']) {
                    continue;
                }
                // if not requested otherwise, invite must be sent through registered postal mail
                $communication_method = 'postal_registered';
                // fetch Ownership communication preferences
                $communicationPreference = OwnershipCommunicationPreference::search([
                        ['condo_id', '=', $assembly['condo_id']],
                        ['ownership_id', '=', $ownership_id],
                        ['communication_reason', '=', 'general_assembly_call']
                    ])
                    ->read(['communication_method'])
                    ->first();

                if($communicationPreference) {
                    $communication_method = $communicationPreference['communication_method'];
                }

                AssemblyInvitation::create([
                    'condo_id'              => $assembly['condo_id'],
                    'assembly_id'           => $id,
                    'ownership_id'          => $ownership_id,
                    'owner_id'              => $ownership['representative_owner_id'],
                    'communication_method'  => $communication_method
                ]);

            }
        }
    }

    protected static function doSendInvites($self) {

    }

    // on fait ceci lorsque les proxy ont été validés qu'on est prêt à finaliser les présences
    protected static function doGenerateRepresentations($self) {
        $self->read(['condo_id', 'step', 'assembly_attendees_ids']);
        // Loop through all assembly attendees
        // For each attendee:
        //   1. If the attendee is a direct owner of a single ownership (i.e. not in conflict or co-representation):
        //      - Create a representation (type: OWNER)
        //      - If a proxy-based representation already exists for this ownership, remove it
        //        (since the physical presence of the owner overrides any proxy).
        //
        //   2. For each valid proxy that the attendee holds (i.e. person gave power to represent):
        //      - Create a representation (type: PROXY) for the ownership covered by the proxy,
        //        BUT only if no representation already exists for this ownership:
        //          - If the owner is present (representation type: OWNER) → skip
        //          - If the ownership is already represented by someone else via a valid proxy → skip
        foreach($self as $id => $assembly) {
            if(in_array($assembly['step'], ['opening', 'attendance_closure', 'proxy_validation'])) {
                continue;
            }
            $attendees = AssemblyAttendee::ids($assembly['assembly_attendees_ids'])
                ->read(['identity_id', 'assembly_proxies_ids' => ['is_valid', 'ownership_id']]);

            foreach($attendees as $attendee_id => $attendee) {

                // 1) add the ownerships corresponding to the identity of the attendee
                $owners = Owner::search([['condo_id', '=', $assembly['condo_id']], ['identity_id', '=', $attendee['identity_id']]])
                    ->read(['ownership_id' => ['id', 'ownership_type', 'representative_owner_id']]);

                foreach($owners as $owner_id => $owner) {
                    // #todo - add only if owner is the official mandatory for the joint ownership
                    // if ($owner['ownership_id']['ownership_type'] === 'joint' && $owner['ownership_id']['representative_owner_id'] === $owner_id) {}
                    $ownership_id = $owner['ownership_id']['id'];
                    $existingRepresentations = AssemblyRepresentation::search([['assembly_id', '=', $id], ['ownership_id', '=', $ownership_id]])->read(['representation_type']);

                    foreach($existingRepresentations as $representation_id => $representation) {
                        if($representation['representation_type'] ===' proxy') {
                            AssemblyRepresentation::id($representation_id)->delete(true);
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

                // 2) add ownerships from valid proxies
                foreach($attendee['assembly_proxies_ids'] as $proxy_id => $assemblyProxy) {
                    if($assemblyProxy['is_valid'] && $assemblyProxy['ownership_id']) {
                        $existingRepresentations = AssemblyRepresentation::search([['assembly_id', '=', $id], ['ownership_id', '=', $ownership_id]]);
                        if($existingRepresentations->count() > 0) {
                            continue;
                        }
                        AssemblyRepresentation::create([
                            'condo_id'              => $assembly['condo_id'],
                            'assembly_id'           => $id,
                            'attendee_id'           => $attendee_id,
                            'ownership_id'          => $assemblyProxy['ownership_id'],
                            'representation_type'   => 'proxy',
                            'assembly_proxy_id'     => $proxy_id
                        ]);
                    }
                }
            }
        }
    }

    protected static function doCloseAttendance($self) {
        $self
            ->do('validate_attendees')
            ->update(['step' => 'proxy_validation']);
    }

    protected static function doValidateAttendees($self) {
        $self->read(['condo_id', 'ownerships_ids', 'assembly_attendees_ids' => ['identity_id', 'attendee_role', 'assembly_proxies_ids']]);
        foreach($self as $id => $assembly) {
            /*
                    'invalid_attendee',      // Attendee not designated or not authorized
                    'double_attendance',     // The representation is redundant (owner prevails if present)
                    'missing_mandate',       // Joint ownership without mandate
                    'conflict'               // disagreement between 2 or more owners from a joint ownership
            */

            foreach($assembly['assembly_attendees_ids'] as $assembly_attendee_id => $assemblyAttendee) {
                $attendee_ownerships_ids = [];
                $owners = Owner::search([['condo_id', '=', $assembly['condo_id']], ['identity_id', '=', $assemblyAttendee['identity_id']]])
                    ->read(['ownership_id' => ['id', 'ownership_type', 'representative_owner_id']]);

                foreach($owners as $owner_id => $owner) {
                    $attendee_ownerships_ids[] = $owner['ownership_id']['id'];
                }

                $assemblyProxies = AssemblyProxy::ids($assemblyAttendee['assembly_proxies_ids']);

                // an attendee who is not owner, has no proxy and is neither president nor secretary, is invalid
                if(
                    $assemblyProxies->count() <= 0
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
    protected static function doValidateProxies($self) {
        $self->read(['count_shares', 'ownerships_ids', 'assembly_attendees_ids' => ['is_valid', 'assembly_proxies_ids']]);
        foreach($self as $id => $assembly) {
            $map_covered_ownerships_ids = [];

            foreach($assembly['assembly_attendees_ids'] as $assemblyAttendee) {

                $assemblyProxies = AssemblyProxy::ids($assemblyAttendee['assembly_proxies_ids'])
                    ->read(['proxy_shares', 'proxy_type', 'has_wet_signature', 'ownership_id', 'proxy_document_id']);

                $count_proxy_shares = 0;
                $count_proxies = 0;

                foreach($assemblyProxies as $assembly_proxy_id => $assemblyProxy) {
                    if(!$assemblyAttendee['is_valid']) {
                        AssemblyProxy::id($assembly_proxy_id)->update(['is_valid' => false, 'invalidity_reason' => 'invalid_attendee']);
                        continue;
                    }

                    ++$count_proxies;

                    if(!in_array($assemblyProxy['ownership_id'], $assembly['ownerships_ids'])) {
                        AssemblyProxy::id($assembly_proxy_id)->update(['is_valid' => false, 'invalidity_reason' => 'not_owner']);
                        continue;
                    }
                    $count_proxy_shares += $assemblyProxy['proxy_shares'];
                    if($count_proxy_shares > $assembly['count_shares'] * 0.1) {
                        if($count_proxies > 3) {
                            AssemblyProxy::id($assembly_proxy_id)->update(['is_valid' => false, 'invalidity_reason' => 'too_many_proxies']);
                            continue;
                        }
                    }
                    if($assemblyProxy['proxy_type'] === 'written') {
                        if(!$assemblyProxy['has_wet_signature']) {
                            AssemblyProxy::id($assembly_proxy_id)->update(['is_valid' => false, 'invalidity_reason' => 'no_signature']);
                            continue;
                        }
                    }
                    // electronic signature
                    else {
                        // retrouver le doc et vérifier la signature électronique
                    }

                    if(isset($map_covered_ownerships_ids[$assemblyProxy['ownership_id']])) {
                        AssemblyProxy::id($assembly_proxy_id)->update(['is_valid' => false, 'invalidity_reason' => 'duplicated_owner']);
                        continue;
                    }

                    $map_covered_ownerships_ids[$assemblyProxy['ownership_id']] = true;
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

    protected static function policyCanPublish($self) {
        $result = [];
        $self->read(['assembly_location']);

        foreach($self as $id => $assembly) {

            if(strlen($assembly['assembly_location']) <= 0) {
                $result[$id] = [
                    'missing_assembly_location' => 'The assembly location is mandatory.'
                ];
                continue;
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
                foreach($assembly['assembly_items_ids'] as $assembly_item) {
                    if($assembly_item['status'] !== 'closed') {
                        $result[$id] = [
                            'assembly_item_not_closed' => 'At least one assembly item is not closed.'
                        ];
                        continue 2;
                    }
                }
            }
        }

        return $result;
    }

    protected static function policyIsAssemblyValid($self) {
        $result = [];
        $self->read(['count_shares', 'count_represented_shares', 'count_owners', 'count_represented_owners']);

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

            // Among those present persons, there must be exactly one president and one secretary.
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
                $result[$id] = [
                    'missing_president' => 'A president must be selected amongst attendees.'
                ];
                continue;
            }
            elseif($count_president > 1) {
                $result[$id] = [
                    'multiple_presidents' => 'Only one president can be selected amongst attendees.'
                ];
                continue;
            }
            /*
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
            */

        }
        return $result;
    }

    /**
     * At this stage, we have validated the mandates and created related ownership links.
     *
     */
    protected static function doValidateAssembly($self, $access) {
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
                continue;
            }
            // assembly is valid, move one step forward
            self::id($id)->update(['step' => 'agenda_processing']);
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
            if(in_array($assembly['step'], ['opening', 'attendance_closure', 'proxy_validation', 'representation_validation'])) {
                continue;
            }

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

            if(in_array($assembly['step'], ['opening', 'attendance_closure', 'proxy_validation'])) {
                continue;
            }

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


}
