<?php

use Claramente\Options\Services\OptionTypes;
use Claramente\Options\Validator\RequestAddRowValidator;

header('Content-Type: application/json');

try {
    // Валидация
    $validator = new RequestAddRowValidator();
    $validator->checkSession();
    $fields = $validator->validate();

    $typeClass = OptionTypes::getOptionTypeClass($fields['option_type']);
    $result = $typeClass->getOptionLangRow($fields['id'] . '[value]', $fields['id'] . '[lang]', ['value' => '', 'lang' => '']);

    echo json_encode([
        "success" => true,
        "result"  => $result
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "errors"  => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}