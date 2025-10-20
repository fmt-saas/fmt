<?php

use identity\Identity;
use realestate\property\NotaryOffice;

['orm' => $orm] = eQual::inject(['orm']);

$events = $orm->disableEvents();


  Identity::create([
    "id" => 200,
    "legal_name" => "Étude Benoît HEYMANS, Notaire",
    "address_street" => "Avenue de l'Échevinage 1A",
    "address_zip" => "1180",
    "address_city" => "Uccle",
    "email" => "benoit.heymans@notaire.be",
    "phone" => "023745970",
    "website" => "https://notheymans.be",
    "nationality" => "BE",
    "lang_id" => 2,
    "is_active" => true
  ]);

NotaryOffice::create([
    "id" => 200,
    "identity_id" => 200,
    "supplier_type_id" => 6,
    "is_active" => true,
    "registry_ref" => "fednot:benoit-heymans-notaire"
]);

Identity::create([
    "id" => 201,
    "legal_name" => "Étude Bernard DEWITTE et Astrid COMIJN, Notaires associés",
    "address_street" => "Avenue Franklin Roosevelt 208",
    "address_zip" => "1020",
    "address_city" => "Bruxelles",
    "email" => "bernard.dewitte@belnot.be",
    "phone" => "026638020",
    "website" => "https://abcdnotaires.be",
    "nationality" => "BE",
    "lang_id" => 2,
    "is_active" => true
  ]);

NotaryOffice::create([
    "id" => 201,
    "identity_id" => 201,
    "supplier_type_id" => 6,
    "is_active" => true,
    "registry_ref" => "fednot:bernard-dewitte-et-astrid-comijn-notaires-associes"
]);

Identity::create([
    "id" => 202,
    "legal_name" => "Étude DEWEERDT & RUELLE, Notaires associés",
    "address_street" => "Avenue Louise 213/11",
    "address_zip" => "1020",
    "address_city" => "Bruxelles",
    "phone" => "026486197",
    "website" => "https://notaire.be/Etude/deweerdt-ruelle-notaires-associes",
    "nationality" => "BE",
    "lang_id" => 2,
    "is_active" => true
  ]);

NotaryOffice::create([
    "id" => 202,
    "identity_id" => 202,
    "supplier_type_id" => 6,
    "is_active" => true,
    "registry_ref" => "fednot:deweerdt-ruelle-notaires-associes"
]);

Identity::create([
    "id" => 203,
    "legal_name" => "Étude Véronique FASOL, Notaire",
    "address_street" => "Avenue A.J. Slegers 2/5",
    "address_zip" => "1200",
    "address_city" => "Woluwe-Saint-Lambert",
    "email" => "veronique.fasol@belnot.be",
    "phone" => "027330781",
    "website" => "https://etudefasol.be",
    "nationality" => "BE",
    "lang_id" => 2,
    "is_active" => true
  ]);
NotaryOffice::create([
    "id" => 203,
    "identity_id" => 203,
    "supplier_type_id" => 6,
    "is_active" => true,
    "registry_ref" => "fednot:fasol-veronique"
]);

Identity::create([
    "id" => 204,
    "legal_name" => "Étude INDEKEU - de CRAYENCOUR",
    "address_street" => "Avenue Louise 126",
    "address_zip" => "1020",
    "address_city" => "Bruxelles",
    "email" => "gerard.indekeu@belnot.be",
    "phone" => "026473280",
    "website" => "https://indekeu-cleenewerckdecrayencour.be",
    "nationality" => "BE",
    "lang_id" => 2,
    "is_active" => true
  ]);
NotaryOffice::create([
    "id" => 204,
    "identity_id" => 204,
    "supplier_type_id" => 6,
    "is_active" => true,
    "registry_ref" => "fednot:indekeu-de-crayencour"
]);

Identity::create([
    "id" => 205,
    "legal_name" => "Étude Jean Didier GYSELINCK, Notaire",
    "address_street" => "Avenue Louise 422",
    "address_zip" => "1020",
    "address_city" => "Bruxelles",
    "email" => "jeandidier.gyselinck@notaire.be",
    "phone" => "026496105",
    "website" => "https://notairegyselinck.be",
    "nationality" => "BE",
    "lang_id" => 2,
    "is_active" => true
  ]);
NotaryOffice::create([
    "id" => 205,
    "identity_id" => 205,
    "supplier_type_id" => 6,
    "is_active" => true,
    "registry_ref" => "fednot:jean-didier-gyselinck-notaire"
]);

