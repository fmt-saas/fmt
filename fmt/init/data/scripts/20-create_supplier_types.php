<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
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