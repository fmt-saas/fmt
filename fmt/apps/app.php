<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

list($params, $providers) = eQual::announce([
    'description'   => 'Redirect to `/app` public folder.',
    'params'        => [],
    'response'      => [
        'location'      => '/app/#/fmt/fmt'
    ]
]);
