<?php



// override core settings

use core\setting\Setting;

Setting::set_value('core', 'locale', 'currency', '€');
Setting::set_value('core', 'locale', 'numbers.decimal_separator', ',');
Setting::set_value('core', 'locale', 'numbers.thousands_separator', '.');