Identity::create([
    "id" => 206,
    "legal_name" => "Étude Jean-Louis Van Boxstael, Société notariale",
    "address_street" => "Avenue Louise 480",
    "address_zip" => "1020",
    "address_city" => "Bruxelles",
    "phone" => "028953019",
    "website" => "https://notaire.be/Etude/jean-louis-van-boxstael-societe-notariale",
    "nationality" => "BE",
    "lang_id" => 2,
    "is_active" => true
  ]);
NotaryOffice::create([
    "id" => 206,
    "identity_id" => 206,
    "supplier_type_id" => 6,
    "is_active" => true,
    "registry_ref" => "fednot:jean-louis-van-boxstael-societe-notariale"
]);

Identity::create([
    "id" => 207,
    "legal_name" => "Étude Jean-Pierre MARCHANT, Notaire",
    "address_street" => "Avenue Brugmann 480",
    "address_zip" => "1180",
    "address_city" => "Uccle",
    "email" => "jpmarchant@marchant.be",
    "phone" => "023743574",
    "website" => "https://marchant.be",
    "nationality" => "BE",
    "lang_id" => 2,
    "is_active" => true
  ]);
NotaryOffice::create([
    "id" => 207,
    "identity_id" => 207,
    "supplier_type_id" => 6,
    "is_active" => true,
    "registry_ref" => "fednot:jean-pierre-marchant-notaire-notaris"
]);

Identity::create([
    "id" => 208,
    "legal_name" => "Étude LICOPPE-CAUCHIE, notaires associés",
    "address_street" => "Avenue des Paradisiers 24",
    "address_zip" => "1160",
    "address_city" => "Auderghem",
    "phone" => "027320574",
    "website" => "https://notaire.be/Etude/licoppe-cauchie-notaires-associes",
    "nationality" => "BE",
    "lang_id" => 2,
    "is_active" => true
  ]);
NotaryOffice::create([
    "id" => 208,
    "identity_id" => 208,
    "supplier_type_id" => 6,
    "is_active" => true,
    "registry_ref" => "fednot:licoppe-cauchie-notaires-associes"
]);

Identity::create([
    "id" => 209,
    "legal_name" => "Étude MARROYEN Luc",
    "address_street" => "Avenue Louise 205",
    "address_zip" => "1020",
    "address_city" => "Bruxelles",
    "email" => "info@marroyen.be",
    "phone" => "023752728",
    "website" => "https://marroyen.be",
    "nationality" => "BE",
    "lang_id" => 2,
    "is_active" => true
  ]);
NotaryOffice::create([
    "id" => 209,
    "identity_id" => 209,
    "supplier_type_id" => 6,
    "is_active" => true,
    "registry_ref" => "fednot:marroyen-luc"
]);

Identity::create([
    "id" => 210,
    "legal_name" => "Étude MSW, société notariale",
    "address_street" => "Avenue Louise 202/15",
    "address_zip" => "1020",
    "address_city" => "Bruxelles",
    "email" => "msw@swnot.be",
    "phone" => "026402956",
    "website" => "https://swnot.be",
    "nationality" => "BE",
    "lang_id" => 2,
    "is_active" => true
  ]);
NotaryOffice::create([
    "id" => 210,
    "identity_id" => 210,
    "supplier_type_id" => 6,
    "is_active" => true,
    "registry_ref" => "fednot:maxime-van-der-straten-waillet-societe-notariale"
]);

Identity::create([
    "id" => 211,
    "legal_name" => "Étude NOTABEL, Notaires Associés",
    "address_street" => "Avenue Louise 65/5",
    "address_zip" => "1020",
    "address_city" => "Bruxelles",
    "email" => "info@notabel.info",
    "phone" => "025386076",
    "website" => "https://notabel.info",
    "nationality" => "BE",
    "lang_id" => 2,
    "is_active" => true
  ]);
NotaryOffice::create([
    "id" => 211,
    "identity_id" => 211,
    "supplier_type_id" => 6,
    "is_active" => true,
    "registry_ref" => "fednot:de-clippele-bruyaux-naets-notaires-associes"
]);

Identity::create([
    "id" => 212,
    "legal_name" => "Étude Notaire Laura Hornung, société notariale",
    "address_street" => "Chaussée de Waterloo 1359K",
    "address_zip" => "1180",
    "address_city" => "Uccle",
    "phone" => "023740384",
    "website" => "https://notaire.be/Etude/notaire-laura-hornung-societe-notariale",
    "nationality" => "BE",
    "lang_id" => 2,
    "is_active" => true
  ]);
