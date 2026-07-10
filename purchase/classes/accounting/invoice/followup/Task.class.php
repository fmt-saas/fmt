<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace purchase\accounting\invoice\followup;

class Task extends \fmt\core\followup\Task {

    public static function getColumns(): array {
        return [

            'entity' => [
                'type'              => 'string',
                'description'       => 'Namespace of the concerned entity.',
                'default'           => 'purchase\accounting\invoice\PurchaseInvoice'
            ]

        ];
    }
}
