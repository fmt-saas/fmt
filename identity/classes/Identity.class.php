<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace identity;

use equal\services\Container;
use equal\orm\Model;
use hr\employee\Employee;
use sale\customer\Customer;
use sale\customer\Contact as CustomerContact;
use purchase\supplier\Supplier;
use realestate\management\ManagingAgent;
use realestate\ownership\Owner;

/**
 * This class is meant to be used as an interface for other entities (organisation and partner).
 */
class Identity extends Model {

    public static function getName() {
        return "Identity";
    }

    public static function getDescription() {
        return "An Identity is either a legal or natural person: organizations are legal persons and users, contacts and employees are natural persons. An identity might have several partners of various kind (contact, employee, provider, customer, ...).";
    }

    public static function getColumns() {
        return [

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'function'          => 'calcName',
                'store'             => true,
                'instant'           => true,
                'dependents'        => [
                    'user_id'             => 'name',
                    'contact_id'          => 'name',
                    'customer_contact_id' => 'name',
                    'employee_id'         => 'name',
                    'customer_id'         => 'name',
                    'supplier_id'         => 'name'
                ],
                'description'       => 'The display name of the identity.',
                'help'              => "The display name is a computed field that returns a concatenated string containing either the firstname+lastname, or the legal name of the Identity, based on the kind of Identity.\n
                    For instance, 'name', for a company with \"My Company\" as legal_name will return \"My Company\". \n
                    Whereas, for an individual having \"John\" as firstname and \"Smith\" as lastname, it will return \"John Smith\"."
            ],

            'identity_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Identity',
                'description'       => 'Identity the object relates to.',
                'help'              => 'Meant for entities that inherit from `identity\Identity` and must be synced with parent Identity. Classes that inherit from Identity must implement `onupdateIdentityId()` method.',
                'onupdate'          => 'onupdateIdentityId'
            ],

