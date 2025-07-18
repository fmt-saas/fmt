<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace realestate\governance;

use realestate\property\Apportionment;
use realestate\property\PropertyLotApportionmentShare;

class AssemblyProxy extends \equal\orm\Model {

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
                'description'       => "The assembly the proxy refers to.",
                'foreign_object'    => 'realestate\governance\Assembly',
                'required'          => true
            ],

            'attendee_id' => [
                'type'              => 'many2one',
                'description'       => "Attendee holder of the proxy.",
                'foreign_object'    => 'realestate\governance\AssemblyAttendee',
                'required'          => true,
                'dependents'        => ['identity_id']
            ],

            'identity_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'relation'          => ['attendee_id' => 'identity_id'],
                'description'       => "Person (natural or legal) receiving the proxy.",
                'foreign_object'    => 'identity\Identity',
                'store'             => true
            ],

            'ownership_id' => [
                'type'              => 'many2one',
                'description'       => "The ownership that is represented by the proxy.",
                'foreign_object'    => 'realestate\ownership\Ownership',
                'required'          => true
            ],

            'proxy_date' => [
                'type'              => 'date',
                'description'       => "Date at which the proxy was granted (as stated on document).",
                'required'          => true
            ],

            'proxy_type' => [
                'type'              => 'string',
                'selection'         => [
                    'written',
                    'email',
                    'mandate',
                    'notarial'
                ],
                'description'       => "Type of proxy.",
                'default'           => 'written'
            ],

            'proxy_document_id' => [
                'type'              => 'many2one',
                'description'       => "PDF scan or eID/itsme file.",
                'foreign_object'    => 'documents\Document',
                'required'          => false
            ],

            'is_valid' => [
                'type'              => 'boolean',
                'description'       => "Can be invalidated after verification.",
                'default'           => true
            ],

            'invalidity_reason' => [
                'type'        => 'string',
                'selection'   => [
                    'no_signature',          // Missing signature
                    'missing_or_wrong_date', // Missing or incorrect date
                    'invalid_mandatory',     // Proxy holder not designated or not authorized
                    'invalid_document',      // Incomplete or incorrect form
                    'expired_or_mismatch',   // Proxy expired or for another assembly
                    'too_many_proxies',      // Too many proxies per proxy holder
                    'not_owner'              // Grantor not legitimate (not owner)
                ],
                'description' => "Reason for invalidity of the proxy (e.g. no signature, expired, too many mandates, etc.)",
                'visible'     => ['is_valid', '=', false]
            ],
        ];
    }

    /*
    au moment ou on valide la procuration, on vérifie les autres procurations déjà existantes pour cette assemblée, pour le porteur de procuration (identity_id)
        $self->read(['has_mandate', 'assembly_proxies_ids' => ['@sort' => ['proxy_date' => 'asc'], 'is_valid', 'shares']]);

**Règles métier**

- Un `Ownership` **ne peut donner procuration** qu’une seule fois par AG
- Une personne physique peut recevoir **plusieurs procurations**
- Possibilité de limiter à **3 procurations max par personne** ou, dans le cas contraire, à plafonner à un max de 10% des quotités
- Vérification automatique du poids total (pas plus de 50 % du quorum représenté par procurations)



    */

}
