<?php
use core\setting\Setting;


// override core settings
Setting::set_value('core', 'locale', 'currency', '€');
Setting::set_value('core', 'locale', 'numbers.decimal_separator', ',');
Setting::set_value('core', 'locale', 'numbers.thousands_separator', '.');