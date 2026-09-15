<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use hr\employee\Employee;
use identity\Contact;
use identity\Identity;

$providers = eQual::inject(['context', 'orm', 'auth', 'access']);

$tests = [

    '1101' => [
            'description'       => "Create Employee & Identity.",
            'help'              => "Create an Employee, assign it to an Identity and asserts that backlink is set.",
            'return'            => ['boolean'],
            'arrange'           => function() use($providers) {

                    $identity = Identity::create([
                        "type_id"   => 1,
                        "type"      => "IN",
                        "lang_id"   => 2
                    ])->first();

                    return $identity['id'];
                },
            'act'               => function($identity_id) use($providers) {
                    Employee::create()->update(['identity_id' => $identity_id]);

                    return $identity_id;
                },
            'assert'            => function($identity_id) use($providers) {
                    $employee = Employee::search([['identity_id', '=', $identity_id]])->first();
                    $identity = Identity::id($identity_id)->read(['employee_id'])->first();

                    return $identity['employee_id'] == $employee['id'];
                },
            'rollback'          => function() use($providers) {
                }
        ],

    '1102' => [
            'description'       => "Synchronize common fields from Identity to linked Facets.",
            'help'              => "Update an Identity, propagate null, and repair an inconsistent Facet with sync_from_identity.",
            'arrange'           => function() {
                    $identity = Identity::create([
                        'type_id'   => 1,
                        'type'      => 'IN',
                        'firstname' => 'Alice',
                        'lastname'  => 'Identity',
                        'email'     => 'alice.initial@example.test',
                        'lang_id'   => 2
                    ])->first();

                    $employee = Employee::create([
                        'identity_id' => $identity['id'],
                        'firstname'   => 'Alice',
                        'lastname'    => 'Identity',
                        'email'       => 'alice.initial@example.test'
                    ])->first();

                    $contact = Contact::create([
                        'identity_id' => $identity['id'],
                        'firstname'   => 'Alice',
                        'lastname'    => 'Identity',
                        'email'       => 'alice.initial@example.test',
                        'position'    => 'Director'
                    ])->first();

                    Identity::id($identity['id'])->update([
                        'employee_id' => $employee['id'],
                        'contact_id'  => $contact['id']
                    ]);

                    return [
                        'identity_id' => $identity['id'],
                        'employee_id' => $employee['id'],
                        'contact_id'  => $contact['id']
                    ];
                },
            'act'               => function($ids) use($providers) {
                    $checks = [];

                    // 1. A common Identity field is copied to every linked Facet.
                    Identity::id($ids['identity_id'])->update([
                        'email' => 'alice.updated@example.test'
                    ]);

                    $employee_after_update = Employee::id($ids['employee_id'])->read(['email'])->first();
                    $contact_after_update = Contact::id($ids['contact_id'])->read(['email'])->first();
                    $checks['identity_updates_all_facets'] =
                        $employee_after_update['email'] === 'alice.updated@example.test'
                        && $contact_after_update['email'] === 'alice.updated@example.test';

                    // 4. Null is a value and must also be propagated.
                    Identity::id($ids['identity_id'])->update(['email' => null]);

                    $employee_after_null = Employee::id($ids['employee_id'])->read(['email'])->first();
                    $contact_after_null = Contact::id($ids['contact_id'])->read(['email'])->first();
                    $checks['identity_null_updates_all_facets'] =
                        is_null($employee_after_null['email'])
                        && is_null($contact_after_null['email']);

                    // 8. The explicit action repairs all sampled common fields.
                    /* @var \equal\orm\ObjectManager $orm */
                    $orm = $providers['orm'];
                    $orm_events = $orm->disableEvents();
                    try {
                        $orm->update(Contact::getType(), $ids['contact_id'], [
                            'firstname' => 'Outdated',
                            'email'     => 'outdated@example.test'
                        ]);
                    }
                    finally {
                        $orm->enableEvents($orm_events);
                    }

                    Contact::id($ids['contact_id'])->do('sync_from_identity');
                    $contact_after_sync = Contact::id($ids['contact_id'])
                        ->read(['firstname', 'email'])
                        ->first();
                    $checks['sync_from_identity_repairs_common_fields'] =
                        $contact_after_sync['firstname'] === 'Alice'
                        && is_null($contact_after_sync['email']);

                    return [
                        'created' => $ids,
                        'checks'  => $checks
                    ];
                },
            'assert'            => function($result) {
                    return !in_array(false, $result['checks'], true);
                },
            'rollback'          => function($result) use($providers) {
                    if(!is_array($result) || !isset($result['created'])) {
                        return;
                    }

                    /* @var \equal\orm\ObjectManager $orm */
                    $orm = $providers['orm'];
                    $orm->delete(Contact::getType(), $result['created']['contact_id'], true);
                    $orm->delete(Employee::getType(), $result['created']['employee_id'], true);
                    $orm->delete(Identity::getType(), $result['created']['identity_id'], true);
                }
        ],

    '1103' => [
            'description'       => "Synchronize a Facet change through Identity to the other Facets.",
            'help'              => "Cover Facet updates, attachment rules, Facet-only fields, null propagation, and automatic Identity creation.",
            'arrange'           => function() {
                    $identity = Identity::create([
                        'type_id'   => 1,
                        'type'      => 'IN',
                        'firstname' => 'Bruno',
                        'lastname'  => 'Identity',
                        'email'     => 'bruno.identity@example.test',
                        'lang_id'   => 2
                    ])->first();

                    $employee = Employee::create([
                        'identity_id' => $identity['id'],
                        'firstname'   => 'Bruno',
                        'lastname'    => 'Identity',
                        'email'       => 'bruno.identity@example.test'
                    ])->first();

                    Identity::id($identity['id'])->update(['employee_id' => $employee['id']]);

                    $contact = Contact::create([
                        'state'     => 'draft',
                        'firstname' => 'Detached',
                        'lastname'  => 'Contact',
                        'email'     => 'detached@example.test',
                        'position'  => 'Assistant'
                    ])->first();

                    $override_contact = Contact::create([
                        'state'     => 'draft',
                        'firstname' => 'Detached',
                        'lastname'  => 'Override',
                        'email'     => 'detached.override@example.test'
                    ])->first();

                    return [
                        'identity_id'         => $identity['id'],
                        'employee_id'         => $employee['id'],
                        'contact_id'          => $contact['id'],
                        'override_contact_id' => $override_contact['id']
                    ];
                },
            'act'               => function($ids) {
                    $checks = [];

                    // 5. Unmodified common fields come from Identity when attaching a Facet.
                    Contact::id($ids['contact_id'])->update([
                        'state'       => 'instance',
                        'identity_id' => $ids['identity_id']
                    ]);
                    $contact_after_attachment = Contact::id($ids['contact_id'])
                        ->read(['firstname', 'email'])
                        ->first();
                    $checks['attachment_uses_identity_fields'] =
                        $contact_after_attachment['firstname'] === 'Bruno'
                        && $contact_after_attachment['email'] === 'bruno.identity@example.test';

                    // 3. A Facet-only field does not change Identity or another Facet.
                    Contact::id($ids['contact_id'])->update(['position' => 'Manager']);
                    $identity_after_position = Identity::id($ids['identity_id'])->read(['email'])->first();
                    $employee_after_position = Employee::id($ids['employee_id'])->read(['email'])->first();
                    $contact_after_position = Contact::id($ids['contact_id'])->read(['position'])->first();
                    $checks['facet_only_field_stays_local'] =
                        $identity_after_position['email'] === 'bruno.identity@example.test'
                        && $employee_after_position['email'] === 'bruno.identity@example.test'
                        && $contact_after_position['position'] === 'Manager';

                    // 2. A common Facet field goes up to Identity and down to the other Facets.
                    Contact::id($ids['contact_id'])->update([
                        'email' => 'bruno.contact@example.test'
                    ]);
                    $identity_after_facet_update = Identity::id($ids['identity_id'])->read(['email'])->first();
                    $employee_after_facet_update = Employee::id($ids['employee_id'])->read(['email'])->first();
                    $checks['facet_update_cascades'] =
                        $identity_after_facet_update['email'] === 'bruno.contact@example.test'
                        && $employee_after_facet_update['email'] === 'bruno.contact@example.test';

                    // 4. Null follows the same upward and downward path.
                    Contact::id($ids['contact_id'])->update(['email' => null]);
                    $identity_after_facet_null = Identity::id($ids['identity_id'])->read(['email'])->first();
                    $employee_after_facet_null = Employee::id($ids['employee_id'])->read(['email'])->first();
                    $checks['facet_null_cascades'] =
                        is_null($identity_after_facet_null['email'])
                        && is_null($employee_after_facet_null['email']);

                    // 6. An explicitly modified common field wins during attachment.
                    Contact::id($ids['override_contact_id'])->update([
                        'state'       => 'instance',
                        'identity_id' => $ids['identity_id'],
                        'email'       => 'bruno.override@example.test'
                    ]);
                    $override_contact = Contact::id($ids['override_contact_id'])
                        ->read(['firstname', 'email'])
                        ->first();
                    $identity_after_override = Identity::id($ids['identity_id'])->read(['email'])->first();
                    $employee_after_override = Employee::id($ids['employee_id'])->read(['email'])->first();
                    $checks['explicit_attachment_field_wins'] =
                        $override_contact['firstname'] === 'Bruno'
                        && $override_contact['email'] === 'bruno.override@example.test'
                        && $identity_after_override['email'] === 'bruno.override@example.test'
                        && $employee_after_override['email'] === 'bruno.override@example.test';

                    // 7. Creating a Facet without Identity creates and initializes one.
                    $generated_employee = Employee::create([
                        'firstname' => 'Diane',
                        'lastname'  => 'Generated',
                        'email'     => 'diane.generated@example.test'
                    ])->read(['identity_id'])->first();
                    $generated_identity = Identity::id($generated_employee['identity_id'])
                        ->read(['firstname', 'lastname', 'email'])
                        ->first();
                    $checks['facet_creates_identity'] =
                        $generated_identity['firstname'] === 'Diane'
                        && $generated_identity['lastname'] === 'Generated'
                        && $generated_identity['email'] === 'diane.generated@example.test';

                    $ids['generated_employee_id'] = $generated_employee['id'];
                    $ids['generated_identity_id'] = $generated_employee['identity_id'];

                    return [
                        'created' => $ids,
                        'checks'  => $checks
                    ];
                },
            'assert'            => function($result) {
                    return !in_array(false, $result['checks'], true);
                },
            'rollback'          => function($result) use($providers) {
                    if(!is_array($result) || !isset($result['created'])) {
                        return;
                    }

                    /* @var \equal\orm\ObjectManager $orm */
                    $orm = $providers['orm'];
                    $orm->delete(Contact::getType(), [
                        $result['created']['contact_id'],
                        $result['created']['override_contact_id']
                    ], true);
                    $orm->delete(Employee::getType(), [
                        $result['created']['employee_id'],
                        $result['created']['generated_employee_id']
                    ], true);
                    $orm->delete(Identity::getType(), [
                        $result['created']['identity_id'],
                        $result['created']['generated_identity_id']
                    ], true);
                }
        ],

];