NotaryOffice::create([
    "id" => 212,
    "identity_id" => 212,
    "supplier_type_id" => 6,
    "is_active" => true,
    "registry_ref" => "fednot:notaire-laura-hornung-societe-notariale"
]);

Identity::create([
    "id" => 213,
    "legal_name" => "Étude NOTÉRIS, Pierre-Edouard",
    "address_street" => "Avenue Brugmann 587/9",
    "address_zip" => "1180",
    "address_city" => "Uccle",
    "email" => "etude@noteris.be",
    "phone" => "022104260",
    "website" => "https://noteris.be",
    "nationality" => "BE",
    "lang_id" => 2,
    "is_active" => true
  ]);
NotaryOffice::create([
    "id" => 213,
    "identity_id" => 213,
    "supplier_type_id" => 6,
    "is_active" => true,
    "registry_ref" => "fednot:noteris-pierre-edouard"
]);

Identity::create([
    "id" => 214,
    "legal_name" => "Étude Notilius",
    "address_street" => "",
    "address_zip" => "",
    "address_city" => "",
    "email" => "info@notilius.be",
    "phone" => "026722202",
    "website" => "https://notilius.be",
    "nationality" => "BE",
    "lang_id" => 2,
    "is_active" => true
  ]);
NotaryOffice::create([
    "id" => 214,
    "identity_id" => 214,
    "supplier_type_id" => 6,
    "is_active" => true,
    "registry_ref" => "fednot:notilius-notaires-associes"
]);

Identity::create([
    "id" => 215,
    "legal_name" => "Étude Patrick GUSTIN & Gauthier NOBELS, Notaires associés",
    "address_street" => "Avenue Jean Van Horenbeeck 42",
    "address_zip" => "1160",
    "address_city" => "Auderghem",
    "phone" => "026724000",
    "website" => "https://notaire-gustin.be",
    "nationality" => "BE",
    "lang_id" => 2,
    "is_active" => true
  ]);
NotaryOffice::create([
    "id" => 215,
    "identity_id" => 215,
    "supplier_type_id" => 6,
    "is_active" => true,
    "registry_ref" => "fednot:patrick-gustin-gauthier-nobels-notaires-associes"
]);

Identity::create([
    "id" => 216,
    "legal_name" => "Étude Sophie MAQUET, Stijn JOYE & Dominique BERTOUILLE, Notaires associés",
    "address_street" => "Avenue Louise 65",
    "address_zip" => "1020",
    "address_city" => "Bruxelles",
    "nationality" => "BE",
    "lang_id" => 2,
    "is_active" => true
  ]);
NotaryOffice::create([
    "id" => 216,
    "identity_id" => 216,
    "supplier_type_id" => 6,
    "is_active" => true,
    "registry_ref" => "fednot:sophie-maquet-stijn-joye-dominique-bertouille-notaires-associes"
]);

Identity::create([
    "id" => 217,
    "legal_name" => "Étude Valéry COLARD et Vanessa WATERKEYN, notaires associés",
    "address_street" => "Avenue Louise 65",
    "address_zip" => "1020",
    "address_city" => "Bruxelles",
    "nationality" => "BE",
    "lang_id" => 2,
    "is_active" => true
  ]);
NotaryOffice::create([
    "id" => 217,
    "identity_id" => 217,
    "supplier_type_id" => 6,
    "is_active" => true,
    "registry_ref" => "fednot:valery-colard-et-vanessa-waterkeyn-notaires-associes"
]);

Identity::create([
    "id" => 218,
    "legal_name" => "Étude VAN STEENKISTE, société notariale",
    "address_street" => "Avenue A.J. Slegers 2/5",
    "address_zip" => "1200",
    "address_city" => "Woluwe-Saint-Lambert",
    "nationality" => "BE",
    "lang_id" => 2,
    "is_active" => true
  ]);
NotaryOffice::create([
    "id" => 218,
    "identity_id" => 218,
    "supplier_type_id" => 6,
    "is_active" => true,
    "registry_ref" => "fednot:van-steenkiste-societe-notariale-1"
]);

Identity::create([
    "id" => 219,
    "legal_name" => "Étude Véronique BONEHILL et Laurent WETS, Notaires Associés",
    "address_street" => "Avenue Brugmann 587",
    "address_zip" => "1180",
    "address_city" => "Uccle",
    "nationality" => "BE",
    "lang_id" => 2,
    "is_active" => true
  ]);
NotaryOffice::create([
    "id" => 219,
    "identity_id" => 219,
    "supplier_type_id" => 6,
    "is_active" => true,
    "registry_ref" => "fednot:veronique-bonehill-et-laurent-wets-notaires-associes"
]);

