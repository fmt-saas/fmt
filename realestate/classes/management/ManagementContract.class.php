<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace realestate\management;

class ManagementContract extends \equal\orm\Model {

    public static function getName() {
        return 'Managing Agent Contract';
    }


    public static function getDescription() {
        return 'A managing agent is contractually in charge of the administration of one or more condominiums.';
    }

    public static function getColumns() {
        return [
            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the property lot belongs to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'required'          => true
            ],

            'indexation_rate' => [
                'type'              => 'float',
                'description'       => "Rate for yearly indexation of the condominium's fees.",
                'default'           => 0.0
            ],

            'date_from' => [
                'type'              => 'date',
                'description'       => "Start of validity period.",
                'required'          => true
            ],

            'date_renewal' => [
                'type'              => 'date',
                'description'       => "End of validity period.",
                'help'              => "This is a theoretical date: the contract can be revoked by a general assembly.",
                'required'          => true
            ],

            'max_duration' => [
                'type'              => 'integer',
                'description'       => "In case it applies, this is the maximum duration of the contract, in years.",

            ],

            'is_active' => [
                'type'              => 'boolean',
                'description'       => "Current state of the contract.",
                'default'           => true
            ],

        ];
    }

}

