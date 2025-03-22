<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace realestate\finance\accounting;

use equal\orm\Model;
use fmt\setting\Setting;

class AccountingEntry extends \finance\accounting\AccountingEntry {

    public static function getName() {
        return "Journal accounting entry";
    }

    public static function getDescription() {
        return "Accounting entries correspond to invoice lines mapped as records of financial transactions in the accounting books.";
    }

    public static function getColumns() {
        return [
            'invoice_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\accounting\invoice\Invoice',
                'description'       => 'Invoice the line is related to.',
                'ondelete'          => 'null'
            ]
        ];
    }
}