Identity::create([
    "id" => 520,
    "legal_name" => "Étude Bruno MICHAUX & Marie THIEBAUT, Notaires associés",
    "address_street" => "Avenue d'Auderghem 328",
    "address_zip" => "1040",
    "address_city" => "Etterbeek",
    "website" => "https://notaire.be/Etude/bruno-michaux-marie-thiebaut-notaires-associes",
    "nationality" => "BE",
    "lang_id" => 2,
    "is_active" => true
  ]);
NotaryOffice::create([
    "id" => 520,
    "identity_id" => 520,
    "supplier_type_id" => 6,
    "is_active" => true,
    "registry_ref" => "fednot:bruno-michaux-marie-thiebaut-notaires-associes"
]);

Identity::create([
    "id" => 521,
    "legal_name" => "Étude DAMIEN COLLON - ANTOINE LOGÉ, geassocieerde notarissen",
    "address_street" => "Avenue d'Auderghem 328",
    "address_zip" => "1040",
    "address_city" => "Etterbeek",
    "website" => "https://notaire.be/Etude/damien-collon-antoine-loge-geassocieerde-notarissen",
    "nationality" => "BE",
    "lang_id" => 2,
    "is_active" => true
  ]);
NotaryOffice::create([
    "id" => 521,
    "identity_id" => 521,
    "supplier_type_id" => 6,
    "is_active" => true,
    "registry_ref" => "fednot:damien-collon-antoine-loge-geassocieerde-notarissen"
]);

Identity::create([
    "id" => 522,
    "legal_name" => "Étude In-Deed notaires",
    "address_street" => "Avenue d'Auderghem 328",
    "address_zip" => "1040",
    "address_city" => "Etterbeek",
    "website" => "https://notaire.be/Etude/in-deed-notaires",
    "nationality" => "BE",
    "lang_id" => 2,
    "is_active" => true
  ]);
/*
#todo - add address
    "address_street" => "Avenue de Tervuren 270",
    "address_zip" => "1120",
    "address_city" => "Woluwe-Saint-Pierre",

*/
NotaryOffice::create([
    "id" => 522,
    "identity_id" => 522,
    "supplier_type_id" => 6,
    "is_active" => true,
    "registry_ref" => "fednot:in-deed-notaires"
]);

Identity::create([
    "id" => 524,
    "legal_name" => "Étude MAITE BOUCLIER société notariale",
    "address_street" => "Avenue d'Auderghem 328",
    "address_zip" => "1040",
    "address_city" => "Etterbeek",
    "website" => "https://notaire.be/Etude/maite-bouclier-societe-notariale",
    "nationality" => "BE",
    "lang_id" => 2,
    "is_active" => true
  ]);
NotaryOffice::create([
    "id" => 524,
    "identity_id" => 524,
    "supplier_type_id" => 6,
    "is_active" => true,
    "registry_ref" => "fednot:maite-bouclier-societe-notariale"
]);

Identity::create([
    "id" => 525,
    "legal_name" => "Étude Marc WILMUS et Ludovic du BUS de WARNAFFE, notaires associés",
    "address_street" => "Avenue d'Auderghem 328",
    "address_zip" => "1040",
    "address_city" => "Etterbeek",
    "website" => "https://notaire.be/Etude/marc-wilmus-ludovic-du-bus-de-warnaffe-notaires-associes",
    "nationality" => "BE",
    "lang_id" => 2,
    "is_active" => true
  ]);
NotaryOffice::create([
    "id" => 525,
    "identity_id" => 525,
    "supplier_type_id" => 6,
    "is_active" => true,
    "registry_ref" => "fednot:marc-wilmus-ludovic-du-bus-de-warnaffe-notaires-associes"
]);

Identity::create([
    "id" => 526,
    "legal_name" => "Étude Nathalie d'Hennezel société notariale",
    "address_street" => "Avenue de la Houlette 42/11",
    "address_zip" => "1170",
    "address_city" => "Watermael-Boitsfort",
    "phone" => "026727770",
    "website" => "https://notaire.be/Etude/nathalie-dhennezel-societe-notariale"
]);

NotaryOffice::create([
    "id" => 526,
    "identity_id" => 526,
    "supplier_type_id" => 6,
    "is_active" => true,
    "registry_ref" => "fednot:nathalie-dhennezel-societe-notariale"
]);



$orm->enableEvents($events);

// sync values from Identities to Suppliers (Banks)
NotaryOffice::search(['object_class', '=', 'realestate\property\NotaryOffice'])->do('sync_from_identity');