<?php

use identity\Identity;
use purchase\supplier\Supplier;

['orm' => $orm] = eQual::inject(['orm']);

$orm->disableEvents();


Identity::create([
        "id" => 1001,
        "supplier_id" => 1001,
        "type_id" => 3,
        "bank_account_iban" => "BE59001835397826",
        "bank_account_bic" => "GEBABEBB",
        "legal_name" => "ABC TECHNICS",
        "short_name" => "ABC",
        "has_vat" => true,
        "vat_number" => "BE0455160721",
        "registration_number" => "0455160721",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Avenue E. Lambrecht, 40",
        "address_city" => "Wemmel",
        "address_zip" => "1780",
        "address_country" => "BE",
        "email" => "info@abctechnics.be",
        "phone" => "+3224655210",
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1002,
        "supplier_id" => 1002,
        "type_id" => 3,
        "bank_account_iban" => "BE32068898295102",
        "bank_account_bic" => "GKCCBEBB",
        "legal_name" => "ACCESS SYSTEMS",
        "short_name" => "ACCESS",
        "has_vat" => true,
        "vat_number" => "BE0543297493",
        "registration_number" => "0543297493",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Rue de l'Entreprise, 15",
        "address_city" => "Villers-le-Bouillet",
        "address_zip" => "4530",
        "address_country" => "BE",
        "email" => "jbl@access-systems.be",
        "phone" => "+3248630500",
        "mobile" => "+32486842330",
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1003,
        "supplier_id" => 1003,
        "type_id" => 3,
        "bank_account_iban" => "BE39734009429419",
        "bank_account_bic" => "KREDBEBB",
        "legal_name" => "ACERTA GUICHET D'ENTREPRISES",
        "short_name" => "ACERTA",
        "has_vat" => true,
        "vat_number" => "BE0480513551",
        "registration_number" => "0480513551",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Esplanade Heysel BP 65",
        "address_city" => "Laeken",
        "address_zip" => "1020",
        "address_country" => "BE",
        "email" => null,
        "phone" => "+3223332724",
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1004,
        "supplier_id" => 1004,
        "type_id" => 3,
        "bank_account_iban" => "BE64734003185952",
        "bank_account_bic" => "KREDBEBB",
        "legal_name" => "ACERTA SECRETARIAT SOCIAL",
        "short_name" => "ACERTA",
        "has_vat" => true,
        "vat_number" => "BE0473329910",
        "registration_number" => "0473329910",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Esplanade Heysel BP 65",
        "address_city" => "Laeken",
        "address_zip" => "1020",
        "address_country" => "BE",
        "email" => null,
        "phone" => "+3223332724",
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1005,
        "supplier_id" => 1005,
        "type_id" => 3,
        "bank_account_iban" => "BE89001795197285",
        "bank_account_bic" => "GEBABEBB",
        "legal_name" => "ACTIAS FACILITIES",
        "short_name" => "ACTIAS",
        "has_vat" => true,
        "vat_number" => "BE0662497033",
        "registration_number" => "0662497033",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Bergensesteenweg, 423 bte 9",
        "address_city" => "Sint-Pieters-Leeuw",
        "address_zip" => "1600",
        "address_country" => "BE",
        "email" => "billing@actiasfacilities.be",
        "phone" => null,
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1006,
        "supplier_id" => 1006,
        "type_id" => 3,
        "bank_account_iban" => "BE06001372785022",
        "bank_account_bic" => "GEBABEBB",
        "legal_name" => "AGRO-JARDINS",
        "short_name" => "AGRO-JARDINS",
        "has_vat" => true,
        "vat_number" => "BE0477295725",
        "registration_number" => "0477295725",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Rue de Moustier, 68",
        "address_city" => "Spy",
        "address_zip" => "5190",
        "address_country" => "BE",
        "email" => "agro.jardins@skynet.be",
        "phone" => "+3271787324",
        "mobile" => "+32478338994",
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1007,
        "supplier_id" => 1007,
        "type_id" => 3,
        "bank_account_iban" => "BE34001662307790",
        "bank_account_bic" => "GEBABEBB",
        "legal_name" => "ALL REFRESH",
        "short_name" => "ALL",
        "has_vat" => true,
        "vat_number" => "BE0808734431",
        "registration_number" => "0808734431",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Square des Cicindèles, 5",
        "address_city" => "Watermael-Boitsfort",
        "address_zip" => "1170",
        "address_country" => "BE",
        "email" => "info.mvb@gmail.com",
        "phone" => null,
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1008,
        "supplier_id" => 1008,
        "type_id" => 3,
        "bank_account_iban" => "BE64310003566252",
        "bank_account_bic" => "BBRUBEBB",
        "legal_name" => "ALLIANZ BENELUX",
        "short_name" => "ALLIANZ",
        "has_vat" => true,
        "vat_number" => "BE0403258197",
        "registration_number" => "0403258197",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Boulevard du Roi Albert II, 32",
        "address_city" => "Bruxelles",
        "address_zip" => "1000",
        "address_country" => "BE",
        "email" => null,
        "phone" => "+3222146111",
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1009,
        "supplier_id" => 1009,
        "type_id" => 3,
        "bank_account_iban" => "BE55248060946544",
        "bank_account_bic" => "GEBABEBB",
        "legal_name" => "ANTICIMEX",
        "short_name" => "ANTICIMEX",
        "has_vat" => true,
        "vat_number" => "BE0402272064",
        "registration_number" => "0402272064",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Avenue des Saisons 100-102 bte 30",
        "address_city" => "Ixelles",
        "address_zip" => "1050",
        "address_country" => "BE",
        "email" => "info@anticimex.be",
        "phone" => "+3251265151",
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1010,
        "supplier_id" => 1010,
        "type_id" => 3,
        "bank_account_iban" => "BE49310144965071",
        "bank_account_bic" => "BBRUBEBB",
        "legal_name" => "AQUANTIS",
        "short_name" => "AQUANTIS",
        "has_vat" => true,
        "vat_number" => "BE0864993738",
        "registration_number" => "0864993738",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Rue paloke, 105",
        "address_city" => "Molenbeek-Saint-Jean",
        "address_zip" => "1080",
        "address_country" => "BE",
        "email" => "info@aquantis.be",
        "phone" => "+3224116765",
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1011,
        "supplier_id" => 1011,
        "type_id" => 3,
        "bank_account_iban" => "BE80197258771277",
        "bank_account_bic" => "CREGBEBB",
        "legal_name" => "AQUATEL",
        "short_name" => "AQUATEL",
        "has_vat" => true,
        "vat_number" => "BE0459851759",
        "registration_number" => "0459851759",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Rue Pont Léopold, 13",
        "address_city" => "Verviers",
        "address_zip" => "4800",
        "address_country" => "BE",
        "email" => "aquatel@aquatel.be",
        "phone" => "+3287340830",
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1012,
        "supplier_id" => 1012,
        "type_id" => 3,
        "bank_account_iban" => "BE86390024395050",
        "bank_account_bic" => "BBRUBEBB",
        "legal_name" => "AR2D2",
        "short_name" => "AR2D2",
        "has_vat" => true,
        "vat_number" => "BE0639956411",
        "registration_number" => "0639956411",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Varenberg, 13a",
        "address_city" => "Vossem",
        "address_zip" => "3080",
        "address_country" => "BE",
        "email" => "alissann@skynet.be",
        "phone" => "+32474288515",
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1013,
        "supplier_id" => 1013,
        "type_id" => 3,
        "bank_account_iban" => "BE40745022357963",
        "bank_account_bic" => "KREDBEBB",
        "legal_name" => "ASCENSEURS A.T.M. LIFTEN",
        "short_name" => "ASCENSEURS",
        "has_vat" => true,
        "vat_number" => "BE0810925740",
        "registration_number" => "0810925740",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Rue Chopin, 23",
        "address_city" => "Anderlecht",
        "address_zip" => "1070",
        "address_country" => "BE",
        "email" => null,
        "phone" => null,
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1014,
        "supplier_id" => 1014,
        "type_id" => 3,
        "bank_account_iban" => "BE47363153661780",
        "bank_account_bic" => "BBRUBEBB",
        "legal_name" => "ASH PLOMBERIE - A&A TRADING",
        "short_name" => "ASH",
        "has_vat" => true,
        "vat_number" => "BE0640868508",
        "registration_number" => "0640868508",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Koningin Astridlaan, 20",
        "address_city" => "Sint-Niklaas",
        "address_zip" => "9100",
        "address_country" => "BE",
        "email" => null,
        "phone" => "+32472820292",
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1015,
        "supplier_id" => 1015,
        "type_id" => 3,
        "bank_account_iban" => "BE37310158579528",
        "bank_account_bic" => "BBRUBEBB",
        "legal_name" => "ASTRA CLEAN",
        "short_name" => "ASTRA",
        "has_vat" => true,
        "vat_number" => "BE0455665121",
        "registration_number" => "0455665121",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Chaussée de Wemmel, 1",
        "address_city" => "Jette",
        "address_zip" => "1090",
        "address_country" => "BE",
        "email" => "astra_clean@hotmail.com",
        "phone" => null,
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1016,
        "supplier_id" => 1016,
        "type_id" => 3,
        "bank_account_iban" => "BE22310146417647",
        "bank_account_bic" => "BBRUBEBB",
        "legal_name" => "ASVEDEC THERMOGRAPHIE",
        "short_name" => "ASVEDEC",
        "has_vat" => true,
        "vat_number" => "BE0418622702",
        "registration_number" => "0418622702",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Avenue Louise, 230/3",
        "address_city" => "Ixelles",
        "address_zip" => "1050",
        "address_country" => "BE",
        "email" => "thermo@asvedec.be",
        "phone" => "+3226491380",
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1017,
        "supplier_id" => 1017,
        "type_id" => 3,
        "bank_account_iban" => "BE13731035944939",
        "bank_account_bic" => "KREDBEBB",
        "legal_name" => "ATK",
        "short_name" => "ATK",
        "has_vat" => true,
        "vat_number" => "BE0406583319",
        "registration_number" => "0406583319",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Mechelsesteenweg, 247",
        "address_city" => "Bonheiden",
        "address_zip" => "2820",
        "address_country" => "BE",
        "email" => null,
        "phone" => "+3215555151",
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1018,
        "supplier_id" => 1018,
        "type_id" => 3,
        "bank_account_iban" => "BE71068891977469",
        "bank_account_bic" => "GKCCBEBB",
        "legal_name" => "AUTEUIL SERVICE",
        "short_name" => "AUTEUIL",
        "has_vat" => true,
        "vat_number" => "BE0833508330",
        "registration_number" => "0833508330",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Rue Royal Sainte-Marie, 77",
        "address_city" => "Schaerbeek",
        "address_zip" => "1030",
        "address_country" => "BE",
        "email" => "auteuilservices@gmail.com",
        "phone" => "+3223080808",
        "mobile" => "+32468228888",
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1019,
        "supplier_id" => 1019,
        "type_id" => 3,
        "bank_account_iban" => "BE48363087453927",
        "bank_account_bic" => "BBRUBEBB",
        "legal_name" => "AV FACADE",
        "short_name" => "AV",
        "has_vat" => true,
        "vat_number" => "BE0471096633",
        "registration_number" => "0471096633",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Rue de la Sucrerie, 12",
        "address_city" => "Juprelle",
        "address_zip" => "4450",
        "address_country" => "BE",
        "email" => "avfacade@hotmail.com",
        "phone" => "+3242890031",
        "mobile" => "+32476662090",
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1020,
        "supplier_id" => 1020,
        "type_id" => 3,
        "bank_account_iban" => "BE09700021777857",
        "bank_account_bic" => "AXABBE22",
        "legal_name" => "AXA BELGIUM",
        "short_name" => "AXA",
        "has_vat" => true,
        "vat_number" => "BE0404483367",
        "registration_number" => "0404483367",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Boulevard du Souverain, 25 ",
        "address_city" => "Watermael-Boitsfort",
        "address_zip" => "1170",
        "address_country" => "BE",
        "email" => null,
        "phone" => "+3226786111",
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1021,
        "supplier_id" => 1021,
        "type_id" => 3,
        "bank_account_iban" => "BE31410000071155",
        "bank_account_bic" => "KREDBEBB",
        "legal_name" => "BALOISE",
        "short_name" => "BALOISE",
        "has_vat" => true,
        "vat_number" => "BE0400048883",
        "registration_number" => "0400048883",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Posthofbrug, 16",
        "address_city" => "Antwerpen",
        "address_zip" => "2600",
        "address_country" => "BE",
        "email" => "info@baloise.be",
        "phone" => "+3232472111",
        "mobile" => "+3227730311",
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1022,
        "supplier_id" => 1022,
        "type_id" => 3,
        "bank_account_iban" => "BE89210051908085",
        "bank_account_bic" => "GEBABEBB",
        "legal_name" => "BARAL CHAUFFAGE MAINTENANCE",
        "short_name" => "BARAL",
        "has_vat" => true,
        "vat_number" => "BE0449483944",
        "registration_number" => "0449483944",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Avenue du Roi Albert, 70",
        "address_city" => "Neder-Over-Heembeek",
        "address_zip" => "1120",
        "address_country" => "BE",
        "email" => "info@baral.be",
        "phone" => "+3222683055",
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1023,
        "supplier_id" => 1023,
        "type_id" => 3,
        "bank_account_iban" => "BE03068910282884",
        "bank_account_bic" => "GKCCBEBB",
        "legal_name" => "BINAME SERRURERIE",
        "short_name" => "BINAME",
        "has_vat" => true,
        "vat_number" => "BE0699512431",
        "registration_number" => "0699512431",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Avenue Adolphe Schattens, 9",
        "address_city" => "Waterloo",
        "address_zip" => "1410",
        "address_country" => "BE",
        "email" => "info@serrureriebiname.be",
        "phone" => "+3223549747",
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1024,
        "supplier_id" => 1024,
        "type_id" => 3,
        "bank_account_iban" => "BE53097231088453",
        "bank_account_bic" => "GKCCBEBB",
        "legal_name" => "BRUXELLES FISCALITE",
        "short_name" => "BRUXELLES",
        "has_vat" => true,
        "vat_number" => "BE0316381039",
        "registration_number" => "0316381039",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Iris Tower - Place Saint-Lazare, 2-4",
        "address_city" => "Saint-Josse-ten-Noode",
        "address_zip" => "1210",
        "address_country" => "BE",
        "email" => "taxprov@fisc.brussels",
        "phone" => "+3222041419",
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1025,
        "supplier_id" => 1025,
        "type_id" => 3,
        "bank_account_iban" => "BE56210038823088",
        "bank_account_bic" => "GEBABEBB",
        "legal_name" => "BSC CLEANING",
        "short_name" => "BSC",
        "has_vat" => true,
        "vat_number" => "BE0454773414",
        "registration_number" => "0454773414",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Venelle Schubert, 1",
        "address_city" => "Ganshoren",
        "address_zip" => "1083",
        "address_country" => "BE",
        "email" => null,
        "phone" => null,
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1026,
        "supplier_id" => 1026,
        "type_id" => 3,
        "bank_account_iban" => "BE15789578218230",
        "bank_account_bic" => "GKCCBEBB",
        "legal_name" => "BTV NORMEC",
        "short_name" => "BTV",
        "has_vat" => true,
        "vat_number" => "BE0406486616",
        "registration_number" => "0406486616",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Ruisbroeksesteenweg, 75",
        "address_city" => "Forest",
        "address_zip" => "1190",
        "address_country" => "BE",
        "email" => "btv.brussel@btvcontrol.be",
        "phone" => "+3222308182",
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1027,
        "supplier_id" => 1027,
        "type_id" => 3,
        "bank_account_iban" => "BE56210044594588",
        "bank_account_bic" => "GEBABEBB",
        "legal_name" => "BWT BELGIUM",
        "short_name" => "BWT",
        "has_vat" => true,
        "vat_number" => "BE0402940374",
        "registration_number" => "0402940374",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Leuvensesteenweg, 633",
        "address_city" => "Zaventem",
        "address_zip" => "1930",
        "address_country" => "BE",
        "email" => "bwt@bwt.be",
        "phone" => "+3227580310",
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1028,
        "supplier_id" => 1028,
        "type_id" => 3,
        "bank_account_iban" => "BE87310095225794",
        "bank_account_bic" => "BBRUBEBB",
        "legal_name" => "CD NET",
        "short_name" => "CD",
        "has_vat" => true,
        "vat_number" => "BE0429429292",
        "registration_number" => "0429429292",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Rue Konkel, 70",
        "address_city" => "Woluwe-Saint-Pierre",
        "address_zip" => "1150",
        "address_country" => "BE",
        "email" => null,
        "phone" => "+3227319544",
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1029,
        "supplier_id" => 1029,
        "type_id" => 3,
        "bank_account_iban" => "BE43360103925301",
        "bank_account_bic" => "BBRUBEBB",
        "legal_name" => "CERTITANK",
        "short_name" => "CERTITANK",
        "has_vat" => true,
        "vat_number" => "BE0470184635",
        "registration_number" => "0470184635",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Rue Beyaert  75",
        "address_city" => "Tournai",
        "address_zip" => "7500",
        "address_country" => "BE",
        "email" => null,
        "phone" => "+3280085800",
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1030,
        "supplier_id" => 1030,
        "type_id" => 3,
        "bank_account_iban" => "BE33434317650146",
        "bank_account_bic" => "KREDBEBB",
        "legal_name" => "CHAUFFAGE BARAL GMT",
        "short_name" => "CHAUFFAGE",
        "has_vat" => true,
        "vat_number" => "BE0440354957",
        "registration_number" => "0440354957",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Avenue du Roi Albert, 70",
        "address_city" => "Neder-Over-Heembeek",
        "address_zip" => "1120",
        "address_country" => "BE",
        "email" => "info@baral.be",
        "phone" => "+3222622923",
        "mobile" => "+3222683055",
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1031,
        "supplier_id" => 1031,
        "type_id" => 3,
        "bank_account_iban" => "BE75068216613151",
        "bank_account_bic" => "GKCCBEBB",
        "legal_name" => "CHAUFFAGE ELAERTS",
        "short_name" => "CHAUFFAGE",
        "has_vat" => true,
        "vat_number" => "BE0875622562",
        "registration_number" => "0875622562",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Rue de Virginal, 27",
        "address_city" => "Ittre",
        "address_zip" => "1460",
        "address_country" => "BE",
        "email" => "info@elaerts.be",
        "phone" => "+3267210782",
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1032,
        "supplier_id" => 1032,
        "type_id" => 3,
        "bank_account_iban" => "BE76001755079095",
        "bank_account_bic" => "GEBABEBB",
        "legal_name" => "CHAUFFAGE LEGRAND - EYLENBOSCH",
        "short_name" => "CHAUFFAGE",
        "has_vat" => true,
        "vat_number" => "BE0415474358",
        "registration_number" => "0415474358",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Rinsdelleplein, 29 ",
        "address_city" => "Etterbeek",
        "address_zip" => "1040",
        "address_country" => "BE",
        "email" => "info@chauffagelegrand.eu",
        "phone" => "+3227365967",
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1033,
        "supplier_id" => 1033,
        "type_id" => 3,
        "bank_account_iban" => "BE95068894071558",
        "bank_account_bic" => "GKCCBEBB",
        "legal_name" => "CHD - COMFORT HOME DUPONT & CO",
        "short_name" => "CHD",
        "has_vat" => true,
        "vat_number" => "BE0841216365",
        "registration_number" => "0841216365",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Terhulpensesteenweg, 382",
        "address_city" => "Overijse",
        "address_zip" => "3090",
        "address_country" => "BE",
        "email" => null,
        "phone" => null,
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1034,
        "supplier_id" => 1034,
        "type_id" => 3,
        "bank_account_iban" => "BE96779594597405",
        "bank_account_bic" => "GKCCBEBB",
        "legal_name" => "CIBOR",
        "short_name" => "CIBOR",
        "has_vat" => true,
        "vat_number" => "BE0451884594",
        "registration_number" => "0451884594",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Ambachtsstraat, 7",
        "address_city" => "Meerhout",
        "address_zip" => "2450",
        "address_country" => "BE",
        "email" => "info@cibor.be",
        "phone" => "+3214592203",
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1035,
        "supplier_id" => 1035,
        "type_id" => 3,
        "bank_account_iban" => "BE68001895606534",
        "bank_account_bic" => "GEBABEBB",
        "legal_name" => "CITY FACADE",
        "short_name" => "CITY",
        "has_vat" => true,
        "vat_number" => "BE0433696205",
        "registration_number" => "0433696205",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Rue des Boers, 101",
        "address_city" => "Etterbeek",
        "address_zip" => "1040",
        "address_country" => "BE",
        "email" => "city@city-facade.be",
        "phone" => "+3227340955",
        "mobile" => "+32475434441",
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1036,
        "supplier_id" => 1036,
        "type_id" => 3,
        "bank_account_iban" => "BE50310150682718",
        "bank_account_bic" => "BBRUBEBB",
        "legal_name" => "CLEAN & CO",
        "short_name" => "CLEAN",
        "has_vat" => true,
        "vat_number" => "BE0440617253",
        "registration_number" => "0440617253",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Avenue Demey, 57",
        "address_city" => "Auderghem",
        "address_zip" => "1160",
        "address_country" => "BE",
        "email" => "info@cleanandco.be",
        "phone" => "+3223316481",
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1037,
        "supplier_id" => 1037,
        "type_id" => 3,
        "bank_account_iban" => "BE69001605396678",
        "bank_account_bic" => "GEBABEBB",
        "legal_name" => "CONCORDIA ASSURANCES",
        "short_name" => "CONCORDIA",
        "has_vat" => true,
        "vat_number" => "BE0427391205",
        "registration_number" => "0427391205",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Chaussée Romaine, 564B",
        "address_city" => "Strombeek-Bever",
        "address_zip" => "1853",
        "address_country" => "BE",
        "email" => null,
        "phone" => "+3224235032",
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1038,
        "supplier_id" => 1038,
        "type_id" => 3,
        "bank_account_iban" => "BE45210078709589",
        "bank_account_bic" => "GEBABEBB",
        "legal_name" => "CPM GARDEN",
        "short_name" => "CPM",
        "has_vat" => true,
        "vat_number" => "BE0415229383",
        "registration_number" => "0415229383",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Chaussée de Mons, 574",
        "address_city" => "Anderlecht",
        "address_zip" => "1070",
        "address_country" => "BE",
        "email" => "paul.loos@skynet.be",
        "phone" => "+3225243697",
        "mobile" => "+32475598131",
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1039,
        "supplier_id" => 1039,
        "type_id" => 3,
        "bank_account_iban" => "BE90096928000132",
        "bank_account_bic" => "GKCCBEBB",
        "legal_name" => "DE WATERGROEP",
        "short_name" => "DE",
        "has_vat" => true,
        "vat_number" => "BE0224771467",
        "registration_number" => "0224771467",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Rue du Progrès, 189",
        "address_city" => "Schaerbeek",
        "address_zip" => "1030",
        "address_country" => "BE",
        "email" => null,
        "phone" => null,
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1040,
        "supplier_id" => 1040,
        "type_id" => 3,
        "bank_account_iban" => "BE73001349246960",
        "bank_account_bic" => "GEBABEBB",
        "legal_name" => "DEBOUCHAGE HENDRICX",
        "short_name" => "DEBOUCHAGE",
        "has_vat" => true,
        "vat_number" => "BE0473595174",
        "registration_number" => "0473595174",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Beemd, 23",
        "address_city" => "Rhode-Saint-Genèse",
        "address_zip" => "1640",
        "address_country" => "BE",
        "email" => null,
        "phone" => "+3223761885",
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1041,
        "supplier_id" => 1041,
        "type_id" => 3,
        "bank_account_iban" => "BE41210087334610",
        "bank_account_bic" => "GEBABEBB",
        "legal_name" => "DEBOUCHAGE MODERNE",
        "short_name" => "DEBOUCHAGE",
        "has_vat" => true,
        "vat_number" => "BE0432418278",
        "registration_number" => "0432418278",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Rue Albert Vanderkindere, 12A",
        "address_city" => "Molenbeek-Saint-Jean",
        "address_zip" => "1080",
        "address_country" => "BE",
        "email" => "dmosprl@skynet.be",
        "phone" => null,
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1042,
        "supplier_id" => 1042,
        "type_id" => 3,
        "bank_account_iban" => "BE10000020884504",
        "bank_account_bic" => "BPOTBEB1",
        "legal_name" => "DEHON & ASSOCIÉS",
        "short_name" => "DEHON",
        "has_vat" => true,
        "vat_number" => "BE0443894170",
        "registration_number" => "0443894170",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Rue Egide Van Ophem, 40B",
        "address_city" => "Uccle",
        "address_zip" => "1180",
        "address_country" => "BE",
        "email" => null,
        "phone" => "+3225241070",
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1043,
        "supplier_id" => 1043,
        "type_id" => 3,
        "bank_account_iban" => "BE57210054541435",
        "bank_account_bic" => "GEBABEBB",
        "legal_name" => "DEMAY VITRERIE",
        "short_name" => "DEMAY",
        "has_vat" => true,
        "vat_number" => "BE0472046639",
        "registration_number" => "0472046639",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Chaussée de Wavre, 1386",
        "address_city" => "Auderghem",
        "address_zip" => "1160",
        "address_country" => "BE",
        "email" => "info@demay.be",
        "phone" => "+3226725820",
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1044,
        "supplier_id" => 1044,
        "type_id" => 3,
        "bank_account_iban" => "BE98645103921993",
        "bank_account_bic" => "JVBABE22",
        "legal_name" => "DICLO LABORATOIRES",
        "short_name" => "DICLO",
        "has_vat" => true,
        "vat_number" => "BE0412019277",
        "registration_number" => "0412019277",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Rue d'Alsace Lorraine, 13",
        "address_city" => "Ixelles",
        "address_zip" => "1050",
        "address_country" => "BE",
        "email" => "diclo@diclolabo.be",
        "phone" => "+3225118629",
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1045,
        "supplier_id" => 1045,
        "type_id" => 3,
        "bank_account_iban" => "BE32363062687302",
        "bank_account_bic" => "BBRUBEBB",
        "legal_name" => "EDAN CLEAN",
        "short_name" => "EDAN",
        "has_vat" => true,
        "vat_number" => "BE0677473932",
        "registration_number" => "0677473932",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Avenue De Fré, 108",
        "address_city" => "Uccle",
        "address_zip" => "1180",
        "address_country" => "BE",
        "email" => "info@edan-clean.be",
        "phone" => "+32486273215",
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1046,
        "supplier_id" => 1046,
        "type_id" => 3,
        "bank_account_iban" => "BE47001657146380",
        "bank_account_bic" => "GEBABEBB",
        "legal_name" => "EDENRED BELGIUM",
        "short_name" => "EDENRED",
        "has_vat" => true,
        "vat_number" => "BE0407034269",
        "registration_number" => "0407034269",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Boulevard du Souverain, 165 bte 9",
        "address_city" => "Auderghem",
        "address_zip" => "1160",
        "address_country" => "BE",
        "email" => "ale-pwa-be@edenred.com",
        "phone" => "+3226782825",
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1047,
        "supplier_id" => 1047,
        "type_id" => 3,
        "bank_account_iban" => "BE02068249019740",
        "bank_account_bic" => "GKCCBEBB",
        "legal_name" => "ELEMEN'TERRE",
        "short_name" => "ELEMEN'TERRE",
        "has_vat" => true,
        "vat_number" => "BE0895256253",
        "registration_number" => "0895256253",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Rue Théophile Vander Elst, 160",
        "address_city" => "Watermael-Boitsfort",
        "address_zip" => "1170",
        "address_country" => "BE",
        "email" => null,
        "phone" => null,
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1048,
        "supplier_id" => 1048,
        "type_id" => 3,
        "bank_account_iban" => "BE85068896074206",
        "bank_account_bic" => "GKCCBEBB",
        "legal_name" => "ELITIS",
        "short_name" => "ELITIS",
        "has_vat" => true,
        "vat_number" => "BE0818415130",
        "registration_number" => "0818415130",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Avenue de Limburg Stirumlaan, 194",
        "address_city" => "Wemmel",
        "address_zip" => "1780",
        "address_country" => "BE",
        "email" => null,
        "phone" => "+3222682180",
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1049,
        "supplier_id" => 1049,
        "type_id" => 3,
        "bank_account_iban" => "BE81000325873924",
        "bank_account_bic" => "GEBABEBB",
        "legal_name" => "ENGIE",
        "short_name" => "ENGIE",
        "has_vat" => true,
        "vat_number" => "BE0403170701",
        "registration_number" => "0403170701",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Boulevard Simon Bolivar, 34",
        "address_city" => "Bruxelles",
        "address_zip" => "1000",
        "address_country" => "BE",
        "email" => "servicessyndic@electrabel.com",
        "phone" => "+3278782020",
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1050,
        "supplier_id" => 1050,
        "type_id" => 3,
        "bank_account_iban" => "BE21409652185103",
        "bank_account_bic" => "KREDBEBB",
        "legal_name" => "EUROMEX",
        "short_name" => "EUROMEX",
        "has_vat" => true,
        "vat_number" => "BE0404493859",
        "registration_number" => "0404493859",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Generaal lemanstraat, 82-92",
        "address_city" => "Berchem",
        "address_zip" => "2600",
        "address_country" => "BE",
        "email" => "polisbeheer@euromex.be",
        "phone" => "+3234514400",
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1051,
        "supplier_id" => 1051,
        "type_id" => 3,
        "bank_account_iban" => "BE11735061368248",
        "bank_account_bic" => "KREDBEBB",
        "legal_name" => "EURONET",
        "short_name" => "EURONET",
        "has_vat" => true,
        "vat_number" => "BE0430083647",
        "registration_number" => "0430083647",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Rue Henri Maubel, 53",
        "address_city" => "Forest",
        "address_zip" => "1190",
        "address_country" => "BE",
        "email" => "euronet@euronet-vanbelle.be",
        "phone" => "+3223472173",
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1052,
        "supplier_id" => 1052,
        "type_id" => 3,
        "bank_account_iban" => "BE03132532902984",
        "bank_account_bic" => "BNAGBEBB",
        "legal_name" => "EXTERMINA",
        "short_name" => "EXTERMINA",
        "has_vat" => true,
        "vat_number" => "BE0829444228",
        "registration_number" => "0829444228",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Rue Jean-Baptiste Desmeth, 32",
        "address_city" => "Evere",
        "address_zip" => "1140",
        "address_country" => "BE",
        "email" => "contact@extermina.be",
        "phone" => "+3227320441",
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1053,
        "supplier_id" => 1053,
        "type_id" => 3,
        "bank_account_iban" => "BE79732019291533",
        "bank_account_bic" => "CREGBEBB",
        "legal_name" => "FAIN BELGIQUE - RENSONNET",
        "short_name" => "FAIN",
        "has_vat" => true,
        "vat_number" => "BE0808702262",
        "registration_number" => "0808702262",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Au Fonds Race, 33",
        "address_city" => "Waremme",
        "address_zip" => "4300",
        "address_country" => "BE",
        "email" => "corentin.devos@fainbelgique.be",
        "phone" => "+3219339043",
        "mobile" => "+32497447821",
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1054,
        "supplier_id" => 1054,
        "type_id" => 3,
        "bank_account_iban" => "BE61091017090217",
        "bank_account_bic" => "GKCCBEBB",
        "legal_name" => "FARYS",
        "short_name" => "FARYS",
        "has_vat" => true,
        "vat_number" => "BE0200068636",
        "registration_number" => "0200068636",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Stropstraat, 1",
        "address_city" => "Gent",
        "address_zip" => "9000",
        "address_country" => "BE",
        "email" => "klantendienst@farys.be",
        "phone" => "+3292400474",
        "mobile" => "+3278353599",
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1055,
        "supplier_id" => 1055,
        "type_id" => 3,
        "bank_account_iban" => "BE82210055592368",
        "bank_account_bic" => "GEBABEBB",
        "legal_name" => "FAV & CO",
        "short_name" => "FAV",
        "has_vat" => true,
        "vat_number" => "BE0463292388",
        "registration_number" => "0463292388",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Romeinsesteenweg, 466",
        "address_city" => "Strombeek-Bever",
        "address_zip" => "1853",
        "address_country" => "BE",
        "email" => "info@favenco.be",
        "phone" => "+3222611212",
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1056,
        "supplier_id" => 1056,
        "type_id" => 3,
        "bank_account_iban" => "BE31310007233155",
        "bank_account_bic" => "BBRUBEBB",
        "legal_name" => "FEDERALE ASSURANCE",
        "short_name" => "FEDERALE",
        "has_vat" => true,
        "vat_number" => "BE0403257506",
        "registration_number" => "0403257506",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Rue de l'Etuve, 12",
        "address_city" => "Bruxelles",
        "address_zip" => "1000",
        "address_country" => "BE",
        "email" => "incendie@federale.be",
        "phone" => "+3225090149",
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1057,
        "supplier_id" => 1057,
        "type_id" => 3,
        "bank_account_iban" => "BE72063157923816",
        "bank_account_bic" => "GKCCBEBB",
        "legal_name" => "FLORA",
        "short_name" => "FLORA",
        "has_vat" => true,
        "vat_number" => "BE0443692549",
        "registration_number" => "0443692549",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Avenue René Comhaire, 65",
        "address_city" => "Berchem-Sainte-Agathe",
        "address_zip" => "1082",
        "address_country" => "BE",
        "email" => "etienne@floragarden.be",
        "phone" => "+3224631302",
        "mobile" => "+32475439629",
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1058,
        "supplier_id" => 1058,
        "type_id" => 3,
        "bank_account_iban" => "BE93734048437967",
        "bank_account_bic" => "KREDBEBB",
        "legal_name" => "FOUCART BENJAMIN",
        "short_name" => "FOUCART",
        "has_vat" => true,
        "vat_number" => "BE0743895671",
        "registration_number" => "0743895671",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Rozenstraat ",
        "address_city" => "Beersel",
        "address_zip" => "1650",
        "address_country" => "BE",
        "email" => null,
        "phone" => "+32471303765",
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1059,
        "supplier_id" => 1059,
        "type_id" => 3,
        "bank_account_iban" => "BE76363013410995",
        "bank_account_bic" => "BBRUBEBB",
        "legal_name" => "GLT CLEAN",
        "short_name" => "GLT",
        "has_vat" => true,
        "vat_number" => "BE0681980175",
        "registration_number" => "0681980175",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Rue de la Royale Harmonie, 13",
        "address_city" => "Braine-l'Alleud",
        "address_zip" => "1420",
        "address_country" => "BE",
        "email" => null,
        "phone" => "+32495481870",
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1060,
        "supplier_id" => 1060,
        "type_id" => 3,
        "bank_account_iban" => "BE46001885722436",
        "bank_account_bic" => "GEBABEBB",
        "legal_name" => "GO 4 GREEN",
        "short_name" => "GO",
        "has_vat" => true,
        "vat_number" => "BE0810981564",
        "registration_number" => "0810981564",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Rue Colonel Bourg, 127/14",
        "address_city" => "Evere",
        "address_zip" => "1140",
        "address_country" => "BE",
        "email" => "info@go4green.be",
        "phone" => "+3226098727",
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1061,
        "supplier_id" => 1061,
        "type_id" => 3,
        "bank_account_iban" => "BE96068950879105",
        "bank_account_bic" => "GKCCBEBB",
        "legal_name" => "GREEN LIGHT SECURITY - VISTA SECURITY",
        "short_name" => "GREEN",
        "has_vat" => true,
        "vat_number" => "BE0861896567",
        "registration_number" => "0861896567",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Avenue du Commerce, 24a",
        "address_city" => "Braine-l'Alleud",
        "address_zip" => "1420",
        "address_country" => "BE",
        "email" => "info@greenlightsecurity.be",
        "phone" => "+3226637000",
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1062,
        "supplier_id" => 1062,
        "type_id" => 3,
        "bank_account_iban" => "BE46363115623636",
        "bank_account_bic" => "BBRUBEBB",
        "legal_name" => "HELP FIRE",
        "short_name" => "HELP",
        "has_vat" => true,
        "vat_number" => "BE0478437949",
        "registration_number" => "0478437949",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Chaussée d'Alsemberg, 1423",
        "address_city" => "Uccle",
        "address_zip" => "1180",
        "address_country" => "BE",
        "email" => "info@helpfire.be",
        "phone" => "+3223764686",
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1063,
        "supplier_id" => 1063,
        "type_id" => 3,
        "bank_account_iban" => "BE17421418151121",
        "bank_account_bic" => "KREDBEBB",
        "legal_name" => "HENRI VERDICKT MAZOUT",
        "short_name" => "HENRI",
        "has_vat" => true,
        "vat_number" => "BE0441943975",
        "registration_number" => "0441943975",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Molenstraat, 261",
        "address_city" => "Grimbergen",
        "address_zip" => "1851",
        "address_country" => "BE",
        "email" => "info@henriverdickt-mazout.be",
        "phone" => "+3222700650",
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1064,
        "supplier_id" => 1064,
        "type_id" => 3,
        "bank_account_iban" => "BE53459250230153",
        "bank_account_bic" => "KREDBEBB",
        "legal_name" => "HORMANN",
        "short_name" => "HORMANN",
        "has_vat" => true,
        "vat_number" => "BE0417609051",
        "registration_number" => "0417609051",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Vrijheidweg, 13",
        "address_city" => "Tongres",
        "address_zip" => "3700",
        "address_country" => "BE",
        "email" => null,
        "phone" => "+3212399222",
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1065,
        "supplier_id" => 1065,
        "type_id" => 3,
        "bank_account_iban" => "BE40310060661563",
        "bank_account_bic" => "BBRUBEBB",
        "legal_name" => "ISB VENTILATION",
        "short_name" => "ISB",
        "has_vat" => true,
        "vat_number" => "BE0402940869",
        "registration_number" => "0402940869",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Rue de Fierlant, 68",
        "address_city" => "Forest",
        "address_zip" => "1190",
        "address_country" => "BE",
        "email" => "project@isbventilation.be",
        "phone" => "+3225332611",
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1066,
        "supplier_id" => 1066,
        "type_id" => 3,
        "bank_account_iban" => "BE69363028861378",
        "bank_account_bic" => "BBRUBEBB",
        "legal_name" => "JD CLEANING",
        "short_name" => "JD",
        "has_vat" => true,
        "vat_number" => "BE0896221701",
        "registration_number" => "0896221701",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Frans Trimmermansstraat, 33/B1",
        "address_city" => "Zellik",
        "address_zip" => "1731",
        "address_country" => "BE",
        "email" => "info@jdcleaning.be",
        "phone" => "+3224694128",
        "mobile" => "+32477930613",
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1067,
        "supplier_id" => 1067,
        "type_id" => 3,
        "bank_account_iban" => "BE83210096472515",
        "bank_account_bic" => "GEBABEBB",
        "legal_name" => "JEAN CRAB ET SES FILS",
        "short_name" => "JEAN",
        "has_vat" => true,
        "vat_number" => "BE0419654662",
        "registration_number" => "0419654662",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Chaussée de Vleurgat, 243",
        "address_city" => "Ixelles",
        "address_zip" => "1050",
        "address_country" => "BE",
        "email" => null,
        "phone" => "+3225359400",
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1068,
        "supplier_id" => 1068,
        "type_id" => 3,
        "bank_account_iban" => "BE96001622053905",
        "bank_account_bic" => "GEBABEBB",
        "legal_name" => "JF CLEANING",
        "short_name" => "JF",
        "has_vat" => true,
        "vat_number" => "BE0829266757",
        "registration_number" => "0829266757",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Avenue des Touterelles, 44",
        "address_city" => "Braine-l'Alleud",
        "address_zip" => "1420",
        "address_country" => "BE",
        "email" => null,
        "phone" => null,
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1069,
        "supplier_id" => 1069,
        "type_id" => 3,
        "bank_account_iban" => "BE58363165699379",
        "bank_account_bic" => "BBRUBEBB",
        "legal_name" => "KANALIS - DBBS - B.BELGIUM",
        "short_name" => "KANALIS",
        "has_vat" => true,
        "vat_number" => "BE0672547817",
        "registration_number" => "0672547817",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Rue de la Croix, 41",
        "address_city" => "Ixelles",
        "address_zip" => "1050",
        "address_country" => "BE",
        "email" => "info@dbbservices.be",
        "phone" => null,
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1070,
        "supplier_id" => 1070,
        "type_id" => 3,
        "bank_account_iban" => "BE64240017620052",
        "bank_account_bic" => "GEBABEBB",
        "legal_name" => "KONE BELGIUM",
        "short_name" => "KONE",
        "has_vat" => true,
        "vat_number" => "BE0436407453",
        "registration_number" => "0436407453",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Boulevard du Roi Albert II, 4 B9",
        "address_city" => "Bruxelles",
        "address_zip" => "1000",
        "address_country" => "BE",
        "email" => null,
        "phone" => null,
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1071,
        "supplier_id" => 1071,
        "type_id" => 3,
        "bank_account_iban" => "BE44068242627945",
        "bank_account_bic" => "GKCCBEBB",
        "legal_name" => "LA CHALEUR ET L'EAU",
        "short_name" => "LA",
        "has_vat" => true,
        "vat_number" => "BE0447080126",
        "registration_number" => "0447080126",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Chaussée de Tubize, 455 bte 2",
        "address_city" => "Braine-l'Alleud",
        "address_zip" => "1420",
        "address_country" => "BE",
        "email" => "info@lachaleuretleau.be",
        "phone" => null,
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1072,
        "supplier_id" => 1072,
        "type_id" => 3,
        "bank_account_iban" => "BE47732011284080",
        "bank_account_bic" => "CREGBEBB",
        "legal_name" => "LA FERME NOS PILIFS",
        "short_name" => "LA",
        "has_vat" => true,
        "vat_number" => "BE0438065757",
        "registration_number" => "0438065757",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Trassersweg, 347",
        "address_city" => "Neder-Over-Heembeek",
        "address_zip" => "1120",
        "address_country" => "BE",
        "email" => null,
        "phone" => "+3222621106",
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1073,
        "supplier_id" => 1073,
        "type_id" => 3,
        "bank_account_iban" => "BE13068078028039",
        "bank_account_bic" => "GKCCBEBB",
        "legal_name" => "LA SERRE OUTIL",
        "short_name" => "LA",
        "has_vat" => true,
        "vat_number" => "BE0420454022",
        "registration_number" => "0420454022",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Chaussée de Stockel, 377",
        "address_city" => "Woluwe-Saint-Pierre",
        "address_zip" => "1150",
        "address_country" => "BE",
        "email" => null,
        "phone" => "+3227628073",
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1074,
        "supplier_id" => 1074,
        "type_id" => 3,
        "bank_account_iban" => "BE69210027570078",
        "bank_account_bic" => "GEBABEBB",
        "legal_name" => "LA VIDANGE L'OISEAU",
        "short_name" => "LA",
        "has_vat" => true,
        "vat_number" => "BE0421968014",
        "registration_number" => "0421968014",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Chaussée de Vilvorde, 94A",
        "address_city" => "Neder-Over-Heembeek",
        "address_zip" => "1120",
        "address_country" => "BE",
        "email" => "info@lavidangeloiseau.be",
        "phone" => "+3224102154",
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1075,
        "supplier_id" => 1075,
        "type_id" => 3,
        "bank_account_iban" => "BE86068230823550",
        "bank_account_bic" => "GKCCBEBB",
        "legal_name" => "LABRANCHE-WALRAVENS HUISSIERS",
        "short_name" => "LABRANCHE-WALRAVENS",
        "has_vat" => true,
        "vat_number" => "BE0841811431",
        "registration_number" => "0841811431",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Chaussée de la Hulpe, 110",
        "address_city" => "Bruxelles",
        "address_zip" => "1000",
        "address_country" => "BE",
        "email" => "info@labranche.info",
        "phone" => "+3225461950",
        "mobile" => "+3225461640",
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1076,
        "supplier_id" => 1076,
        "type_id" => 3,
        "bank_account_iban" => "BE52210007833309",
        "bank_account_bic" => "GEBABEBB",
        "legal_name" => "LE VILLAGE N°1",
        "short_name" => "LE",
        "has_vat" => true,
        "vat_number" => "BE0411648501",
        "registration_number" => "0411648501",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Rue Sart Moulin, 1",
        "address_city" => "Ophain",
        "address_zip" => "1421",
        "address_country" => "BE",
        "email" => null,
        "phone" => null,
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1077,
        "supplier_id" => 1077,
        "type_id" => 3,
        "bank_account_iban" => "BE66001071883443",
        "bank_account_bic" => "GEBABEBB",
        "legal_name" => "LES JEUNES JARDINIERS",
        "short_name" => "LES",
        "has_vat" => true,
        "vat_number" => "BE0414842571",
        "registration_number" => "0414842571",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Chaussée d'Alsemberg, 1393",
        "address_city" => "Uccle",
        "address_zip" => "1180",
        "address_country" => "BE",
        "email" => null,
        "phone" => null,
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1078,
        "supplier_id" => 1078,
        "type_id" => 3,
        "bank_account_iban" => "BE90001525658032",
        "bank_account_bic" => "GEBABEBB",
        "legal_name" => "LIFT EXPERTISE",
        "short_name" => "LIFT",
        "has_vat" => true,
        "vat_number" => "BE0890781088",
        "registration_number" => "0890781088",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Avenue de l'Exposition, 368 bte 34",
        "address_city" => "Jette",
        "address_zip" => "1090",
        "address_country" => "BE",
        "email" => null,
        "phone" => "+32475513405",
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1079,
        "supplier_id" => 1079,
        "type_id" => 3,
        "bank_account_iban" => "BE48068911082227",
        "bank_account_bic" => "GKCCBEBB",
        "legal_name" => "LIFTA9",
        "short_name" => "LIFTA9",
        "has_vat" => true,
        "vat_number" => "BE0708919748",
        "registration_number" => "0708919748",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Rue Colonel Bourg, 127/129",
        "address_city" => "Evere",
        "address_zip" => "1140",
        "address_country" => "BE",
        "email" => null,
        "phone" => null,
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1080,
        "supplier_id" => 1080,
        "type_id" => 3,
        "bank_account_iban" => "BE79001690034333",
        "bank_account_bic" => "GEBABEBB",
        "legal_name" => "M.M.S. DEBOUCHAGE",
        "short_name" => "M.M.S.",
        "has_vat" => true,
        "vat_number" => "BE0502435848",
        "registration_number" => "0502435848",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Chaussée d'Alsemberg, 1031B bte 58",
        "address_city" => "Uccle",
        "address_zip" => "1180",
        "address_country" => "BE",
        "email" => "mms.debouchage@gmail.com",
        "phone" => "+3223770018",
        "mobile" => "+32477283214",
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1081,
        "supplier_id" => 1081,
        "type_id" => 3,
        "bank_account_iban" => "BE11001682886948",
        "bank_account_bic" => "GEBABEBB",
        "legal_name" => "M2M TEC",
        "short_name" => "M2M",
        "has_vat" => true,
        "vat_number" => "BE0500700538",
        "registration_number" => "0500700538",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Spechtdreef, 10",
        "address_city" => "Grobbendonk",
        "address_zip" => "2280",
        "address_country" => "BE",
        "email" => "info@m2mtec.be",
        "phone" => null,
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1082,
        "supplier_id" => 1082,
        "type_id" => 3,
        "bank_account_iban" => "BE57143084752035",
        "bank_account_bic" => "GEBABEBB",
        "legal_name" => "MAGONETTE JARDINS",
        "short_name" => "MAGONETTE",
        "has_vat" => true,
        "vat_number" => "BE0849336255",
        "registration_number" => "0849336255",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Avenue Reine Astrid, 86",
        "address_city" => "La Hulpe",
        "address_zip" => "1310",
        "address_country" => "BE",
        "email" => null,
        "phone" => "+3226331975",
        "mobile" => "+32477340991",
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1083,
        "supplier_id" => 1083,
        "type_id" => 3,
        "bank_account_iban" => "BE55310034663644",
        "bank_account_bic" => "BBRUBEBB",
        "legal_name" => "MAISON DE KEYSER",
        "short_name" => "MAISON",
        "has_vat" => true,
        "vat_number" => "BE0419334364",
        "registration_number" => "0419334364",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Rue de la Gare, 9",
        "address_city" => "Etterbeek",
        "address_zip" => "1040",
        "address_country" => "BE",
        "email" => null,
        "phone" => null,
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1084,
        "supplier_id" => 1084,
        "type_id" => 3,
        "bank_account_iban" => "BE23732040598591",
        "bank_account_bic" => "CREGBEBB",
        "legal_name" => "MENUISERIE MORDANT & FILS",
        "short_name" => "MENUISERIE",
        "has_vat" => true,
        "vat_number" => "BE0438101389",
        "registration_number" => "0438101389",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Rue de l'Industrie, 9",
        "address_city" => "Jumet",
        "address_zip" => "6040",
        "address_country" => "BE",
        "email" => "menuiserie@mordantetfils.be",
        "phone" => "+3271359953",
        "mobile" => "+32472700000",
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1085,
        "supplier_id" => 1085,
        "type_id" => 3,
        "bank_account_iban" => "BE56734054240688",
        "bank_account_bic" => "KREDBEBB",
        "legal_name" => "MIROITERIE LEYS & FILS",
        "short_name" => "MIROITERIE",
        "has_vat" => true,
        "vat_number" => "BE0422362744",
        "registration_number" => "0422362744",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Clos Saint-Martin, 12",
        "address_city" => "Ganshoren",
        "address_zip" => "1083",
        "address_country" => "BE",
        "email" => null,
        "phone" => null,
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1086,
        "supplier_id" => 1086,
        "type_id" => 3,
        "bank_account_iban" => "BE82068936791368",
        "bank_account_bic" => "GKCCBEBB",
        "legal_name" => "MMS - MEGANCK MAINTENANCE SERVICES",
        "short_name" => "MMS",
        "has_vat" => true,
        "vat_number" => "BE0681866547",
        "registration_number" => "0681866547",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Avenue d'Italie, 38 bte 4",
        "address_city" => "Ixelles",
        "address_zip" => "1050",
        "address_country" => "BE",
        "email" => null,
        "phone" => "+32474581365",
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1087,
        "supplier_id" => 1087,
        "type_id" => 3,
        "bank_account_iban" => "BE50731028968518",
        "bank_account_bic" => "KREDBEBB",
        "legal_name" => "MONIZZE",
        "short_name" => "MONIZZE",
        "has_vat" => true,
        "vat_number" => "BE0834013324",
        "registration_number" => "0834013324",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "R. Vandendriesschelaan, 18",
        "address_city" => "Woluwe-Saint-Pierre",
        "address_zip" => "1150",
        "address_country" => "BE",
        "email" => "supportclient@monizze.be",
        "phone" => "+3228918844",
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1088,
        "supplier_id" => 1088,
        "type_id" => 3,
        "bank_account_iban" => "BE94143105168414",
        "bank_account_bic" => "GEBABEBB",
        "legal_name" => "ND DETECT",
        "short_name" => "ND",
        "has_vat" => true,
        "vat_number" => "BE0894918337",
        "registration_number" => "0894918337",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Bruyère du Culot, 4",
        "address_city" => "Villers-la-Ville",
        "address_zip" => "1495",
        "address_country" => "BE",
        "email" => "info@nddetect.be",
        "phone" => "+32473187518",
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1089,
        "supplier_id" => 1089,
        "type_id" => 3,
        "bank_account_iban" => "BE47363147783580",
        "bank_account_bic" => "BBRUBEBB",
        "legal_name" => "Ô FUITE",
        "short_name" => "Ô",
        "has_vat" => true,
        "vat_number" => "BE0629914535",
        "registration_number" => "0629914535",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Chaussée de Vleurgat, 320",
        "address_city" => "Ixelles",
        "address_zip" => "1050",
        "address_country" => "BE",
        "email" => "info@ofuite.be",
        "phone" => "+32472881124",
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1090,
        "supplier_id" => 1090,
        "type_id" => 3,
        "bank_account_iban" => "BE14096307050083",
        "bank_account_bic" => "GKCCBEBB",
        "legal_name" => "OCTA+ ENERGIE",
        "short_name" => "OCTA+",
        "has_vat" => true,
        "vat_number" => "BE0401934742",
        "registration_number" => "0401934742",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Schaarbeeklei, 600",
        "address_city" => "Vilvoorde",
        "address_zip" => "1800",
        "address_country" => "BE",
        "email" => "energie@octaplus.be",
        "phone" => "+3228510252",
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1091,
        "supplier_id" => 1091,
        "type_id" => 3,
        "bank_account_iban" => "BE89068946919885",
        "bank_account_bic" => "GKCCBEBB",
        "legal_name" => "OCTA+ MAZOUT",
        "short_name" => "OCTA+",
        "has_vat" => true,
        "vat_number" => "BE0437727445",
        "registration_number" => "0437727445",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Schaarbeeklei, 600",
        "address_city" => "Vilvoorde",
        "address_zip" => "1800",
        "address_country" => "BE",
        "email" => "mazout@octaplus.be",
        "phone" => "+3226482323",
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1092,
        "supplier_id" => 1092,
        "type_id" => 3,
        "bank_account_iban" => "BE64001750457552",
        "bank_account_bic" => "GEBABEBB",
        "legal_name" => "OKDO BUILDING MAINTENANCE",
        "short_name" => "OKDO",
        "has_vat" => true,
        "vat_number" => "BE0892302901",
        "registration_number" => "0892302901",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Chaussée de Tubize, 487",
        "address_city" => "Braine-l'Alleud",
        "address_zip" => "1420",
        "address_country" => "BE",
        "email" => null,
        "phone" => "+3223671064",
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1093,
        "supplier_id" => 1093,
        "type_id" => 3,
        "bank_account_iban" => "BE90001494394932",
        "bank_account_bic" => "GEBABEBB",
        "legal_name" => "ORBAN CLEANER",
        "short_name" => "ORBAN",
        "has_vat" => true,
        "vat_number" => "BE0458564728",
        "registration_number" => "0458564728",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Rue Cardinal Lavigerie, 43",
        "address_city" => "Etterbeek",
        "address_zip" => "1040",
        "address_country" => "BE",
        "email" => null,
        "phone" => "+32474367346",
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1094,
        "supplier_id" => 1094,
        "type_id" => 3,
        "bank_account_iban" => "BE34001734999590",
        "bank_account_bic" => "GEBABEBB",
        "legal_name" => "OTIS",
        "short_name" => "OTIS",
        "has_vat" => true,
        "vat_number" => "BE0400388581",
        "registration_number" => "0400388581",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Spoorwegstraat, 34",
        "address_city" => "Dilbeek",
        "address_zip" => "1702",
        "address_country" => "BE",
        "email" => null,
        "phone" => null,
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1095,
        "supplier_id" => 1095,
        "type_id" => 3,
        "bank_account_iban" => "BE74068901399607",
        "bank_account_bic" => "GKCCBEBB",
        "legal_name" => "PARLE-AU-PHONE",
        "short_name" => "PARLE-AU-PHONE",
        "has_vat" => true,
        "vat_number" => "BE0886402133",
        "registration_number" => "0886402133",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Avenue Emile Verhaeren, 56",
        "address_city" => "Schaerbeek",
        "address_zip" => "1030",
        "address_country" => "BE",
        "email" => null,
        "phone" => "+32475637729",
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1096,
        "supplier_id" => 1096,
        "type_id" => 3,
        "bank_account_iban" => "BE90751207336732",
        "bank_account_bic" => "AXABBE22",
        "legal_name" => "PLOMBERIE DEWAME",
        "short_name" => "PLOMBERIE",
        "has_vat" => true,
        "vat_number" => "BE0568966564",
        "registration_number" => "0568966564",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Rue Longue, 286",
        "address_city" => "Drogenbos",
        "address_zip" => "1620",
        "address_country" => "BE",
        "email" => null,
        "phone" => "+3223330325",
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1097,
        "supplier_id" => 1097,
        "type_id" => 3,
        "bank_account_iban" => "BE44363160050545",
        "bank_account_bic" => "BBRUBEBB",
        "legal_name" => "PRODETEC",
        "short_name" => "PRODETEC",
        "has_vat" => true,
        "vat_number" => "BE0879242939",
        "registration_number" => "0879242939",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Avenue Octave Michot, 36",
        "address_city" => "Rhode-Saint-Genèse",
        "address_zip" => "1640",
        "address_country" => "BE",
        "email" => "info@prodetec.be",
        "phone" => "+3226608080",
        "mobile" => "+32478447878",
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1098,
        "supplier_id" => 1098,
        "type_id" => 3,
        "bank_account_iban" => "BE26270018301529",
        "bank_account_bic" => "GEBABEBB",
        "legal_name" => "PROXIFUEL - TOTAL BELGIUM",
        "short_name" => "PROXIFUEL",
        "has_vat" => true,
        "vat_number" => "BE0407234704",
        "registration_number" => "0407234704",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Rue du commerce, 113",
        "address_city" => "Etterbeek",
        "address_zip" => "1040",
        "address_country" => "BE",
        "email" => null,
        "phone" => "+3222668080",
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1099,
        "supplier_id" => 1099,
        "type_id" => 3,
        "bank_account_iban" => "BE82210000088968",
        "bank_account_bic" => "GEBABEBB",
        "legal_name" => "PROXIMUS",
        "short_name" => "PROXIMUS",
        "has_vat" => true,
        "vat_number" => "BE0202239951",
        "registration_number" => "0202239951",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Boulevard du Roi Albert II, 27",
        "address_city" => "Schaerbeek",
        "address_zip" => "1030",
        "address_country" => "BE",
        "email" => "syndic@proximus.com",
        "phone" => null,
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1100,
        "supplier_id" => 1100,
        "type_id" => 3,
        "bank_account_iban" => "BE17068251345821",
        "bank_account_bic" => "GKCCBEBB",
        "legal_name" => "REBETON",
        "short_name" => "REBETON",
        "has_vat" => true,
        "vat_number" => "BE0432761045",
        "registration_number" => "0432761045",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Chaussée de Waterloo, 930",
        "address_city" => "Bruxelles",
        "address_zip" => "1000",
        "address_country" => "BE",
        "email" => "info@rebeton.be",
        "phone" => "+3223737040",
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1101,
        "supplier_id" => 1101,
        "type_id" => 3,
        "bank_account_iban" => "BE58210013097779",
        "bank_account_bic" => "GEBABEBB",
        "legal_name" => "REMA GRAVURE",
        "short_name" => "REMA",
        "has_vat" => true,
        "vat_number" => "BE0436247107",
        "registration_number" => "0436247107",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Rue Emmanuel Mertens, 23",
        "address_city" => "Woluwe-Saint-Pierre",
        "address_zip" => "1150",
        "address_country" => "BE",
        "email" => "rema.gravure@skynet.be",
        "phone" => "+3227718094",
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1102,
        "supplier_id" => 1102,
        "type_id" => 3,
        "bank_account_iban" => "BE75645102220251",
        "bank_account_bic" => "JVBABE22",
        "legal_name" => "RSTELEC",
        "short_name" => "RSTELEC",
        "has_vat" => true,
        "vat_number" => "BE0456991546",
        "registration_number" => "0456991546",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Chaussée de Ruisbroek, 3",
        "address_city" => "Forest",
        "address_zip" => "1190",
        "address_country" => "BE",
        "email" => null,
        "phone" => null,
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1103,
        "supplier_id" => 1103,
        "type_id" => 3,
        "bank_account_iban" => "BE09953031524157",
        "bank_account_bic" => "CTBKBEBX",
        "legal_name" => "SANET'IC",
        "short_name" => "SANET'IC",
        "has_vat" => true,
        "vat_number" => "BE0463847070",
        "registration_number" => "0463847070",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Rue Bel Air, 11",
        "address_city" => "Tubize",
        "address_zip" => "1480",
        "address_country" => "BE",
        "email" => "ericplomberie@live.be",
        "phone" => "+3223555790",
        "mobile" => "+32475279050",
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1104,
        "supplier_id" => 1104,
        "type_id" => 3,
        "bank_account_iban" => "BE54429403800197",
        "bank_account_bic" => "KREDBEBB",
        "legal_name" => "SANIFAUST",
        "short_name" => "SANIFAUST",
        "has_vat" => true,
        "vat_number" => "BE0476905646",
        "registration_number" => "0476905646",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Rue du Pommier, 360",
        "address_city" => "Anderlecht",
        "address_zip" => "1070",
        "address_country" => "BE",
        "email" => null,
        "phone" => "+32475234883",
        "mobile" => "+32477395081",
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1105,
        "supplier_id" => 1105,
        "type_id" => 3,
        "bank_account_iban" => "BE34210004853890",
        "bank_account_bic" => "GEBABEBB",
        "legal_name" => "SCHINDLER",
        "short_name" => "SCHINDLER",
        "has_vat" => true,
        "vat_number" => "BE0416481673",
        "registration_number" => "0416481673",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Boulevard de l'Humanité, 241A",
        "address_city" => "Drogenbos",
        "address_zip" => "1620",
        "address_country" => "BE",
        "email" => null,
        "phone" => null,
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1106,
        "supplier_id" => 1106,
        "type_id" => 3,
        "bank_account_iban" => "BE11290006307748",
        "bank_account_bic" => "GEBABEBB",
        "legal_name" => "SECUREX SECRÉTARIAT SOCIAL",
        "short_name" => "SECUREX",
        "has_vat" => true,
        "vat_number" => "BE0401086981",
        "registration_number" => "0401086981",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Cours Saint-Michel, 30",
        "address_city" => "Etterbeek",
        "address_zip" => "1040",
        "address_country" => "BE",
        "email" => null,
        "phone" => null,
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1107,
        "supplier_id" => 1107,
        "type_id" => 3,
        "bank_account_iban" => "BE85210019094706",
        "bank_account_bic" => "GEBABEBB",
        "legal_name" => "SERRURERIE ANDRE",
        "short_name" => "SERRURERIE",
        "has_vat" => true,
        "vat_number" => "BE0558539658",
        "registration_number" => "0558539658",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Chaussée de Gand, 1143",
        "address_city" => "Berchem-Sainte-Agathe",
        "address_zip" => "1082",
        "address_country" => "BE",
        "email" => "info@serrurerie-andre.be",
        "phone" => "+32475946500",
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1108,
        "supplier_id" => 1108,
        "type_id" => 3,
        "bank_account_iban" => "BE29000352950664",
        "bank_account_bic" => "BPOTBEB1",
        "legal_name" => "SERRURERIE DE VLEURGAT",
        "short_name" => "SERRURERIE",
        "has_vat" => true,
        "vat_number" => "BE0811815962",
        "registration_number" => "0811815962",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Chaussée de Vleurgat, 152",
        "address_city" => "Bruxelles",
        "address_zip" => "1000",
        "address_country" => "BE",
        "email" => "serrureexpress@hotmail.com",
        "phone" => "+3226440178",
        "mobile" => "+32475526169",
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1109,
        "supplier_id" => 1109,
        "type_id" => 3,
        "bank_account_iban" => "BE59068249095926",
        "bank_account_bic" => "GKCCBEBB",
        "legal_name" => "SERRURERIE GEORGES HENRI - AZIZ HICHOU",
        "short_name" => "SERRURERIE",
        "has_vat" => true,
        "vat_number" => "BE0893262112",
        "registration_number" => "0893262112",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Avenue Georges Henri, 235",
        "address_city" => "Woluwe-Saint-Lambert",
        "address_zip" => "1200",
        "address_country" => "BE",
        "email" => null,
        "phone" => null,
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1110,
        "supplier_id" => 1110,
        "type_id" => 3,
        "bank_account_iban" => "BE57001812293335",
        "bank_account_bic" => "GEBABEBB",
        "legal_name" => "SERRURERIE GRIMONPREZ",
        "short_name" => "SERRURERIE",
        "has_vat" => true,
        "vat_number" => "BE0673639165",
        "registration_number" => "0673639165",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Avenue Montjoie, 9",
        "address_city" => "Uccle",
        "address_zip" => "1180",
        "address_country" => "BE",
        "email" => null,
        "phone" => null,
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1111,
        "supplier_id" => 1111,
        "type_id" => 3,
        "bank_account_iban" => "BE67363009753287",
        "bank_account_bic" => "BBRUBEBB",
        "legal_name" => "SERRURERIE MONTGOMERY",
        "short_name" => "SERRURERIE",
        "has_vat" => true,
        "vat_number" => "BE0826500376",
        "registration_number" => "0826500376",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Avenue Georges Henry, 336",
        "address_city" => "Woluwe-Saint-Lambert",
        "address_zip" => "1200",
        "address_country" => "BE",
        "email" => "info@depserrure.be",
        "phone" => "+32488823516",
        "mobile" => "+3223257425",
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1112,
        "supplier_id" => 1112,
        "type_id" => 3,
        "bank_account_iban" => "BE55734028579744",
        "bank_account_bic" => "KREDBEBB",
        "legal_name" => "SERRURIER DU GLOBE",
        "short_name" => "SERRURIER",
        "has_vat" => true,
        "vat_number" => "BE0546899856",
        "registration_number" => "0546899856",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Avenue Brugmann, 591",
        "address_city" => "Uccle",
        "address_zip" => "1180",
        "address_country" => "BE",
        "email" => "serrurier.du.globe@gmail.com",
        "phone" => "+3223458886",
        "mobile" => "+32485258711",
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1113,
        "supplier_id" => 1113,
        "type_id" => 3,
        "bank_account_iban" => "BE85001746737806",
        "bank_account_bic" => "GEBABEBB",
        "legal_name" => "SHINE PRO",
        "short_name" => "SHINE",
        "has_vat" => true,
        "vat_number" => "BE0506918238",
        "registration_number" => "0506918238",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Rue au Bois, 568",
        "address_city" => "Woluwe-Saint-Pierre",
        "address_zip" => "1150",
        "address_country" => "BE",
        "email" => "info@shinepro.be",
        "phone" => "+32478568428",
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1114,
        "supplier_id" => 1114,
        "type_id" => 3,
        "bank_account_iban" => "BE29097010016864",
        "bank_account_bic" => "GKCCBEBB",
        "legal_name" => "SIAMU",
        "short_name" => "SIAMU",
        "has_vat" => true,
        "vat_number" => "BE0241570679",
        "registration_number" => "0241570679",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Avenue de l'Heliport, 11-15",
        "address_city" => "Bruxelles",
        "address_zip" => "1000",
        "address_country" => "BE",
        "email" => null,
        "phone" => "+3222088420",
        "mobile" => "+3222088484",
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1115,
        "supplier_id" => 1115,
        "type_id" => 3,
        "bank_account_iban" => "BE07737044564166",
        "bank_account_bic" => "KREDBEBB",
        "legal_name" => "SIBELGA",
        "short_name" => "SIBELGA",
        "has_vat" => true,
        "vat_number" => "BE0222869673",
        "registration_number" => "0222869673",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Boulevard Émile Jacqmain, 96",
        "address_city" => "Bruxelles",
        "address_zip" => "1000",
        "address_country" => "BE",
        "email" => "clients@sibelga.be",
        "phone" => "+3225494100",
        "mobile" => "+3280019400",
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1116,
        "supplier_id" => 1116,
        "type_id" => 3,
        "bank_account_iban" => "BE22210005809847",
        "bank_account_bic" => "GEBABEBB",
        "legal_name" => "SODEXO",
        "short_name" => "SODEXO",
        "has_vat" => true,
        "vat_number" => "BE0403167335",
        "registration_number" => "0403167335",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Boulevard de la Plaine, 15",
        "address_city" => "Ixelles",
        "address_zip" => "1050",
        "address_country" => "BE",
        "email" => "support-sodexo.be@sodexo.com",
        "phone" => "+3225475445",
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1117,
        "supplier_id" => 1117,
        "type_id" => 3,
        "bank_account_iban" => "BE21630012711103",
        "bank_account_bic" => "BBRUBEBB",
        "legal_name" => "STEPHANANDRA",
        "short_name" => "STEPHANANDRA",
        "has_vat" => true,
        "vat_number" => "BE0465658990",
        "registration_number" => "0465658990",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Rue Planchette, 36",
        "address_city" => "Ittre",
        "address_zip" => "1460",
        "address_country" => "BE",
        "email" => "staphanandra@hotmail.com",
        "phone" => "+3267647909",
        "mobile" => "+32477416250",
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1118,
        "supplier_id" => 1118,
        "type_id" => 3,
        "bank_account_iban" => "BE08096969000113",
        "bank_account_bic" => "GKCCBEBB",
        "legal_name" => "SWDE",
        "short_name" => "SWDE",
        "has_vat" => true,
        "vat_number" => "BE0230132005",
        "registration_number" => "0230132005",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Rue de la Concorde, 41",
        "address_city" => "Verviers",
        "address_zip" => "4800",
        "address_country" => "BE",
        "email" => "info@swde.be",
        "phone" => "+3287878787",
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1119,
        "supplier_id" => 1119,
        "type_id" => 3,
        "bank_account_iban" => "BE30310068426011",
        "bank_account_bic" => "BBRUBEBB",
        "legal_name" => "SYMULAK",
        "short_name" => "SYMULAK",
        "has_vat" => true,
        "vat_number" => "BE0432034535",
        "registration_number" => "0432034535",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Avenue du Couronnement, 1",
        "address_city" => "Woluwe-Saint-Lambert",
        "address_zip" => "1200",
        "address_country" => "BE",
        "email" => "info@symulak.be",
        "phone" => "+3227717807",
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1120,
        "supplier_id" => 1120,
        "type_id" => 3,
        "bank_account_iban" => "BE19103017308912",
        "bank_account_bic" => "NICABEBB",
        "legal_name" => "TECH-IMMO",
        "short_name" => "TECH-IMMO",
        "has_vat" => true,
        "vat_number" => "BE0882301409",
        "registration_number" => "0882301409",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Rue Reimond Stijns, 103",
        "address_city" => "Molenbeek-Saint-Jean",
        "address_zip" => "1080",
        "address_country" => "BE",
        "email" => "info@tech-immo.be",
        "phone" => "+3224148139",
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1121,
        "supplier_id" => 1121,
        "type_id" => 3,
        "bank_account_iban" => "BE43210060843001",
        "bank_account_bic" => "GEBABEBB",
        "legal_name" => "TK ELEVATOR - THYSSENKRUPP",
        "short_name" => "TK",
        "has_vat" => true,
        "vat_number" => "BE0447794857",
        "registration_number" => "0447794857",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Avenue de la Métrologie, 10",
        "address_city" => "Haren",
        "address_zip" => "1130",
        "address_country" => "BE",
        "email" => "cedric.deboes@tkelevator.com",
        "phone" => "+3222473648",
        "mobile" => "+3222473511",
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1122,
        "supplier_id" => 1122,
        "type_id" => 3,
        "bank_account_iban" => "BE31751203119555",
        "bank_account_bic" => "AXABBE22",
        "legal_name" => "TOP NET",
        "short_name" => "TOP",
        "has_vat" => true,
        "vat_number" => "BE0891620337",
        "registration_number" => "0891620337",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Avenue du Soleil, 10",
        "address_city" => "Rhode-Saint-Genèse",
        "address_zip" => "1640",
        "address_country" => "BE",
        "email" => "topnetto@yahoo.fr",
        "phone" => null,
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1123,
        "supplier_id" => 1123,
        "type_id" => 3,
        "bank_account_iban" => "BE38001509424272",
        "bank_account_bic" => "GEBABEBB",
        "legal_name" => "TOTAL ENERGIES",
        "short_name" => "TOTAL",
        "has_vat" => true,
        "vat_number" => "BE0859655570",
        "registration_number" => "0859655570",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Rue Saint-Laurent, 54",
        "address_city" => "Liège",
        "address_zip" => "4000",
        "address_country" => "BE",
        "email" => "syndic.corp@totalenergies.be",
        "phone" => null,
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1124,
        "supplier_id" => 1124,
        "type_id" => 3,
        "bank_account_iban" => "BE16751206535874",
        "bank_account_bic" => "AXABBE22",
        "legal_name" => "TOUT DEBOUCHAGE ERIC",
        "short_name" => "TOUT",
        "has_vat" => true,
        "vat_number" => "BE0537307249",
        "registration_number" => "0537307249",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Rue de Mons, 172",
        "address_city" => "Tubize",
        "address_zip" => "1480",
        "address_country" => "BE",
        "email" => "debouchageeric@gmail.com",
        "phone" => "+32471053552",
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1125,
        "supplier_id" => 1125,
        "type_id" => 3,
        "bank_account_iban" => "BE48068889810127",
        "bank_account_bic" => "GKCCBEBB",
        "legal_name" => "UCM",
        "short_name" => "UCM",
        "has_vat" => true,
        "vat_number" => "BE0479713894",
        "registration_number" => "0479713894",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Rue Colonel Bourg, 123",
        "address_city" => "Evere",
        "address_zip" => "1140",
        "address_country" => "BE",
        "email" => "ge.bxl2@ucm.be",
        "phone" => "+3227750380",
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1126,
        "supplier_id" => 1126,
        "type_id" => 3,
        "bank_account_iban" => "BE32001651231202",
        "bank_account_bic" => "GEBABEBB",
        "legal_name" => "VERREZEN ANN HUISSIER",
        "short_name" => "VERREZEN",
        "has_vat" => true,
        "vat_number" => "BE0839167487",
        "registration_number" => "0839167487",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Lindestraat, 16",
        "address_city" => "Merchtem",
        "address_zip" => "1785",
        "address_country" => "BE",
        "email" => null,
        "phone" => null,
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1127,
        "supplier_id" => 1127,
        "type_id" => 3,
        "bank_account_iban" => "BE43271008610501",
        "bank_account_bic" => "GEBABEBB",
        "legal_name" => "VIDANGE EFFICACE",
        "short_name" => "VIDANGE",
        "has_vat" => true,
        "vat_number" => "BE0420769865",
        "registration_number" => "0420769865",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Avenue Zenobbe Gramme, 12",
        "address_city" => "Wavre",
        "address_zip" => "1300",
        "address_country" => "BE",
        "email" => "christian.pourtois@vidange.be",
        "phone" => "+3210241733",
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1128,
        "supplier_id" => 1128,
        "type_id" => 3,
        "bank_account_iban" => "BE05363163206075",
        "bank_account_bic" => "BBRUBEBB",
        "legal_name" => "VIDANGE LIMALOISE",
        "short_name" => "VIDANGE",
        "has_vat" => true,
        "vat_number" => "BE0880770490",
        "registration_number" => "0880770490",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Rue de Grimohaye, 84",
        "address_city" => "Limal",
        "address_zip" => "1300",
        "address_country" => "BE",
        "email" => "info@vidangelimaloise.be",
        "phone" => "+3210416750",
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1129,
        "supplier_id" => 1129,
        "type_id" => 3,
        "bank_account_iban" => "BE25210041441482",
        "bank_account_bic" => "GEBABEBB",
        "legal_name" => "VINCOTTE",
        "short_name" => "VINCOTTE",
        "has_vat" => true,
        "vat_number" => "BE0402726875",
        "registration_number" => "0402726875",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Jan Olleslagerslaan, 35",
        "address_city" => "Vilvoorde",
        "address_zip" => "1800",
        "address_country" => "BE",
        "email" => null,
        "phone" => null,
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1130,
        "supplier_id" => 1130,
        "type_id" => 3,
        "bank_account_iban" => "BE82210010768668",
        "bank_account_bic" => "GEBABEBB",
        "legal_name" => "VITESSKE",
        "short_name" => "VITESSKE",
        "has_vat" => true,
        "vat_number" => "BE0402079252",
        "registration_number" => "0402079252",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Vilvoordsesteenweg, 94A",
        "address_city" => "Neder-Over-Heembeek",
        "address_zip" => "1120",
        "address_country" => "BE",
        "email" => "info@vitesske.be",
        "phone" => "+3224274290",
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1131,
        "supplier_id" => 1131,
        "type_id" => 3,
        "bank_account_iban" => "BE52096011784309",
        "bank_account_bic" => "GKCCBEBB",
        "legal_name" => "VIVAQUA",
        "short_name" => "VIVAQUA",
        "has_vat" => true,
        "vat_number" => "BE0202962701",
        "registration_number" => "0202962701",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Boulevard de l'Impératrice, 17-19",
        "address_city" => "Bruxelles",
        "address_zip" => "1000",
        "address_country" => "BE",
        "email" => null,
        "phone" => null,
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1132,
        "supplier_id" => 1132,
        "type_id" => 3,
        "bank_account_iban" => "BE06096322641522",
        "bank_account_bic" => "GKCCBEBB",
        "legal_name" => "VOO",
        "short_name" => "VOO",
        "has_vat" => true,
        "vat_number" => "BE0205954655",
        "registration_number" => "0205954655",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Rue de Naples, 29",
        "address_city" => "Ixelles",
        "address_zip" => "1050",
        "address_country" => "BE",
        "email" => null,
        "phone" => "+3278505050",
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1133,
        "supplier_id" => 1133,
        "type_id" => 3,
        "bank_account_iban" => "BE26732043570229",
        "bank_account_bic" => "CREGBEBB",
        "legal_name" => "WATERTECH SA - APRE",
        "short_name" => "WATERTECH",
        "has_vat" => true,
        "vat_number" => "BE0806996844",
        "registration_number" => "0806996844",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Rue de la Cale Sèche, 34",
        "address_city" => "Haccourt",
        "address_zip" => "4684",
        "address_country" => "BE",
        "email" => "info@water-tech.be",
        "phone" => "+3243743070",
        "mobile" => null,
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1134,
        "supplier_id" => 1134,
        "type_id" => 3,
        "bank_account_iban" => "BE14210056088583",
        "bank_account_bic" => "GEBABEBB",
        "legal_name" => "WRZESINSKI",
        "short_name" => "WRZESINSKI",
        "has_vat" => true,
        "vat_number" => "BE0440186394",
        "registration_number" => "0440186394",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Parc des Saules, 18 bte 3",
        "address_city" => "Wavre",
        "address_zip" => "1300",
        "address_country" => "BE",
        "email" => "electricite@wrzesinski-sa.be",
        "phone" => "+3210222999",
        "mobile" => "+32475234710",
        "website" => null,
        "is_active" => true
      ]);
