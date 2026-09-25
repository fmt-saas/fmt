<?php

use realestate\sale\pay\FundingAllocation;

FundingAllocation::search([
        [
            'origin_object_class',
            'in',
            [
                'realestate\funding\FundRequestExecution',
                'realestate\purchase\accounting\invoice\PurchaseInvoice',
                'finance\accounting\MiscOperation'
            ]
        ]
    ])
    ->update(['payment_origin' => 'funding_allocation']);
