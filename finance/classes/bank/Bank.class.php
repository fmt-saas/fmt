<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace finance\bank;



class Bank extends \purchase\supplier\Supplier {

    public static function getName() {
        return 'Bank';
    }

    public static function getDescription() {
        return "A Bank is handled as a supplier with specific additional info.";
    }

    public static function getColumns() {

        return [


        ];
    }

    public static function onrevertName($self) {
        $self->read(['supplierships_ids']);
        foreach($self as $id => $supplier) {
            Suppliership::ids($supplier['supplierships_ids'])->update(['name' => null]);
        }
    }

    public static function onupdateIdentityId($self) {
        $self->read(['identity_id']);
        foreach($self as $id => $supplier) {
            if($supplier['identity_id']) {
                Identity::id($supplier['identity_id'])->update(['supplier_id' => $id]);
            }
        }
    }

}
