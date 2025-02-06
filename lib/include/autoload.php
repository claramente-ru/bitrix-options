<?php

use Bitrix\Main\Loader;

Loader::registerAutoLoadClasses(
    'claramente.options',
    [
        'ClaramenteModuleAdminOptions' => 'classes/general/claramentemoduleadminoptions.php',
    ]
);

// Типы опций
$optionClasses = [
    'Claramente\Options\Types\StringOption' => 'lib/types/stringoption.php',
    'Claramente\Options\Types\StringsOption' => 'lib/types/stringsoption.php',
    'Claramente\Options\Types\BoolOption' => 'lib/types/booloption.php',
    'Claramente\Options\Types\DateOption' => 'lib/types/dateoption.php',
    'Claramente\Options\Types\SelectOption' => 'lib/types/selectoption.php',
    'Claramente\Options\Types\TextOption' => 'lib/types/textoption.php',
    'Claramente\Options\Types\FileOption' => 'lib/types/fileoption.php',
];

// Загрузка типа данных опций
$declaredClass = get_declared_classes();
foreach ($optionClasses as $class => $path) {
    $path = __DIR__ . '/../' . $path;
    if (! in_array($class, $declaredClass) && file_exists($path)) {
        require_once $path;
    }
}