<?php

$result = [
    'en' => [],
    'fr' => []
];

$data = file_get_contents('packages/fmt/pcmn_1000m.csv');

$lines = explode("\n", $data);

// remove first line
array_shift($lines);
// remove last line
array_pop($lines);

$id = 1;

foreach($lines as $line) {
    $parts = explode(';', $line);
    $values = array_combine(['code','is_collector','fr','nl','en','code_assignation','assignation','perc_owner','perc_tenant'], $parts);

    $item = [
        'id'                    => $id,
        'code'                  => $values['code'],
        'is_control_account'    => (bool) intval($values['is_collector']),
        'description'           => trim($values['en']),
        'account_chart_id'      => 1,
        'operation_assignment'  => $values['code_assignation'],
        'tenant_share'          => intval(trim($values['perc_tenant'])),
        'owner_share'           => intval(trim($values['perc_owner'])),
    ];

    $result['en'][] = $item;

    $item = [
        'id' => $id,
        'description' => trim($values['fr'])
    ];

    $result['fr'][] = $item;

    ++$id;
}

echo '[
    {
        "name": "finance\\\\accounting\\\\Account",
        "lang": "en",
        "data": ';

echo json_encode($result['en'], JSON_PRETTY_PRINT);
echo '    },
    {
        "name": "finance\\\\accounting\\\\Account",
        "lang": "fr",
        "data": ';

echo json_encode($result['fr'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
echo "    }\n
]";

