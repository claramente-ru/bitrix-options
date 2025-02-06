<?php

global $USER;

use Bitrix\Main\Application;
use Claramente\Options\Entity\ClaramenteOptionsTable;
use Claramente\Options\Services\OptionTypes;

if (! $USER->IsAdmin()) {
    return;
}

$request = Application::getInstance()->getContext()->getRequest();

$option = $request->getPost('option');
$currentOption = null;

// Текущая опция по id
if ($request->get('ID')) {
    $currentOption = ClaramenteOptionsTable::getOptionById($request->get('ID'));
}
// Редактирование опции
$isNewOption = $currentOption === null;

// При смене кода нужно убедиться, что такого кода нет
if (! $isNewOption
    && $option['code'] !== $currentOption->code
    && ClaramenteOptionsTable::getByCode($option['code'], $option['site_id'])
) {
    CAdminMessage::ShowMessage(sprintf('Ошибка. Код опции %s уже существует', $option['code']));
    return;
} elseif ($isNewOption && ClaramenteOptionsTable::getByCode($option['code'], $option['site_id'])) {
    CAdminMessage::ShowMessage(sprintf('Ошибка. Код опции %s уже существует', $option['code']));
    return;
}

// Тип поля опции
$optionType = OptionTypes::getOptionTypeClass((string)$option['type']);
if (null === $optionType) {
    CAdminMessage::ShowMessage('Ошибка. Свойство опции не найдено');
    return;
}

$data = [
    'NAME' => $option['name'] ?? 'Без имени',
    'CODE' => $option['code'],
    'TYPE' => $option['type'],
    'SITE_ID' => ! empty($option['site_id']) ? $option['site_id'] : null,
    'SORT' => $option['sort'] ?? 100,
    'TAB_ID' => isset($option['tab_id']) && intval($option['tab_id']) > 0 ? $option['tab_id'] : null,
];
// Редактирование опции
if (! $isNewOption) {
    $save = ClaramenteOptionsTable::update($currentOption->id, $data);
} else {
    // Добавление новое опции
    $save = ClaramenteOptionsTable::add($data);
}

if (! $save->isSuccess()) {
    CAdminMessage::ShowMessage('Ошибка ' . implode(',', $save->getErrorMessages()));
    return;
}

// Обновим дополнительные настройки типа данных
ClaramenteOptionsTable::update(
    $save->getId(),
    [
        'settings' => $optionType->beforeSaveSettings($request, ClaramenteOptionsTable::getOptionById($save->getId()))
    ]
);

// Редирект относительно нажатой кнопки
if ($request->getPost('apply')) {
   LocalRedirect('/bitrix/admin/claramente_options.php?lang=' . LANG . '&page=option&ID=' . $save->getId());
} else {
    // Редирект на страницу опций
    LocalRedirect('/bitrix/admin/claramente_options.php?lang=' . LANG);
}
