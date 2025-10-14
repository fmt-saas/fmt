<?php

use identity\Identity;
use identity\Organisation;

// Main organisation
$identity = Identity::create([
        'id'                => 1,
        'type_id'           => 3,
        'type'              => 'CO',
        'has_parent'        => false,
        'nationality'       => 'BE',
        'lang_id'           => 2,
        'address_country'   => 'BE',
        'has_vat'           => true,
        'is_active'         => true
    ])
    ->first();

Organisation::create([
        "identity_id" => $identity['id']
    ])
    ->first();