            'owner_identity_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Identity',
                'description'       => 'Hierarchical Identity the identity depends on.',
                'help'              => 'An object linked to an identity can have a logical link to a parent (for instance the subsidiary of an Organisation or the contact of a Customer).'
            ],

            'type_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\IdentityType',
                'onupdate'          => 'onupdateTypeId',
                // default is 'IN' individual
                'default'           => 1,
                'dependents  '      => ['type', 'name'],
                'description'       => 'Type of identity.'
            ],

            'type' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'store'             => true,
                'instant'           => true,
                'readonly'          => true,
                'description'       => 'Code of the type of identity.',
                'relation'          => ['type_id' => 'code']
            ],

            'description' => [
                'type'              => 'string',
                'usage'             => 'text/plain',
                'description'       => 'A short reminder to help user identify the targeted person and its specifics.'
            ],

            'bank_account_iban' => [
                'type'              => 'string',
                'usage'             => 'uri/urn:iban',
                'description'       => "Number of the bank account of the Identity, if any.",
                'visible'           => [ ['has_parent', '=', false] ],
                'onupdate'          => 'onupdateBankAccountIban'
            ],

            'bank_account_bic' => [
                'type'              => 'string',
                'description'       => "Identifier of the Bank related to the Identity's bank account, when set.",
                'visible'           => [ ['has_parent', '=', false] ],
                'onupdate'          => 'onupdateBankAccountBic'
            ],

            'signature' => [
                'type'              => 'string',
                'usage'             => 'text/html',
                'description'       => 'Identity signature to append to communications.',
                'multilang'         => true
            ],

            /*
                Fields specific to organizations
            */
            'legal_name' => [
                'type'              => 'string',
                'description'       => 'Full name of the Identity.',
                'visible'           => [ ['type', '<>', 'IN'] ],
                'dependents'        => ['name'],
                'onupdate'          => 'onupdateLegalName'
            ],

            'short_name' => [
                'type'              => 'string',
                'description'       => 'Usual name to be used as a memo for identifying the organization (acronym or short name).',
                'visible'           => [ ['type', '<>', 'IN'] ],
                'dependents'        => ['name'],
                'onupdate'          => 'onupdateShortName'
            ],

            'has_vat' => [
                'type'              => 'boolean',
                'description'       => 'Does the organization have a VAT number?',
                'visible'           => [ ['type', '<>', 'IN'], ['has_parent', '=', false] ],
                'default'           => false,
                'onupdate'          => 'onupdateHasVat'
            ],

            'vat_number' => [
                'type'              => 'string',
                'description'       => 'Value Added Tax identification number, if any.',
                'visible'           => [ ['has_vat', '=', true], ['type', '<>', 'IN'], ['has_parent', '=', false] ],
                'onupdate'          => 'onupdateVatNumber'
            ],

            'registration_number' => [
                'type'              => 'string',
                'description'       => 'Organization registration number (company number).',
                'visible'           => [ ['type', '<>', 'IN'] ],
                'onupdate'          => 'onupdateRegistrationNumber'
            ],

            /*
                Fields specific to citizen: children organizations and parent company, if any
            */
            'citizen_identification' => [
                'type'              => 'string',
                'description'       => 'Citizen registration number, if any.',
                'visible'           => [ ['type', '=', 'IN'] ],
                'onupdate'          => 'onupdateCitizenIdentification'
            ],

            'nationality' => [
                'type'              => 'string',
                'usage'             => 'country/iso-3166:2',
                'description'       => 'The country the person is citizen of.',
                'default'           => 'BE',
                'onupdate'          => 'onupdateNationality'
            ],

            /*
                Relational fields specific to organizations: children organizations and parent company, if any
            */
            'children_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'identity\Identity',
                'foreign_field'     => 'parent_id',
                'domain'            => [ ['id', '<>', 'object.id'], ['type', '<>', 'IN'] ],
                'description'       => 'Children departments of the organization, if any.',
                'visible'           => [ ['type', '<>', 'IN'] ]
            ],

            'has_parent' => [
                'type'              => 'boolean',
                'description'       => 'Does the identity have a parent organization?',
                'visible'           => [ ['type', '<>', 'IN'] ],
                'default'           => false
            ],

            'parent_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Identity',
                'domain'            => [ ['id', '<>', 'object.id'], ['type', '<>', 'IN'] ],
                'description'       => 'Parent company of which the organization is a branch (department), if any.',
                'visible'           => [ ['has_parent', '=', true] ]
            ],

            'contacts_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'identity\Contact',
                'foreign_field'     => 'owner_identity_id',
                'domain'            => ['identity_id', '<>', 'object.id'],
                'description'       => 'List of contacts related to the organization, if any.',
                'help'              => 'A contact is an arbitrary relation between two identities. Any Identity can have several contacts.'
            ],

            'users_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'identity\User',
                'foreign_field'     => 'owner_identity_id',
                'description'       => 'List of users of the identity, if any.' ,
                'visible'           => [ ['type', '<>', 'IN'] ]
            ],

            'employees_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'hr\employee\Employee',
                'foreign_field'     => 'owner_identity_id',
                'description'       => 'List of employees of the organization, if any.' ,
                'visible'           => [ ['type', '<>', 'IN'] ]
            ],

            'customers_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\customer\Customer',
                'foreign_field'     => 'owner_identity_id',
                'domain'            => ['relationship', '=', 'customer'],
                'description'       => 'List of customers of the organization, if any.',
                'visible'           => [ ['type', '<>', 'IN'] ]
            ],

            'suppliers_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'purchase\supplier\Supplier',
                'foreign_field'     => 'owner_identity_id',
                'description'       => 'List of suppliers of the organization, if any.',
                'visible'           => [ ['type', '<>', 'IN'] ]
            ],


            /*
                Contact details.
                For individuals, these are the contact details of the person herself.
            */
            'firstname' => [
                'type'              => 'string',
                'description'       => "Full name of the contact (must be a person, not a role).",
                'visible'           => ['type', '=', 'IN'],
                'dependents'        => ['name'],
                'onupdate'          => 'onupdateFirstname'
            ],

            'lastname' => [
                'type'              => 'string',
                'description'       => 'Reference contact surname.',
                'visible'           => ['type', '=', 'IN'],
                'dependents'        => ['name'],
                'onupdate'          => 'onupdateLastname'
            ],

            'gender' => [
                'type'              => 'string',
                'selection'         => ['M' => 'Male', 'F' => 'Female', 'X' => 'Non-binary'],
                'description'       => 'Reference contact gender.',
                'visible'           => ['type', '=', 'IN'],
                'onupdate'          => 'onupdateGender'
            ],

            'title' => [
                'type'              => 'string',
                'selection'         => ['Dr' => 'Doctor', 'Ms' => 'Miss', 'Mrs' => 'Misses', 'Mr' => 'Mister', 'Pr' => 'Professor'],
                'description'       => 'Reference contact title.',
                'visible'           => ['type', '=', 'IN'],
                'onupdate'          => 'onupdateTitle'
            ],

            'date_of_birth' => [
                'type'              => 'date',
                'description'       => 'Date of birth.',
                'visible'           => ['type', '=', 'IN'],
                'onupdate'          => 'onupdateDateOfBirth'
            ],

            'lang_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'core\Lang',
                'description'       => "Preferred language of the identity.",
                'default'           => 1,
                'onupdate'          => 'onupdateLangId'
            ],

            /*
                Description of the Identity address.
                For organizations this is the official (legal) address (typically headquarters, but not necessarily)
            */
            'address_street' => [
                'type'              => 'string',
                'description'       => 'Street and number.',
                'onupdate'          => 'onupdateAddressStreet'
            ],

            'address_dispatch' => [
                'type'              => 'string',
                'description'       => 'Optional info for mail dispatch (apartment, box, floor, ...).',
                'onupdate'          => 'onupdateAddressDispatch'
            ],

            'address_city' => [
                'type'              => 'string',
                'description'       => 'City.',
                'onupdate'          => 'onupdateAddressCity'
            ],

            'address_zip' => [
                'type'              => 'string',
                'description'       => 'Postal code.',
                'onupdate'          => 'onupdateAddressZip'
            ],

            'address_state' => [
                'type'              => 'string',
                'description'       => 'State or region.',
                'onupdate'          => 'onupdateAddressState',
                'visible'           => ['country', '<>', 'BE']
            ],

            'address_country' => [
                'type'              => 'string',
                'usage'             => 'country/iso-3166:2',
                'description'       => 'Country.',
                'default'           => 'BE',
                'onupdate'          => 'onupdateAddressCountry'
            ],

            'address' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'function'          => 'calcAddress',
                'description'       => 'Main address from related Identity.'
            ],

            /*
                Additional official contact details.
                For individuals these are personal contact details, whereas for companies these are official (registered) details.
            */
            'email' => [
                'type'              => 'string',
                'usage'             => 'email',
                'onupdate'          => 'onupdateEmail',
                'description'       => "Identity main email address."
            ],

            'email_alt' => [
                'type'              => 'string',
                'usage'             => 'email',
                'description'       => "Identity secondary email address.",
                'onupdate'          => 'onupdateEmailAlt',
            ],

            'phone' => [
                'type'              => 'string',
                'usage'             => 'phone',
                'onupdate'          => 'onupdatePhone',
                'description'       => "Identity secondary phone number (mobile or landline)."
            ],

            'phone_alt' => [
                'type'              => 'string',
                'usage'             => 'phone',
                'onupdate'          => 'onupdatePhoneAlt',
                'description'       => "Identity main phone number (mobile or landline)."
            ],

            'mobile' => [
                'type'              => 'string',
                'usage'             => 'phone',
                'onupdate'          => 'onupdateMobile',
                'description'       => "Identity mobile phone number."
            ],

            // Companies can also have an official website.
            'website' => [
                'type'              => 'string',
                'usage'             => 'uri/url',
                'description'       => 'Organization main official website URL, if any.',
                'visible'           => ['type', '<>', 'IN'],
                'onupdate'          => 'onupdateWebsite'
            ],

            'image_document_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'documents\Document',
                'description'       => 'Logo or picture of the identity.',
                'help'              => 'Company logo for organizations or profile image for natural person.',
                'onupdate'          => 'onupdateImageDocumentId'
            ],

            'is_active' => [
                'type'              => 'boolean',
                'description'       => "Is the identity active?",
                'help'              => "When an identity is not marked as active, it is no longer displayed amongst the selection choices. However, it is still visible in the list of identities, and its related informations and documents remain available.",
                'default'           => true
            ],

            // an identity can have additional addresses
            'addresses_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'identity\Address',
                'foreign_field'     => 'identity_id',
                'description'       => 'List of addresses related to the identity.',
            ],

            /*
                For organizations, there might be a reference person: a person who is entitled to legally represent the organization (typically the director, the manager, the CEO, ...).
                These contact details are commonly requested by service providers for validating the identity of an organization.
            */
            'reference_identity_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Identity',
                'description'       => 'Contact (natural person) that can legally represent the identity.',
                'help'              => 'This field can be the symmetrical value of owner_identity_id.',
                'visible'           => [ ['type', '<>', 'IN'], ['type', '<>', 'SE'] ]
            ],

            'user_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\User',
                'description'       => 'User associated to this identity, if any.',
                'visible'           => [['type', '=', 'IN']],
                'onupdate'          => 'onupdateUserId'
            ],

            'customer_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\customer\Customer',
                'foreign_field'     => 'identity_id',
                'description'       => 'Customer associated to this identity, if any.',
                'onupdate'          => 'onupdateCustomerId'
            ],

            'supplier_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'purchase\supplier\Supplier',
                'foreign_field'     => 'identity_id',
                'description'       => 'Supplier associated to this identity, if any.',
                'onupdate'          => 'onupdateSupplierId'
            ],

            'contact_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Contact',
                'foreign_field'     => 'identity_id',
                'description'       => 'Contact associated to this identity, if any.',
                'onupdate'          => 'onupdateContactId'
            ],

            'customer_contact_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\customer\Contact',
                'foreign_field'     => 'identity_id',
                'description'       => 'Customer contact associated to this identity, if any.',
                'onupdate'          => 'onupdateCustomerContactId'
            ],

            'employee_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'hr\employee\Employee',
                'foreign_field'     => 'identity_id',
                'description'       => 'Employee associated to this identity, if any.',
                'onupdate'          => 'onupdateEmployeeId'
            ],

            'organisation_id' => [
                'type'           => 'many2one',
                'foreign_object' => 'identity\Organisation',
                'description'    => 'The organisation the identity refers to.',
                'onupdate'       => 'onupdateOrganisationId'
            ],

            'managing_agent_id' => [
                'type'           => 'many2one',
                'foreign_object' => 'realestate\management\ManagingAgent',
                'description'    => 'The managing agent the identity refers to.',
                'onupdate'       => 'onupdateManagingAgentId'
            ],

            'owner_id' => [
                'type'           => 'many2one',
                'foreign_object' => 'realestate\ownership\Owner',
                'description'    => 'The managing agent the identity refers to.',
                'onupdate'       => 'onupdateOwnerId'
            ]

        ];
    }

    /**
     * #memo - classes inheriting from Identity must implement this method
     */
    public static function onupdateIdentityId($self) {
    }

    /**
     * For organizations the name is the legal name.
     * For individuals, the name is the concatenation of first and last names.
     */
    public static function calcName($self) {
        $result = [];
        $self->read(['state', 'type', 'firstname', 'lastname', 'legal_name', 'short_name']);
        foreach($self as $id => $identity) {
            if($identity['state'] == 'draft') {
                continue;
            }
            $parts = [];
            if($identity['type'] == 'IN') {
                if(isset($identity['firstname']) && strlen($identity['firstname'])) {
                    $parts[] = ucfirst($identity['firstname']);
                }
                if(isset($identity['lastname']) && strlen($identity['lastname']) ) {
                    $parts[] = mb_strtoupper($identity['lastname']);
                }
            }
            if(empty($parts) ) {
                if(isset($identity['legal_name']) && strlen($identity['legal_name'])) {
                    $parts[] = $identity['legal_name'];
                }
                elseif(isset($identity['short_name']) && strlen($identity['short_name'])) {
                    $parts[] = $identity['short_name'];
                }
            }
            if(count($parts)) {
                $result[$id] = implode(' ', $parts);
            }
        }
        return $result;
    }

    public static function calcAddress($self) {
        $result = [];
        $self->read(['address_street', 'address_city']);
        foreach($self as $id => $identity) {
            $result[$id] = "{$identity['address_street']} {$identity['address_city']}";
        }
        return $result;
    }

    protected static function updateField($self, $field) {
        $self->read(['identity_id', 'user_id', 'contact_id', 'customer_contact_id', 'employee_id', 'customer_id', 'supplier_id', 'organisation_id', 'managing_agent_id', 'owner_id', $field]);
        foreach($self as $id => $identity) {
            /* update from derived class to Identity */
            if($identity['identity_id']) {
                $orm = Container::getInstance()->get(['orm']);
                $orm->update(Identity::getType(), $identity['identity_id'], [$field => $identity[$field]]);
                continue;
            }
            /* update from Identity to derived class */
            if($identity['user_id']) {
                User::id($identity['user_id'])->update([$field => $identity[$field]]);
            }
            elseif($identity['contact_id']) {
                Contact::id($identity['contact_id'])->update([$field => $identity[$field]]);
            }
            elseif($identity['customer_contact_id']) {
                CustomerContact::id($identity['customer_contact_id'])->update([$field => $identity[$field]]);
            }
            elseif($identity['employee_id']) {
                Employee::id($identity['employee_id'])->update([$field => $identity[$field]]);
            }
            elseif($identity['customer_id']) {
                Customer::id($identity['customer_id'])->update([$field => $identity[$field]]);
            }
            elseif($identity['supplier_id']) {
                Supplier::id($identity['supplier_id'])->update([$field => $identity[$field]]);
            }
            elseif($identity['organisation_id']) {
                Organisation::id($identity['organisation_id'])->update([$field => $identity[$field]]);
            }
            elseif($identity['managing_agent_id']) {
                ManagingAgent::id($identity['managing_agent_id'])->update([$field => $identity[$field]]);
            }
            elseif($identity['owner_id']) {
                Owner::id($identity['owner_id'])->update([$field => $identity[$field]]);
            }
        }
    }

    public static function onupdateTypeId($self) {
        self::updateField($self, 'type_id');
    }

    public static function onupdateLegalName($self) {
        self::updateField($self, 'legal_name');
    }

    public static function onupdateFirstname($self) {
        self::updateField($self, 'firstname');
    }

    public static function onupdateLastname($self) {
        self::updateField($self, 'lastname');
    }

    public static function onupdateHasVat($self) {
        self::updateField($self, 'has_vat');
    }

    public static function onupdateVatNumber($self) {
        self::updateField($self, 'vat_number');
    }

    public static function onupdateEmail($self) {
        self::updateField($self, 'email');
    }

    public static function onupdatePhone($self) {
        self::updateField($self, 'phone');
    }

    public static function onupdateMobile($self) {
        self::updateField($self, 'mobile');
    }

    public static function onupdateLangId($self) {
        self::updateField($self, 'lang_id');
    }

    public static function onupdateAddressStreet($self) {
        self::updateField($self, 'address_street');
    }

    public static function onupdateAddressDispatch($self) {
        self::updateField($self, 'address_dispatch');
    }

    public static function onupdateAddressCity($self) {
        self::updateField($self, 'address_city');
    }

    public static function onupdateAddressZip($self) {
        self::updateField($self, 'address_zip');
    }

    public static function onupdateAddressState($self) {
        self::updateField($self, 'address_state');
    }

    public static function onupdateAddressCountry($self) {
        self::updateField($self, 'address_country');
    }

    public static function onupdateNationality($self) {
        self::updateField($self, 'nationality');
    }

    public static function onupdateRegistrationNumber($self) {
        self::updateField($self, 'registration_number');
    }

    public static function onupdateShortName($self) {
        self::updateField($self, 'short_name');
    }

    public static function onupdateBankAccountIban($self) {
        self::updateField($self, 'bank_account_iban');
    }

    public static function onupdateBankAccountBic($self) {
        self::updateField($self, 'bank_account_bic');
    }

    public static function onupdateCitizenIdentification($self) {
        self::updateField($self, 'citizen_identification');
    }

    public static function onupdateGender($self) {
        self::updateField($self, 'gender');
    }

    public static function onupdateTitle($self) {
        self::updateField($self, 'title');
    }

    public static function onupdateDateOfBirth($self) {
        self::updateField($self, 'date_of_birth');
    }

    public static function onupdateEmailAlt($self) {
        self::updateField($self, 'email_alt');
    }

    public static function onupdatePhoneAlt($self) {
        self::updateField($self, 'phone_alt');
    }

    public static function onupdateWebsite($self) {
        self::updateField($self, 'website');
    }

    public static function onupdateImageDocumentId($self) {
        self::updateField($self, 'image_document_id');
    }

    public static function onupdateUserId($self) {
        $self->read(['user_id']);
        foreach($self as $id => $identity) {
            User::id($identity['user_id'])->update(['identity_id' => $id]);
        }
    }

    public static function onupdateContactId($self) {
        $self->read(['contact_id']);
        foreach($self as $id => $identity) {
            Contact::id($identity['contact_id'])->update(['identity_id' => $id]);
        }
    }

    public static function onupdateCustomerContactId($self) {
        $self->read(['customer_contact_id']);
        foreach($self as $id => $identity) {
            CustomerContact::id($identity['customer_contact_id'])->update(['identity_id' => $id]);
        }
    }

    public static function onupdateEmployeeId($self) {
        $self->read(['employee_id']);
        foreach($self as $id => $identity) {
            Employee::id($identity['employee_id'])->update(['identity_id' => $id]);
        }
    }

    public static function onupdateSupplierId($self) {
        $self->read(['supplier_id']);
        foreach($self as $id => $identity) {
            Supplier::id($identity['supplier_id'])->update(['identity_id' => $id]);
        }
    }

    public static function onupdateCustomerId($self) {
        $self->read(['customer_id']);
        foreach($self as $id => $identity) {
            Customer::id($identity['customer_id'])->update(['identity_id' => $id]);
        }
    }

    public static function onupdateOrganisationId($self) {
        $self->read(['organisation_id']);
        foreach($self as $id => $identity) {
            Organisation::id($identity['organisation_id'])->update(['identity_id' => $id]);
        }
    }

    public static function onupdateManagingAgentId($self) {
        $self->read(['managing_agent_id']);
        foreach($self as $id => $identity) {
            ManagingAgent::id($identity['managing_agent_id'])->update(['identity_id' => $id]);
        }
    }

    public static function onupdateOwnerId($self) {
        $self->read(['owner_id']);
        foreach($self as $id => $identity) {
            ManagingAgent::id($identity['owner_id'])->update(['identity_id' => $id]);
        }
    }

    /**
     * Signature for single object change from views.
     *
     * @param  Array    $event     Associative array holding changed fields as keys, and their related new values.
     * @param  Array    $values    Copy of the current (partial) state of the object (fields depend on the view).
     * @return Array    Associative array mapping fields with their resulting values.
     */
    public static function onchange($self, $event, $values, $lang) {
        $result = [];
        if(isset($event['type_id'])) {
            $type = IdentityType::id($event['type_id'])->read(['code'])->first();
            if($type) {
                $result['type'] = $type['code'];
            }
            if($event['type_id'] > 1) {
                $result['firstname'] = '';
                $result['lastname'] = '';
            }
        }

        if(isset($event['address_zip']) && isset($values['address_country'])) {
            $list = self::computeCitiesByZip($event['address_zip'], $values['address_country'], $lang);
            if($list) {
                $result['address_city'] = [
                    'value' => '',
                    'selection' => $list
                ];
            }
        }

        if(isset($event['bank_account_iban'])) {
            $bic = self::computeBicFromIban($event['bank_account_iban'], $lang);
            $result['bank_account_bic'] = $bic;
        }

        return $result;
    }

    private static function computeBicFromIban($iban, $lang) {
        $result = null;

        $normalized_iban = str_replace(' ', '', trim($iban));

        if(preg_match('/^[A-Z]{2}\d{2}\d{12}$/', $normalized_iban)) {
            $country = substr($normalized_iban, 0, 2);
            $bank_code = substr($normalized_iban, 2, 3);

            $file = EQ_BASEDIR."/packages/identity/i18n/{$lang}/bic/{$country}.json";
            if(file_exists($file)) {
                $data = file_get_contents($file);
                $map_bic = json_decode($data, true);
                $result = $map_bic[$bank_code]['bic'] ?? '';
            }
        }

        return $result;
    }

    /**
     * Returns cities' names based on a zip code and a country.
     */
    private static function computeCitiesByZip($zip, $country, $lang) {
        $result = null;

        $file = EQ_BASEDIR."/packages/identity/i18n/{$lang}/zipcodes/{$country}.json";
        if(file_exists($file)) {
            $data = file_get_contents($file);
            $map_zip = json_decode($data, true);
            $result = $map_zip[$zip] ?? null;
        }
        // fallback to english value, if defined
        if(!$result) {
            $file = EQ_BASEDIR."/packages/identity/i18n/en/zipcodes/{$country}.json";
            if(file_exists($file)) {
                $data = file_get_contents($file);
                $map_zip = json_decode($data, true);
                if(isset($map_zip[$zip])) {
                    $result = $map_zip[$zip];
                }
            }
        }

        return $result;
    }

    public static function onafterupdate($self, $values, $orm) {

        /**
         * Handle creation of related identity for objects that inherit from Identity
         */
        if(substr(self::getType(), strrpos(self::getType(), '\\') + 1) !== 'Identity') {
            $common_fields = [
                    'type_id','has_vat','vat_number','legal_name','firstname','lastname','lang_id',
                    'email','phone','mobile',
                    'address_street','address_dispatch','address_zip',
                    'address_city','address_state','address_country'
                ];

            $self->read(array_merge($common_fields, ['identity_id', 'state']));

            foreach($self as $id => $identity) {

                if($identity['state'] == 'draft') {
                    continue;
                }
                // create a new Identity for objects that are meant to relate to an identity but are not linked yet
                if(is_null($identity['identity_id'])) {
                    $values = [];
                    foreach($common_fields as $field) {
                        $values[$field] = $identity[$field];
                    }

                    $identity_id = $orm->create(Identity::getType(), $values);

                    // #memo - classes that inherit from Identity should have a callback onupdateIdentityId (in order to assign back the right field: 'user_id', 'customer_id', 'supplier_id', 'employee_id', ...)
                    $orm->update(self::getType(), $id, ['identity_id' => $identity_id]);
                }
            }
        }
    }

    /**
     * Check wether an object can be updated, and perform some additional operations if necessary.
     * This method can be overridden to define a more precise set of tests.
     *
     * @param  object   $om         ObjectManager instance.
     * @param  array    $ids       List of objects identifiers.
     * @param  array    $values     Associative array holding the new values to be assigned.
     * @param  string   $lang       Language in which multilang fields are being updated.
     * @return array    Returns an associative array mapping fields with their error messages. En empty array means that object has been successfully processed and can be updated.
     */
    public static function canupdate($om, $ids, $values, $lang='en') {
        if(isset($values['type_id'])) {
            $identities = $om->read(get_called_class(), $ids, [ 'firstname', 'lastname', 'legal_name' ], $lang);
            foreach($identities as $id => $identity) {
                if($values['type_id'] == 1) {
                    $firstname = '';
                    $lastname = '';
                    if(isset($values['firstname'])) {
                        $firstname = $values['firstname'];
                    }
                    else {
                        $firstname = $identity['firstname'];
                    }
                    if(isset($values['lastname'])) {
                        $lastname = $values['lastname'];
                    }
                    else {
                        $lastname = $identity['lastname'];
                    }

                    if(!strlen($firstname) ) {
                        return ['firstname' => ['missing' => "Firstname cannot be empty for natural person (identity $id)."]];
                    }
                    if(!strlen($lastname) ) {
                        return ['lastname' => ['missing' => "Lastname cannot be empty for natural person (identity $id)."]];
                    }
                }
                else {
                    $legal_name = '';
                    if(isset($values['legal_name'])) {
                        $legal_name = $values['legal_name'];
                    }
                    else {
                        $legal_name = $identity['legal_name'];
                    }
                    if(!strlen($legal_name)) {
                        return ['legal_name' => ['missing' => 'Legal name cannot be empty for legal person.']];
                    }
                }
            }
        }
        return parent::canupdate($om, $ids, $values, $lang);
    }

    public static function getConstraints() {
        return [
            'legal_name' =>  [
                'too_short' => [
                    'message'       => 'Legal name must be minimum 2 chars long.',
                    'function'      => function ($legal_name, $values) {
                        return !( strlen($legal_name) < 2 && isset($values['type_id']) && $values['type_id'] != 1 );
                    }
                ],
                'too_long' => [
                    'message'       => 'Legal name must be maximum 70 chars long.',
                    'function'      => function ($legal_name, $values) {
                        return !( strlen($legal_name) > 70 && isset($values['type_id']) && $values['type_id'] != 1 );
                    }
                ],
                'invalid_chars' => [
                    'message'       => 'Legal name must contain only naming glyphs.',
                    'function'      => function ($legal_name, $values) {
                        if( isset($values['type_id']) && $values['type_id'] == 1 ) {
                            return true;
                        }
                        // authorized : a-z, 0-9, '/', '-', ',', '.', ''', '&'
                        return (bool) (preg_match('/^[\w\'\-,.&][^_!¡?÷?¿\\+=@#$%ˆ*{}|~<>;:[\]]{1,}$/u', $legal_name));
                    }
                ]
            ],
            'firstname' =>  [
                'too_short' => [
                    'message'       => 'Firstname must be 2 chars long at minimum.',
                    'function'      => function ($firstname, $values) {
                        return !( strlen($firstname) < 2 && isset($values['type_id']) && $values['type_id'] == 1 );
                    }
                ],
                'invalid_chars' => [
                    'message'       => 'Firstname must contain only naming glyphs.',
                    'function'      => function ($firstname, $values) {
                        if( isset($values['type_id']) && $values['type_id'] != 1 ) {
                            return true;
                        }
                        return (bool) (preg_match('/^[\w\'\-,.][^0-9_!¡?÷?¿\/\\+=@#$%ˆ&*(){}|~<>;:[\]]{1,}$/u', $firstname));
                    }
                ]
            ],
            'lastname' =>  [
                'too_short' => [
                    'message'       => 'Lastname must be 2 chars long at minimum.',
                    'function'      => function ($lastname, $values) {
                        return !( strlen($lastname) < 2 && isset($values['type_id']) && $values['type_id'] == 1 );
                    }
                ],
                'invalid_chars' => [
                    'message'       => 'Lastname must contain only naming glyphs.',
                    'function'      => function ($lastname, $values) {
                        if( isset($values['type_id']) && $values['type_id'] != 1 ) {
                            return true;
                        }
                        return (bool) (preg_match('/^[\w\'\-,.][^0-9_!¡?÷?¿\/\\+=@#$%ˆ&*(){}|~<>;:[\]]{1,}$/u', $lastname));
                    }
                ]
            ]
        ];
    }

}
