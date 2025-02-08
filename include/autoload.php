<?php

use Bitrix\Main\Loader;

Loader::registerAutoLoadClasses(
    'claramente.options',
    [
        'ClaramenteModuleAdminOptions' => 'classes/general/claramentemoduleadminoptions.php',
    ]
);