Identity::create([
        "id" => 1135,
        "supplier_id" => 1135,
        "type_id" => 3,
        "bank_account_iban" => "BE23001690389391",
        "bank_account_bic" => "GEBABEBB",
        "legal_name" => "X ELEVATION",
        "short_name" => "X",
        "has_vat" => true,
        "vat_number" => "BE0502999042",
        "registration_number" => "0502999042",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Avenue Guillaume Stassart, 102",
        "address_city" => "Anderlecht",
        "address_zip" => "1070",
        "address_country" => "BE",
        "email" => "pierre@xelevation.com",
        "phone" => "+3225245986",
        "mobile" => "+32495515619",
        "website" => null,
        "is_active" => true
]);


Supplier::create([
        "id" => 1001,
        "identity_id" => 1001,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1002,
        "identity_id" => 1002,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1003,
        "identity_id" => 1003,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1004,
        "identity_id" => 1004,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1005,
        "identity_id" => 1005,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1006,
        "identity_id" => 1006,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1007,
        "identity_id" => 1007,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1008,
        "identity_id" => 1008,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1009,
        "identity_id" => 1009,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1010,
        "identity_id" => 1010,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1011,
        "identity_id" => 1011,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1012,
        "identity_id" => 1012,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1013,
        "identity_id" => 1013,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1014,
        "identity_id" => 1014,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1015,
        "identity_id" => 1015,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1016,
        "identity_id" => 1016,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1017,
        "identity_id" => 1017,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1018,
        "identity_id" => 1018,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1019,
        "identity_id" => 1019,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1020,
        "identity_id" => 1020,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1021,
        "identity_id" => 1021,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1022,
        "identity_id" => 1022,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1023,
        "identity_id" => 1023,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1024,
        "identity_id" => 1024,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1025,
        "identity_id" => 1025,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1026,
        "identity_id" => 1026,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1027,
        "identity_id" => 1027,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1028,
        "identity_id" => 1028,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1029,
        "identity_id" => 1029,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1030,
        "identity_id" => 1030,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1031,
        "identity_id" => 1031,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1032,
        "identity_id" => 1032,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1033,
        "identity_id" => 1033,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1034,
        "identity_id" => 1034,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1035,
        "identity_id" => 1035,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1036,
        "identity_id" => 1036,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1037,
        "identity_id" => 1037,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1038,
        "identity_id" => 1038,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1039,
        "identity_id" => 1039,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1040,
        "identity_id" => 1040,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1041,
        "identity_id" => 1041,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1042,
        "identity_id" => 1042,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1043,
        "identity_id" => 1043,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1044,
        "identity_id" => 1044,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1045,
        "identity_id" => 1045,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1046,
        "identity_id" => 1046,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1047,
        "identity_id" => 1047,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1048,
        "identity_id" => 1048,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1049,
        "identity_id" => 1049,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1050,
        "identity_id" => 1050,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1051,
        "identity_id" => 1051,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1052,
        "identity_id" => 1052,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1053,
        "identity_id" => 1053,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1054,
        "identity_id" => 1054,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1055,
        "identity_id" => 1055,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1056,
        "identity_id" => 1056,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1057,
        "identity_id" => 1057,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1058,
        "identity_id" => 1058,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1059,
        "identity_id" => 1059,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1060,
        "identity_id" => 1060,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1061,
        "identity_id" => 1061,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1062,
        "identity_id" => 1062,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1063,
        "identity_id" => 1063,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1064,
        "identity_id" => 1064,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1065,
        "identity_id" => 1065,
        "legal_name" => "ISB VENTILATION",
        "short_name" => "ISB",
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1066,
        "identity_id" => 1066,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1067,
        "identity_id" => 1067,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1068,
        "identity_id" => 1068,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1069,
        "identity_id" => 1069,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1070,
        "identity_id" => 1070,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1071,
        "identity_id" => 1071,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1072,
        "identity_id" => 1072,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1073,
        "identity_id" => 1073,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1074,
        "identity_id" => 1074,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1075,
        "identity_id" => 1075,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1076,
        "identity_id" => 1076,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1077,
        "identity_id" => 1077,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1078,
        "identity_id" => 1078,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1079,
        "identity_id" => 1079,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1080,
        "identity_id" => 1080,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1081,
        "identity_id" => 1081,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1082,
        "identity_id" => 1082,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1083,
        "identity_id" => 1083,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1084,
        "identity_id" => 1084,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1085,
        "identity_id" => 1085,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1086,
        "identity_id" => 1086,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1087,
        "identity_id" => 1087,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1088,
        "identity_id" => 1088,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1089,
        "identity_id" => 1089,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1090,
        "identity_id" => 1090,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1091,
        "identity_id" => 1091,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1092,
        "identity_id" => 1092,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1093,
        "identity_id" => 1093,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1094,
        "identity_id" => 1094,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1095,
        "identity_id" => 1095,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1096,
        "identity_id" => 1096,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1097,
        "identity_id" => 1097,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1098,
        "identity_id" => 1098,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1099,
        "identity_id" => 1099,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1100,
        "identity_id" => 1100,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1101,
        "identity_id" => 1101,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1102,
        "identity_id" => 1102,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1103,
        "identity_id" => 1103,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1104,
        "identity_id" => 1104,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1105,
        "identity_id" => 1105,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1106,
        "identity_id" => 1106,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1107,
        "identity_id" => 1107,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1108,
        "identity_id" => 1108,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1109,
        "identity_id" => 1109,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1110,
        "identity_id" => 1110,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1111,
        "identity_id" => 1111,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1112,
        "identity_id" => 1112,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1113,
        "identity_id" => 1113,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1114,
        "identity_id" => 1114,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1115,
        "identity_id" => 1115,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1116,
        "identity_id" => 1116,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1117,
        "identity_id" => 1117,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1118,
        "identity_id" => 1118,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1119,
        "identity_id" => 1119,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1120,
        "identity_id" => 1120,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1121,
        "identity_id" => 1121,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1122,
        "identity_id" => 1122,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1123,
        "identity_id" => 1123,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1124,
        "identity_id" => 1124,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1125,
        "identity_id" => 1125,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1126,
        "identity_id" => 1126,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1127,
        "identity_id" => 1127,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1128,
        "identity_id" => 1128,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1129,
        "identity_id" => 1129,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1130,
        "identity_id" => 1130,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1131,
        "identity_id" => 1131,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1132,
        "identity_id" => 1132,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1133,
        "identity_id" => 1133,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1134,
        "identity_id" => 1134,
        "is_active" => true
      ]);
Supplier::create([
        "id" => 1135,
        "identity_id" => 1135,
        "is_active" => true
]);

$orm->enableEvents();

// sync values from Identities to Suppliers
Supplier::search()->do('sync_from_identity');