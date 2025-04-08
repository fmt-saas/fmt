<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace identity;

class Contact extends Identity {

    public function getTable() {
        // force table name to use distinct tables and ID columns
        return 'identity_contact';
    }

    public static function getName() {
        return "Contact";
    }

    public static function getDescription() {
        return "Contacts are persons that are attached to an identity.";
    }

    public static function getColumns() {

        return [

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => "The name of the Owner.",
                'relation'          => ['identity_id' => 'name'],
                'store'             => true,
                'readonly'          => true
            ],

            'position' => [
                'type'              => 'string',
                'description'       => 'Position of the contact (natural person) within the target organisation (legal person), e.g. \'director\', \'CEO\', \'Regional manager\'.'
            ]

        ];
    }

    public static function onupdateIdentityId($self) {
        $self->read(['identity_id']);
        foreach($self as $id => $contact) {
            Identity::id($contact['identity_id'])->update(['contact_id' => $id]);
        }
    }

}
