<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace identity;

use equal\services\Container;
use equal\orm\Model;
use equal\text\TextTransformer;
use finance\bank\BankAccount;
use fmt\setting\Setting;
use hr\employee\Employee;
use sale\customer\Customer;
use purchase\supplier\Supplier;
use realestate\management\ManagingAgent;
use realestate\ownership\Owner;
use realestate\property\Condominium;
use realestate\property\Tenant;

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

    public static function constants() {
        return ['AUTH_SECRET_KEY'];
    }

    public static function getColumns() {
        return [

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'function'          => 'calcName',
                'store'             => true,
                'instant'           => true,
                'description'       => 'The display name of the identity.',
                'help'              => "The display name is a computed field that returns a concatenated string containing either the firstname+lastname, or the legal name of the Identity, based on the kind of Identity.\n
                    For instance, 'name', for a company with \"My Company\" as legal_name will return \"My Company\". \n
                    Whereas, for an individual having \"John\" as firstname and \"Smith\" as lastname, it will return \"John Smith\"."
            ],

            'uuid' => [
                'type'              => 'string',
                'usage'             => 'text/plain:36',
                // #memo - commented for testing because items are on the same instance
                // #todo - uncomment for PROD
                // 'unique'            => true,
                'description'       => 'Unique identifier from the Master instance.'
            ],

            'object_class' => [
                'type'              => 'string',
                'description'       => 'Class of the current entity.',
                'help'              => 'This is required in order to display the relational fields accordingly.',
                'default'           => 'identity\Identity'
            ],

            'hash_sha256' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'usage'             => 'text/plain:64',
                'function'          => 'calcHashSha256',
                'description'       => 'SHA256 hash of the identity.',
                'help'              => 'This hash helps to identify and prevent duplicate identities within the current instance. It is based on registration_number and/or citizen_identification.',
                'store'             => true,
                'instant'           => true,
                'readonly'          => true
            ],

            'identity_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Identity',
                'description'       => 'Identity the object relates to.',
                'help'              => 'Meant for entities that inherit from `identity\Identity` and must be synced with parent Identity. Classes that inherit from Identity must implement `onupdateIdentityId()` method.',
                'onupdate'          => 'onupdateIdentityId',
                'visible'           => ['object_class', '<>', 'identity\Identity']
            ],

            'owner_identity_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Identity',
                'description'       => 'Hierarchical Identity the identity depends on.',
                'help'              => 'An object linked to an identity can have a logical link to a parent (for instance the subsidiary of an Organisation or the contact of a Customer).',
                'visible'           => ['object_class', '<>', 'identity\Identity']
            ],

            /*
                Following `source_*` fields apply to imported protected entities.
            */

            'source' => [
                'type'              => 'string',
                'description'       => 'The source the Identity originated from.',
                'selection'         => [
                    'manual',           // manual creation
                    'internal',         // identity generated by the software (imported, sync, ...)
                    'external'          // identity retrieved from an external source (API, ...)
                ],
                'default'           => 'manual'
            ],

            'source_type' => [
                'type'              => 'string',
                'selection'         => [
                    'eid',
                    'iam_auth',
                    'registry',
                    'third_party',
                    'manual'
                ],
                'default'           => 'manual',
                'description'       => 'Type of source (eid, registry, manual, etc.)',
                'help'              => 'Indicates how the identity data was obtained. In case of manual encoding, there is no proof for that value.',
            ],

            'source_origin' => [
                'type'              => 'string',
                'description'       => 'Detailed origin of the source (e.g. BCE, itsme, employer).',
                'help'              => 'Specifies the originating system, registry or third party.',
            ],

            'source_date' => [
                'type'              => 'datetime',
                'description'       => 'Date when the identity information was collected or encoded.',
                'help'              => 'Used to assess the freshness of the information.',
            ],

            'source_verification_status' => [
                'type'              => 'string',
                'selection'         => [
                    'pending',
                    'rejected',
                    'conflict',
                    'verified',
                ],
                'default'           => 'pending',
                'description'       => 'Status of the verification of the source (validity of the data).',
            ],

            'type_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\IdentityType',
                'onupdate'          => 'onupdateTypeId',
                'default'           => Setting::get_value('identity', 'organization', 'identity_type_default', 1),
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
                'usage'             => 'text/html',
                'description'       => 'A short text/reminder to help user identify the targeted person and its specifics.'
            ],

            'bank_account_iban' => [
                'type'              => 'string',
                'usage'             => 'uri/urn.iban',
                'description'       => "Number of the bank account of the Identity, if any.",
                'visible'           => [ ['has_parent', '=', false] ],
                'dependents'        => ['bank_account_bic', 'bank_country', 'bank_name'],
                'onupdate'          => 'onupdateBankAccountIban',
                // for individuals, several persons might share/have a bank account in common
                // 'unique'            => true
            ],

            'bank_account_bic' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => 'The BIC code of the bank related to the organization\'s bank account.',
                'onupdate'          => 'onupdateBankAccountBic',
                'function'          => 'calcBankAccountBic',
                'store'             => true
            ],

            'bank_country' => [
                'type'              => 'computed',
                'function'          => 'calcBankCountry',
                'result_type'       => 'string',
                'usage'             => 'country/iso-3166:2',
                'description'       => 'The country where the organization holds the bank account, specified using the ISO 3166-2 code.',
                'store'             => true,
                'instant'           => true,
                'readonly'          => true
            ],

            'bank_name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => 'The name of the bank where the organization holds its account.',
                'function'          => 'calcBankName',
                'store'             => true
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
                'description'       => 'Usual name to be used for the organization (acronym or brand name).',
                'visible'           => [ ['type', '<>', 'IN'] ],
                'multilang'         => true,
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
                'onupdate'          => 'onupdateVatNumber',
                'unique'            => true
            ],

            'registration_number' => [
                'type'              => 'string',
                'description'       => 'Organization registration number (company number).',
                'visible'           => [ ['type', '<>', 'IN'] ],
                'unique'            => true,
                'dependents'        => ['hash_sha256'],
                'onupdate'          => 'onupdateRegistrationNumber'
            ],

            /*
                Fields specific to citizen: children organizations and parent company, if any
            */
            'citizen_identification' => [
                'type'              => 'string',
                'usage'             => 'text/plain:30',
                'description'       => 'Citizen registration number, if any.',
                'visible'           => [ ['type', '=', 'IN'] ],
                'unique'            => true,
                'dependents'        => ['hash_sha256'],
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
                'visible'           => [ ['type', '<>', 'IN'], ['object_class', '=', 'identity\Identity'] ]
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

            /*
                Fields related to Entities that are linked to Identity by using field `owner_identity_id`
                If used in children classes, these must be re-written in order to use a domain instead of foreign_field (which points to object id instead of object.owner_identity_id).
                    'foreign_field' => 'owner_identity_id'
                should become:
                    'domain' => ['owner_identity_id', '=', 'object.identity_id']

                #memo - since that kind of object might itself inherit from Identity, by convention, we use `owner_identity_id` for the relational field
            */
            'users_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'identity\User',
                'foreign_field'     => 'owner_identity_id',
                'description'       => 'List of users of the identity, if any.' ,
                'visible'           => [ ['type', '<>', 'IN'], ['object_class', '=', 'identity\Identity'] ]
            ],

            'employees_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'hr\employee\Employee',
                'foreign_field'     => 'owner_identity_id',
                'description'       => 'List of employees of the organization, if any.' ,
                'visible'           => [ ['type', '<>', 'IN'], ['object_class', '=', 'identity\Identity'] ]
            ],

            'contacts_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'identity\Contact',
                'foreign_field'     => 'owner_identity_id',
                'description'       => 'List of contacts related to the organization, if any.',
                'help'              => 'A contact is an arbitrary relation between two identities. Any Identity can have several contacts.',
                'visible'           => ['object_class', '=', 'identity\Identity']
            ],

            'bank_accounts_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'finance\bank\BankAccount',
                'foreign_field'     => 'owner_identity_id',
                'description'       => 'List of the bank account of the organisation',
                'visible'           => ['object_class', '=', 'identity\Identity']
            ],

            'addresses_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'identity\Address',
                'foreign_field'     => 'owner_identity_id',
                'description'       => 'List of addresses related to the identity.',
                'visible'           => ['object_class', '=', 'identity\Identity']
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
                'selection'         => ['Ms' => 'Miss', 'Mrs' => 'Misses', 'Mr' => 'Mister', 'Dr' => 'Doctor', 'Pr' => 'Professor'],
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
                'default'           => Setting::get_value('identity', 'organization', 'identity_lang_default', 1),
                'onupdate'          => 'onupdateLangId'
            ],

            /*
                Description of the Identity address.
                For organizations this is the official (legal) address (typically headquarters, but not necessarily)
            */
            'address_street' => [
                'type'              => 'string',
                'description'       => 'Street and number.',
                'onupdate'          => 'onupdateAddressStreet',
                'dependents'        => ['address_hash', 'address']
            ],

            'address_dispatch' => [
                'type'              => 'string',
                'description'       => 'Optional info for mail dispatch (apartment, box, floor, ...).',
                'onupdate'          => 'onupdateAddressDispatch'
            ],

            'address_city' => [
                'type'              => 'string',
                'description'       => 'City.',
                'onupdate'          => 'onupdateAddressCity',
                'dependents'        => ['address_hash', 'address']
            ],

            'address_zip' => [
                'type'              => 'string',
                'description'       => 'Postal code.',
                'onupdate'          => 'onupdateAddressZip',
                'dependents'        => ['address_hash']
            ],

            'address_state' => [
                'type'              => 'string',
                'description'       => 'State or region.',
                'onupdate'          => 'onupdateAddressState',
                'visible'           => ['address_country', '<>', 'BE']
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

            'address_hash' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'usage'             => 'text/plain:32',
                'function'          => 'calcAddressHash',
                'store'             => true,
                'instant'           => true,
                'description'       => 'Hash of the normalized version of the address.',
                'help'              => 'This field is used to attempt retrieving matches for a given address.'
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

            'profile_image_document_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'documents\Document',
                'description'       => 'Logo or picture of the identity.',
                'help'              => 'Company logo for organizations or profile image for natural person.',
                'domain'            => ['extension', 'in', ['avif', 'jpg', 'png', 'svg', 'webp']],
                'onupdate'          => 'onupdateProfileImageDocumentId'
            ],

            'profile_image_avatar' => [
                'type'              => 'binary',
                'usage'             => 'image/jpeg',
                'description'       => 'Avatar image for front-end display.',
                'help'              => 'A small JPEG version of the profile image document, optimized for UI usage.',
            ],

            'profile_image_print' => [
                'type'              => 'binary',
                'usage'             => 'image/jpeg',
                'description'       => 'Profile image to be used for documents generation purposes.',
                'help'              => 'A medium-size JPEG version of the profile image document.',
            ],

            'is_active' => [
                'type'              => 'boolean',
                'description'       => "Is the identity active?",
                'help'              => "When an identity is not marked as active, it is no longer displayed amongst the selection choices. However, it is still visible in the list of identities, and its related informations and documents remain available.",
                'default'           => true
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


            /*
                On Master instance (only), there might be several User accounts linked to a single Identity.
            */
            'users_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'identity\User',
                'foreign_field'     => 'identity_id',
                'description'       => 'List of Users associated to this identity, if any.',
                'visible'           => [['type', '=', 'IN']],
            ],

            'customer_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\customer\Customer',
                'foreign_field'     => 'identity_id',
                'description'       => 'Customer associated to this identity, if any.',
                'onupdate'          => 'onupdateCustomerId'
            ],

            'condominium_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\property\Condominium',
                'foreign_field'     => 'identity_id',
                'description'       => 'Condominium associated to this identity, if any.',
                'onupdate'          => 'onupdateCondominiumId',
                'visible'           => [['type', '<>', 'IN']]
            ],

            'supplier_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'purchase\supplier\Supplier',
                'foreign_field'     => 'identity_id',
                'description'       => 'Supplier associated to this identity, if any.',
                'onupdate'          => 'onupdateSupplierId',
                'visible'           => [['type', '<>', 'IN']]
            ],

            'contact_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Contact',
                'foreign_field'     => 'identity_id',
                'description'       => 'Contact associated to this identity, if any.',
                'onupdate'          => 'onupdateContactId'
            ],

            'employee_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'hr\employee\Employee',
                'foreign_field'     => 'identity_id',
                'description'       => 'Employee associated to this identity, if any.',
                'onupdate'          => 'onupdateEmployeeId'
            ],

            'organisation_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Organisation',
                'description'       => 'The organisation the identity refers to.',
                'onupdate'          => 'onupdateOrganisationId',
                'visible'           => [['type', '<>', 'IN']]
            ],

            'managing_agent_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\management\ManagingAgent',
                'description'       => 'The managing agent the identity refers to.',
                'onupdate'          => 'onupdateManagingAgentId'
            ],

            'owner_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\ownership\Owner',
                'description'       => 'The Owner the identity refers to.',
                'onupdate'          => 'onupdateOwnerId'
            ],

            'tenant_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\ownership\Owner',
                'description'       => 'The Tenant the identity refers to.',
                'onupdate'          => 'onupdateTenantId'
            ]

        ];
    }

    public static function getActions() {
        return [
            'sync_from_identity' => [
                'description'   => 'Force sync values from related identity.',
                'function'      => 'doSyncFromIdentity'
            ],
            'refresh_bank_accounts' => [
                'description'   => 'Force sync between Identity main bank account and additional ones.',
                'function'      => 'doRefreshBankAccounts'
            ],
            'refresh_addresses' => [
                'description'   => 'Force sync between Identity main bank account and additional ones.',
                'function'      => 'doRefreshAddresses'
            ],
            'generate_profile_image' => [
                'description'   => 'Generate resized profile images based on profile image document.',
                'function'      => 'doGenerateProfileImage'
            ]
        ];
    }

    protected static function doGenerateProfileImage($self) {
        $self->read(['profile_image_document_id' => ['content_type', 'data']]);

        foreach($self as $id => $identity) {
            if(!$identity['profile_image_document_id']) {
                continue;
            }

            $src_image = imagecreatefromstring($identity['profile_image_document_id']['data']);

            $src_width = imageSX($src_image);
            $src_height = imageSY($src_image);

            $target_width = 400;
            $target_height = 200;

            $dst_image = imagecreatetruecolor($target_width, $target_height);

            // #memo - discard transparency (not available for JPEG)
            // fill background with white for non-transparent images
            $white = imagecolorallocate($dst_image, 255, 255, 255);
            imagefilledrectangle($dst_image, 0, 0, $target_width, $target_height, $white);

            if( ($src_width / $src_height) < ($target_width / $target_height) ) {
                $new_height = $target_height;
                $new_width  = $src_width * $target_height / $src_height;
            }
            else {
                $new_height = $src_height * $target_width / $src_width;
                $new_width  = $target_width;
            }

            $offset_x  = round( ($target_width - $new_width) / 2 );
            $offset_y  = round( ($target_height - $new_height) / 2 );
            imagecopyresampled($dst_image, $src_image, $offset_x, $offset_y, 0, 0, $new_width, $new_height, $src_width, $src_height);

            // get binary value of generated image
            ob_start();
            imagejpeg($dst_image, null, 80);
            $buffer = ob_get_clean();

            // free mem
            imagedestroy($dst_image);
            imagedestroy($src_image);

            self::id($id)->update(['profile_image_print' => $buffer]);
        }
    }

    protected static function doSyncFromIdentity($self, $orm) {
        static $common_fields = [
                'name',
                'type_id',
                'legal_name',
                'firstname',
                'lastname',
                'has_vat',
                'vat_number',
                'email',
                'phone',
                'mobile',
                'lang_id',
                'address_street',
                'address_dispatch',
                'address_city',
                'address_zip',
                'address_state',
                'address_country',
                'nationality',
                'registration_number',
                'short_name',
                'bank_account_iban',
                'bank_account_bic',
                'citizen_identification',
                'gender',
                'title',
                'date_of_birth',
                'email_alt',
                'phone_alt',
                'website',
                'profile_image_document_id'
            ];

        $self->read(['object_class', 'identity_id']);
        foreach($self as $id => $identity) {
            if(!$identity['identity_id']) {
                continue;
            }
            if(substr($identity['object_class'], strrpos($identity['object_class'], '\\') + 1) !== 'Identity') {
                $orm_events = $orm->disableEvents();
                $parentIdentity = Identity::id($identity['identity_id'])
                    ->read($common_fields)
                    ->first(true);

                if(!$parentIdentity) {
                    continue;
                }

                $values = [];
                foreach($common_fields as $field) {
                    if(array_key_exists($field, $parentIdentity)) {
                        $values[$field] = $parentIdentity[$field];
                    }
                }
                self::id($id)->update($values);
                $orm->enableEvents($orm_events);
            }
            // force sync backlink from target Identity
            self::id($id)->update(['identity_id' => $identity['identity_id']]);
        }

    }

    // #memo - this is also done in onupdate handler
    protected static function doRefreshBankAccounts($self) {
        $self->read(['bank_account_iban', 'bank_account_bic', 'bank_name', 'bank_country']);

        foreach($self as $id => $identity) {
            if(!$identity['bank_account_iban'] || strlen($identity['bank_account_iban']) <= 0) {
                continue;
            }
            $mainBankAccount = BankAccount::search([['owner_identity_id', '=', $id], ['is_primary', '=', true]])->first();
            if(!$mainBankAccount) {
                BankAccount::create([
                    'owner_identity_id' => $id,
                    'is_primary'        => true,
                    'bank_account_iban' => $identity['bank_account_iban'],
                    'bank_account_bic'  => $identity['bank_account_bic'],
                    'bank_name'         => $identity['bank_name'],
                    'bank_country'      => $identity['bank_country']
                ]);
            }
        }
    }

    // #memo - this is also done in onupdate handler
    protected static function doRefreshAddresses($self) {
        // sync primary address
        $self->read(['address_street', 'address_dispatch', 'address_zip', 'address_city', 'address_state', 'address_country']);

        foreach($self as $id => $identity) {
            if(!$identity['address_street'] || strlen($identity['address_street']) <= 0) {
                continue;
            }
            $mainAddress = Address::search([['owner_identity_id', '=', $id], ['is_primary', '=', true]]);
            if(!$mainAddress) {
                $mainAddress = Address::create([
                    'owner_identity_id' => $id,
                    'is_primary'        => true,
                    'address_street'    => $identity['address_street'],
                    'address_dispatch'  => $identity['address_dispatch'],
                    'address_zip'       => $identity['address_zip'],
                    'address_city'      => $identity['address_city'],
                    'address_state'     => $identity['address_state'],
                    'address_country'   => $identity['address_country']
                ]);
            }
        }
    }

    /**
     * #memo - classes inheriting from Identity must implement this method in order to update the corresponding field in parent Identity.
     * Note: resulting callback is then ignored by the ORM.
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
                if(isset($identity['short_name']) && strlen($identity['short_name'])) {
                    $parts[] = $identity['short_name'];
                }
                elseif(isset($identity['legal_name']) && strlen($identity['legal_name'])) {
                    $parts[] = $identity['legal_name'];
                }
            }
            if(count($parts)) {
                $result[$id] = implode(' ', $parts);
            }
        }
        return $result;
    }

// #todo - remove
    protected static function calcHashSha256($self) {
        $result = [];
        $self->read(['registration_number', 'citizen_identification']);
        foreach($self as $id => $identity) {
            $id_number = '';
            if($identity['registration_number'] && strlen($identity['registration_number']) > 0) {
                $id_number = $identity['registration_number'];
            }
            elseif($identity['citizen_identification'] && strlen($identity['citizen_identification']) > 0) {
                $id_number = $identity['citizen_identification'];
            }
            if(!strlen($id_number)) {
                continue;
            }
            $result[$id] = hash('sha256', $id_number . constant('AUTH_SECRET_KEY'));
        }
        return $result;
    }


// il faudrait un format lisible et systématique, en majuscule et sans ponctuation, 
    public static function calcAddress($self) {
        $result = [];
        $self->read(['address_street', 'address_dispatch', 'address_city', 'address_zip', 'address_country']);
        $organization = Organisation::id(1)->read(['address_country'])->first();
        foreach($self as $id => $identity) {
            $address = $identity['address_street'];
            // add dispatch only if present
            if($identity['address_dispatch'] && strlen($organization['address_dispatch']) > 0 ) {
                $address .= ' (' . $identity['address_dispatch'] . ')';
            }
            $address .= " {$identity['address_zip']} {$identity['address_city']}";
            // add country only if different from organization's
            if($identity['address_country'] !== $organization['address_country']) {
                $address .= " {$identity['address_country']}";
            }
            $result[$id] = $address;
        }
        return $result;
    }

    public static function calcBankName($self, $lang) {
        $result = [];
        $self->read(['bank_account_iban']);
        foreach($self as $id => $bankAccount) {
            $bank_info = self::computeBankFromIban($bankAccount['bank_account_iban'], $lang);
            if($bank_info) {
                $result[$id] = $bank_info['name'];
            }
        }
        return $result;
    }

    public static function calcBankCountry($self) {
        $result = [];
        $self->read(['bank_account_iban']);
        foreach($self as $id => $bankAccount) {
            $result[$id]  = self::computeCountryFromIban($bankAccount['bank_account_iban']);
        }
        return $result;
    }

    public static function calcBankAccountBic($self, $lang) {
        $result = [];
        $self->read(['bank_account_iban']);
        foreach($self as $id => $bankAccount) {
            $bank_info = self::computeBankFromIban($bankAccount['bank_account_iban'], $lang);
            if($bank_info) {
                $result[$id] = $bank_info['bic'];
            }
        }
        return $result;
    }

    protected static function updateField($self, $field) {
        $self->read([$field, 'identity_id', 'user_id', 'contact_id', 'employee_id', 'customer_id', 'condominium_id', 'supplier_id', 'organisation_id', 'managing_agent_id', 'owner_id', 'tenant_id', ]);
        $orm = Container::getInstance()->get(['orm']);
        // prevent loop update propagation
        $events = $orm->disableEvents();
        foreach($self as $id => $identity) {

            /* update from derived class to Identity */

            if($identity['identity_id']) {
                $orm->update(Identity::getType(), $identity['identity_id'], [$field => $identity[$field]]);
                continue;
            }

            /* update from Identity to derived classes */

            if($identity['user_id']) {
                User::id($identity['user_id'])->update([$field => $identity[$field]]);
            }
            if($identity['contact_id']) {
                Contact::id($identity['contact_id'])->update([$field => $identity[$field]]);
            }
            if($identity['employee_id']) {
                Employee::id($identity['employee_id'])->update([$field => $identity[$field]]);
            }
            if($identity['customer_id']) {
                Customer::id($identity['customer_id'])->update([$field => $identity[$field]]);
            }
            if($identity['condominium_id']) {
                Condominium::id($identity['condominium_id'])->update([$field => $identity[$field]]);
            }
            if($identity['supplier_id']) {
                Supplier::id($identity['supplier_id'])->update([$field => $identity[$field]]);
            }
            if($identity['organisation_id']) {
                Organisation::id($identity['organisation_id'])->update([$field => $identity[$field]]);
            }
            if($identity['managing_agent_id']) {
                ManagingAgent::id($identity['managing_agent_id'])->update([$field => $identity[$field]]);
            }
            if($identity['owner_id']) {
                Owner::id($identity['owner_id'])->update([$field => $identity[$field]]);
            }
            if($identity['tenant_id']) {
                Tenant::id($identity['tenant_id'])->update([$field => $identity[$field]]);
            }
        }
        $orm->enableEvents($events);
    }

    /*
        Handlers for updates of scalar fields
    */

    protected static function onupdateTypeId($self) {
        self::updateField($self, 'type_id');
    }

    protected static function onupdateLegalName($self) {
        self::updateField($self, 'legal_name');
    }

    protected static function onupdateFirstname($self) {
        $self->read(['firstname', 'lastname', 'type']);
        self::updateField($self, 'firstname');
        // for individuals: sync legal name
        foreach($self as $id => $identity) {
            if($identity['type'] === 'IN') {
                self::id($id)->update(['legal_name' => $identity['firstname'] . ' ' . mb_strtoupper($identity['lastname'])]);
            }
        }
    }

    protected static function onupdateLastname($self) {
        $self->read(['firstname', 'lastname', 'type']);
        self::updateField($self, 'lastname');
        // for individuals: sync legal name
        foreach($self as $id => $identity) {
            if($identity['type'] === 'IN') {
                self::id($id)->update(['legal_name' => $identity['firstname'] . ' ' . mb_strtoupper($identity['lastname'])]);
            }
        }
    }

    protected static function onupdateHasVat($self) {
        self::updateField($self, 'has_vat');
    }

    protected static function onupdateVatNumber($self) {
        self::updateField($self, 'vat_number');
    }

    protected static function onupdateEmail($self) {
        self::updateField($self, 'email');
    }

    protected static function onupdatePhone($self) {
        self::updateField($self, 'phone');
    }

    protected static function onupdateMobile($self) {
        self::updateField($self, 'mobile');
    }

    protected static function onupdateLangId($self) {
        self::updateField($self, 'lang_id');
    }

    protected static function onupdateAddressStreet($self) {
        self::updateField($self, 'address_street');
    }

    protected static function onupdateAddressDispatch($self) {
        self::updateField($self, 'address_dispatch');
    }

    protected static function onupdateAddressCity($self) {
        self::updateField($self, 'address_city');
    }

    protected static function onupdateAddressZip($self) {
        self::updateField($self, 'address_zip');
    }

    protected static function onupdateAddressState($self) {
        self::updateField($self, 'address_state');
    }

    protected static function onupdateAddressCountry($self) {
        self::updateField($self, 'address_country');
    }

    protected static function onupdateNationality($self) {
        self::updateField($self, 'nationality');
    }

    protected static function onupdateRegistrationNumber($self) {
        self::updateField($self, 'registration_number');
    }

    protected static function onupdateShortName($self) {
        self::updateField($self, 'short_name');
    }

    protected static function onupdateBankAccountIban($self) {
        self::updateField($self, 'bank_account_iban');
    }

    protected static function onupdateBankAccountBic($self) {
        self::updateField($self, 'bank_account_bic');
    }

    protected static function onupdateCitizenIdentification($self) {
        self::updateField($self, 'citizen_identification');
        // #memo - for convenience, citizen_identification (individuals only) is copied into registration_number
        $self->read(['citizen_identification']);
        foreach($self as $id => $identity) {
            self::id($id)->update(['registration_number' => $identity['citizen_identification']]);
        }
    }

    protected static function onupdateGender($self) {
        self::updateField($self, 'gender');
    }

    protected static function onupdateTitle($self) {
        self::updateField($self, 'title');
    }

    protected static function onupdateDateOfBirth($self) {
        self::updateField($self, 'date_of_birth');
    }

    protected static function onupdateEmailAlt($self) {
        self::updateField($self, 'email_alt');
    }

    protected static function onupdatePhoneAlt($self) {
        self::updateField($self, 'phone_alt');
    }

    protected static function onupdateWebsite($self) {
        self::updateField($self, 'website');
    }

    protected static function onupdateProfileImageDocumentId($self) {
        self::updateField($self, 'profile_image_document_id');
        $self->do('generate_profile_image');
    }

    /*
        Handlers for updates of relational fields
    */

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

    public static function onupdateEmployeeId($self) {
        $self->read(['employee_id']);
        foreach($self as $id => $identity) {
            Employee::id($identity['employee_id'])->update(['identity_id' => $id]);
        }
    }

    public static function onupdateCondominiumId($self) {
        $self->read(['condominium_id']);
        foreach($self as $id => $identity) {
            Condominium::id($identity['condominium_id'])->update(['identity_id' => $id]);
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
            Owner::id($identity['owner_id'])->update(['identity_id' => $id]);
        }
    }

    public static function onupdateTenantId($self) {
        $self->read(['owner_id']);
        foreach($self as $id => $identity) {
            Tenant::id($identity['tenant_id'])->update(['identity_id' => $id]);
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

        if(isset($event['citizen_identification'])) {
            // remove spacing chars
            $result['citizen_identification'] = preg_replace('/[^0-9]/i', '', $event['citizen_identification']);
        }

        if(isset($event['vat_number'])) {
            // remove spacing chars
            $result['vat_number'] = preg_replace('/[^A-Z0-9]/i', '', $event['vat_number']);
        }

        if(isset($event['registration_number'])) {
            // remove spacing chars
            $result['registration_number'] = preg_replace('/[^0-9]/i', '', $event['registration_number']);
        }

        if(isset($event['bank_account_iban'])) {
            // remove spacing chars
            $result['bank_account_iban'] = preg_replace('/[^A-Z0-9]/i', '', $event['bank_account_iban']);
            $bank_info = self::computeBankFromIban($result['bank_account_iban']);
            if($bank_info) {
                $result['bank_account_bic'] = $bank_info['bic'];
            }
        }

        if(isset($event['phone'])) {
            $result['phone'] = preg_replace('/[^\d+]/', '', $event['phone']);
        }

        if(isset($event['phone_alt'])) {
            $result['phone_alt'] = preg_replace('/[^\d+]/', '', $event['phone_alt']);
        }

        if(isset($event['mobile'])) {
            $result['mobile'] = preg_replace('/[^\d+]/', '', $event['mobile']);
        }

        if(isset($event['email'])) {
            $result['email'] = trim($event['email']);
        }

        return $result;
    }

    private static function computeCountryFromIban($iban) {
        $country = '';
        if($iban && strlen($iban) > 0) {
            $country = substr($iban, 0, 2);
        }
        return $country;
    }

    private static function computeBankFromIban($iban, $lang='en') {
        $result = null;

        $normalized_iban = strtoupper(str_replace(' ', '', trim($iban)));

        if(!preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]+$/', $normalized_iban)) {

            return null;
        }

        $country = substr($normalized_iban, 0, 2);

        $iban_formats = [
                'BE' => ['bank_pos' => 4, 'bank_len' => 3],
                'FR' => ['bank_pos' => 4, 'bank_len' => 5],
                'DE' => ['bank_pos' => 4, 'bank_len' => 8],
                'LU' => ['bank_pos' => 4, 'bank_len' => 3],
                'NL' => ['bank_pos' => 4, 'bank_len' => 4],
                // #todo - to complete
            ];

        if(!isset($iban_formats[$country])) {
            return null;
        }

        ['bank_pos' => $pos, 'bank_len' => $len] = $iban_formats[$country];
        $bank_code = substr($normalized_iban, $pos, $len);

        $file = EQ_BASEDIR . "/packages/identity/i18n/{$lang}/bic/{$country}.json";

        if(!file_exists($file)) {
            $file = EQ_BASEDIR . "/packages/identity/i18n/en/bic/{$country}.json";
        }
        if(!file_exists($file)) {
            return null;
        }

        $data = file_get_contents($file);
        $map_bank = json_decode($data, true);
        $result = $map_bank[$bank_code] ?? null;

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


    protected static function oncreate($self, $orm) {
        $self->read(['object_class', 'type_id', 'citizen_identification']);
        foreach($self as $id => $identity) {
            if($identity['object_class'] !== 'identity\Identity') {
                continue;
            }
            if($identity['type_id'] === 1 && (!$identity['citizen_identification'] || strlen($identity['citizen_identification']) <= 0)) {
                self::id($id)->update(['registration_number' => self::computeVirtualCitizenIdentification()]);
            }
        }
    }

    /**
     * Handle creation of related identity for objects that inherit from Identity
     */
    public static function onafterupdate($self, $values, $orm) {

        $self->read(['identity_id', 'type_id', 'citizen_identification', 'registration_number']);

        // Class inherits from Identity but uses a distinct table: check if a new Identity should be created
        if(substr(self::getType(), strrpos(self::getType(), '\\') + 1) !== 'Identity') {
            $common_fields = [
                    'source',
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

                // do not sync Identities from automated source (internal or external)
                if($identity['source'] != 'manual') {
                    continue;
                }

                // create a new Identity for objects that are meant to relate to an identity but are not linked yet
                if(is_null($identity['identity_id']) && !array_key_exists('identity_id', $values)) {
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

        // sync name, addresses & bank accounts (if required)

        $name_dependencies = ['legal_name', 'short_name', 'firstname', 'lastname', 'type_id'];
        if(array_intersect_key($values, array_flip($name_dependencies))) {
            self::updateField($self, 'name');
        }

        foreach($self as $id => $identity) {

            // make sure a registration is present (if not, create a random fake one)
            if($identity['type_id'] === 1
                && (!$identity['citizen_identification'] || strlen($identity['citizen_identification']) <= 0)
                && (!$identity['registration_number'] || strlen($identity['registration_number']) <= 0)
            ) {
                self::id($id)->update(['registration_number' => self::computeVirtualCitizenIdentification()]);
            }

            // sync primary address
            $address_fields = ['address_street', 'address_dispatch', 'address_zip', 'address_city', 'address_state', 'address_country'];
            $address_updates = [];
            foreach($address_fields as $address_field) {
                if(isset($values[$address_field])) {
                    $address_updates[$address_field] = $values[$address_field];
                }
            }

            $identity_id = $id;
            if(isset($identity['identity_id'])) {
                $identity_id = $identity['identity_id'];
            }

            if(count($address_updates)) {
                $mainAddress = Address::search([['owner_identity_id', '=', $identity_id], ['is_primary', '=', true]]);
                if($mainAddress->count() <= 0) {
                    $identity = self::id($identity_id)->read($address_fields)->first();
                    $mainAddress = Address::create([
                        'owner_identity_id' => $identity_id,
                        'is_primary'        => true,
                        'address_street'    => $identity['address_street'],
                        'address_dispatch'  => $identity['address_dispatch'],
                        'address_zip'       => $identity['address_zip'],
                        'address_city'      => $identity['address_city'],
                        'address_state'     => $identity['address_state'],
                        'address_country'   => $identity['address_country']
                    ]);
                }
                $mainAddress->update($address_updates);
            }

            // sync primary bank account
            $bank_fields = ['bank_account_iban', 'bank_account_bic', 'bank_name', 'bank_country'];
            $bank_updates = [];
            foreach($bank_fields as $bank_field) {
                if(isset($values[$bank_field])) {
                    $bank_updates[$bank_field] = $values[$bank_field];
                }
            }

            if(count($bank_updates)) {
                $mainBankAccount = BankAccount::search([['owner_identity_id', '=', $identity_id], ['is_primary', '=', true]]);
                if($mainBankAccount->count() <= 0 && isset($identity['bank_account_iban'])) {
                    $identity = self::id($identity_id)->read($bank_fields)->first();
                    $mainBankAccount = BankAccount::create([
                        'owner_identity_id' => $identity_id,
                        'is_primary'        => true,
                        'bank_account_iban' => $identity['bank_account_iban'],
                        'bank_account_bic'  => $identity['bank_account_bic'],
                        'bank_name'         => $identity['bank_name'],
                        'bank_country'      => $identity['bank_country']
                    ]);
                }
                $mainBankAccount->update($bank_updates);
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

                /*
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
                */
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
                        $type_id = $values['type_id'] ?? null;
                        // Skip validation for individuals (type_id == 1)
                        return $type_id == 1 || strlen(trim($legal_name)) >= 2;
                    }
                ],
                'too_long' => [
                    'message'       => 'Legal name must be maximum 80 chars long.',
                    'function'      => function ($legal_name, $values) {
                        $type_id = $values['type_id'] ?? null;
                        return $type_id == 1 || strlen($legal_name) <= 80;
                    }
                ],
                'invalid_chars' => [
                    'message'       => 'Legal name must contain only naming glyphs.',
                    'function'      => function ($legal_name, $values) {
                        $type_id = $values['type_id'] ?? null;
                        if ($type_id == 1) {
                            // skip char check for individuals
                            return true;
                        }
                        // allowed: letters (Unicode), digits, space, comma, ', &, /, -, ., +, °
                        return preg_match('/^[\p{L}0-9 \'&\/\-,.+°]+$/u', $legal_name);
                    }
                ]
            ],
            'firstname' =>  [
                'too_short' => [
                    'message'       => 'Firstname must be 2 chars long at minimum.',
                    'function'      => function ($firstname, $values) {
                        $type_id = $values['type_id'] ?? null;
                        return $type_id != 1 || strlen(trim($firstname)) >= 2;
                    }
                ],
                'invalid_chars' => [
                    'message'       => 'Firstname must contain only naming glyphs.',
                    'function'      => function ($firstname, $values) {
                        $type_id = $values['type_id'] ?? null;
                        if ($type_id != 1) {
                            // skip char check for non-individuals
                            return true;
                        }
                        // allow letters (Unicode), space, apostrophe, hyphen
                        return preg_match('/^[\p{L}\'\- ]+$/u', $firstname);
                    }
                ]
            ],
            'lastname' =>  [
                'too_short' => [
                    'message'       => 'Lastname must be 2 chars long at minimum.',
                    'function'      => function ($lastname, $values) {
                        $type_id = $values['type_id'] ?? null;
                        return $type_id != 1 || strlen(trim($lastname)) >= 2;
                    }
                ],
                'invalid_chars' => [
                    'message'       => 'Lastname must contain only naming glyphs.',
                    'function'      => function ($lastname, $values) {
                        $type_id = $values['type_id'] ?? null;
                        if ($type_id != 1) {
                            // skip check if not individual
                            return true;
                        }
                        // allow Unicode letters, spaces, apostrophes, hyphens
                        return preg_match('/^[\p{L}\'\- ]+$/u', $lastname);
                    }
                ]
            ]
        ];
    }

    protected static function calcAddressHash($self) {
        $result = [];

        $self->read(['address_street', 'address_zip']);

        foreach($self as $id => $identity) {
            $address = $identity['address_street'];

            $address = strtolower(TextTransformer::toAscii($address));
            $zip = $identity['address_zip'];

            // remove non-alphanum chars (keep dash & space)
            $address = preg_replace('/[^a-z0-9\-\s]/', '', $address);
            $zip = preg_replace('/[^a-z0-9]/', '', $zip);

            // remove redundant spaces
            $address = preg_replace('/\s+/', ' ', trim($address));

            // split street and number
            /*
                matches
                    17-19 rue de l'Église
                    23 Avenue Léopold 2
            */
            if(preg_match('/^(\d+[a-z\-0-9]*)\s+(.*)$/i', $address, $matches)) {
                $number = $matches[1];
                $street = $matches[2];
            }
            /*
                matches
                    Avenue Archibald 12
                    Rue du champ, 22-24
            */
            elseif(preg_match('/^(.*)\s+(\d+[a-z\-0-9]*)$/i', $address, $matches)) {
                $street = $matches[1];
                $number = $matches[2];
            }
            else {
                $street = $address;
                $number = '';
            }

            // normalize address
            $street = str_replace(' ', '_', trim($street));
            $number = str_replace(' ', '', trim($number));

            $digest = "{$street}::{$number}::{$zip}";
            // ignore digests with non-significant clues
            if(strlen($digest) <= 15) {
                continue;
            }
            $result[$id] = md5($digest);
        }
        return $result;
    }

    /**
     * Génère un "numéro de registre national belge" virtuel (11 chiffres)
     * - Les 6 premiers chiffres ne représentent pas une date réelle par défaut (mm=13, dd=90..99)
     * - Les 3 chiffres de séquence sont pris dans une plage réservée (par défaut 900..998)
     * - Les 2 derniers chiffres sont le checksum calculé par la règle mod-97.
     *
     * Retour : chaîne de 11 caractères (ex: "99139090523")
     *
     * Options possibles (passer un tableau associatif) :
     *  - 'yy'       : int|null  => année courte 00..99 (si null: aléatoire)
     *  - 'mm'       : int       => mois (default 13)
     *  - 'dd_min'   : int       => borne min jour (default 90)
     *  - 'dd_max'   : int       => borne max jour (default 99)
     *  - 'seq_min'  : int       => séquence min (default 900)
     *  - 'seq_max'  : int       => séquence max (default 998)
     *  - 'century'  : '1900'|'2000' => règle du checksum (default '1900')
     *
     * Usage exemple:
     *   echo generateVirtualBelgianNN(['yy'=>99]); // ex: 99139901234
     */
    private static function computeVirtualCitizenIdentification(): string {
        $yy = mt_rand(0, 99);
        $mm = mt_rand(13, 99);
        $dd = mt_rand(39, 99);
        $seq = mt_rand(0, 999);

        $first9 = sprintf("%02d%02d%02d%03d", $yy, $mm, $dd, $seq);

        $numStr =  $first9;

        $remainder = self::computeMod97($numStr);
        $cc = 97 - $remainder;

        $ccStr = str_pad((string)$cc, 2, "0", STR_PAD_LEFT);

        return $first9 . $ccStr;
    }

    /**
     * Retourne (int) (number % 97) où number est une chaîne décimale arbitrairement longue.
     * Méthode itérative par bloc (safe sur 32/64-bit, pas d'extension nécessaire).
     */
    private static function computeMod97(string $numStr): int {
        $remainder = 0;
        // On lit par blocs pour éviter de construire des entiers trop grands.
        // Taille de bloc choisie : 7 chiffres (10^7 < PHP_INT_MAX sur 32/64), mais méthode marche avec n'importe quelle taille.
        $len = strlen($numStr);
        $pos = 0;
        while ($pos < $len) {
            $take = min(7, $len - $pos);
            $chunk = substr($numStr, $pos, $take);
            // concaténation du reste actuel et du chunk, calcul du modulo 97
            $combined = (string)$remainder . $chunk;
            // comme $combined peut être grand, on prend modulo en utilisant int sur la chaîne :
            // PHP peut convertir en int tant que la valeur tient ; pour être sûr, on réduit progressivement :
            $remainder = intval($combined) % 97;
            $pos += $take;
        }
        return $remainder;
    }

}
