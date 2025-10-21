<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use purchase\supplier\SupplierType;

SupplierType::create([
    'id'        => 1,
    'name'      => 'Managing Agents',
    'code'      => 'managing_agent',
    'category'  => 'services'
]);

SupplierType::create([
    'id'        => 2,
    'name'      => 'Banks',
    'code'      => 'bank',
    'category'  => 'finance'
]);

SupplierType::create([
    'id'        => 3,
    'name'      => 'Water Provider',
    'code'      => 'water',
    'category'  => 'services'
]);

SupplierType::create([
    'id'        => 4,
    'name'      => 'Gas Providers',
    'code'      => 'gas',
    'category'  => 'services'
]);

SupplierType::create([
    'id'        => 5,
    'name'      => 'Electricity Providers',
    'code'      => 'electricity',
    'category'  => 'services'
]);

SupplierType::create([
    'id'        => 6,
    'name'      => 'Notary Offices',
    'code'      => 'notary_office',
    'category'  => 'services'
]);