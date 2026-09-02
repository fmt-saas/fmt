<?php

use finance\accounting\FiscalYear;

FiscalYear::search([
        ['status', 'in', ['preopen', 'open', 'closed']]
    ])
    ->do('generate_sequences');
