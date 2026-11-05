<?php

global $USER;

use Bitrix\Main\Application;
use Bitrix\Main\Type\DateTime;
use Claramente\Options\Entity\ClaramenteOptionsTable;
use Claramente\Options\Services\OptionTypes;
use Claramente\Options\Types\AbstractOption;

if (! $USER->IsAdmin()) {
    return;
}

if (! check_bitrix_sessid()) {
    CAdminMessage::ShowMessage('Ошибка. Сессия истекла, обновите страницу и повторите сохранение');
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

// «Не выбрано» в списке сайтов приходит пустой строкой, а в базе опция для всех сайтов хранится с SITE_ID = NULL
$optionCode = trim((string)($option['code'] ?? ''));
$optionSiteId = ! empty($option['site_id']) ? (string)$option['site_id'] : null;
if ($optionCode === '') {
    CAdminMessage::ShowMessage('Ошибка. Не указан код параметра');
    return;
}

// При смене кода или сайта нужно убедиться, что такой пары код + сайт нет
$codeOrSiteChanged = $isNewOption
    || $optionCode !== $currentOption->code
    || $optionSiteId !== $currentOption->siteId;
if ($codeOrSiteChanged && ClaramenteOptionsTable::getByCode($optionCode, $optionSiteId)) {
    CAdminMessage::ShowMessage(sprintf('Ошибка. Код опции %s уже существует', $optionCode));
    return;
}

// Тип поля опции
$optionType = OptionTypes::getOptionTypeClass((string)$option['type']);
if (null === $optionType) {
    CAdminMessage::ShowMessage('Ошибка. Свойство опции не найдено');
    return;
}

// Подсказка
$hint = null;
if (array_key_exists('hint', $option)) {
    $hint = is_string($option['hint']) ? $option['hint'] : '';
    if (mb_strlen($hint) > AbstractOption::HINT_MAX_LENGTH) {
        CAdminMessage::ShowMessage(sprintf(
            'Ошибка. Подсказка длиннее %d символов',
            AbstractOption::HINT_MAX_LENGTH
        ));
        return;
    }
}

$forAllLangNewValue = isset($option['single_value_for_all_lang']) && $option['single_value_for_all_lang'] === 'Y';
$data = [
    'NAME' => $option['name'] ?? 'Без имени',
    'CODE' => $optionCode,
    'TYPE' => $option['type'],
    'SITE_ID' => $optionSiteId,
    'SORT' => $option['sort'] ?? 100,
    'TAB_ID' => isset($option['tab_id']) && intval($option['tab_id']) > 0 ? $option['tab_id'] : null,
    'IS_ADMIN_ONLY' => isset($option['is_admin_only']) && $option['is_admin_only'] === 'Y',
    'SINGLE_VALUE_FOR_ALL_LANG' => $forAllLangNewValue,
    // В PostgreSQL нет ON UPDATE CURRENT_TIMESTAMP, поэтому время изменения задается явно
    'UPDATED_AT' => new DateTime(),
];

// Редактирование опции
if (! $isNewOption) {
    $save = ClaramenteOptionsTable::update($currentOption->id, $data);
} else {
    // Добавление новое опции
    $save = ClaramenteOptionsTable::add($data);
}
// Ниже опция читается заново, поэтому предзагруженные данные сбрасываем
ClaramenteOptionsTable::clearPreloader();

if (! $save->isSuccess()) {
    CAdminMessage::ShowMessage('Ошибка ' . implode(',', $save->getErrorMessages()));
    return;
}

// Добавим в данные для обновления дополнительные настройки типа данных
$toUpdate['settings'] = $optionType->beforeSaveSettings(
    $request,
    ClaramenteOptionsTable::getOptionById($save->getId())
);
if (null !== $hint || $currentOption !== null) {
    $settings = unserialize(
        $toUpdate['settings'] ?: 'a:0:{}',
        [
            'allowed_classes' => false,
        ]
    );
    $settings = is_array($settings) ? $settings : [];
    unset($settings['hint']);
    // Если поле подсказки не передано (собственная форма без поля), сохраним прежнюю подсказку
    $hintToSave = $hint ?? ($currentOption?->getHint() ?? '');
    if (trim($hintToSave) !== '') {
        $settings['hint'] = $hintToSave;
    }
    $toUpdate['settings'] = serialize($settings);
}

// Если есть данные и поменялся тип хранения с единственного на множественное (SINGLE_VALUE_FOR_ALL_LANG = N) или обратно, то
// необходимо изменить представление данных в базе с единичного значения на json или наоборот
if ($currentOption !== null
    && $currentOption->value
    && $currentOption->singleValueForAllLang !== $forAllLangNewValue
) {
    if ($forAllLangNewValue) {
        $value = unserialize($currentOption->value, ['allowed_classes' => false]);
        if (is_array($value) && isset($value[0]['value'])) {
            $toUpdate['value'] = $value[0]['value'];
        }
    } else {
        $toUpdate['value'] = serialize([
            [
                'value' => $currentOption->value,
                'lang'  => LANG
            ]
        ]);
    }
}

ClaramenteOptionsTable::update(
    $save->getId(),
    $toUpdate
);

// Редирект относительно нажатой кнопки
if ($request->getPost('apply')) {
    LocalRedirect('/bitrix/admin/claramente_options.php?lang=' . LANG . '&page=option&ID=' . $save->getId());
} else {
    // Редирект на страницу опций
    LocalRedirect('/bitrix/admin/claramente_options.php?lang=' . LANG);
}
