<?php

global $USER;

use Bitrix\Main\Application;
use Claramente\Options\Entity\ClaramenteOptionsTable;
use Claramente\Options\Entity\ClaramenteOptionTabsTable;

if (! $USER->IsAdmin()) {
    return;
}

$request = Application::getInstance()->getContext()->getRequest();

/**
 * Добавление новой вкладки
 */
$newTab = $request->getPost('tab_add');
if (! empty($newTab['name']) && ! empty($newTab['code'])) {
    // Проверяем занятость кода
    if (ClaramenteOptionTabsTable::getByCode($newTab['code'])) {
        CAdminMessage::ShowMessage('Ошибка. Вкладка с таким кодом уже существует');
        return;
    }
    // Добавляем новую вкладку
    $tab = ClaramenteOptionTabsTable::add(
        [
            'NAME' => $newTab['name'],
            'SORT' => intval($newTab['sort'] ?? 0) ?: 100,
            'CODE' => $newTab['code']
        ]
    );
    if (! $tab->isSuccess()) {
        CAdminMessage::ShowMessage('Ошибка. ' . implode(',', $tab->getErrorMessages()));
        return;
    }
}

/**
 * Обработка вкладок
 */
$tabs = $request->getPost('tabs');
if (is_array($tabs) && $tabs) {
    foreach ($tabs as $tabId => $tab) {
        // Необходимо удалить вкладку
        if (isset($tab['del']) && $tab['del'] === 'Y') {
            ClaramenteOptionTabsTable::delete($tabId);
            continue;
        }

        // Проверка уникальности кода вкладки
        $checkTab = ClaramenteOptionTabsTable::getByCode($tab['code']);
        $currentTab = ClaramenteOptionTabsTable::getTabById((int)$tabId);
        if ($currentTab->code != $tab['code'] && null !== $currentTab) {
            CAdminMessage::ShowMessage('Ошибка. Вкладка с таким кодом уже существует');
        }

        // Обновим вкладку
        $updateTab = ClaramenteOptionTabsTable::update(
            $tabId,
            [
                'NAME' => $tab['name'] ?? 'Без измени',
                'SORT' => intval($tab['sort'] ?? 0) ?: 100,
                'CODE' => $tab['code']
            ]
        );
    }
}

/**
 * Обновление опций
 */

// Строковые опции
$options = (array)$request->getPost('options');
// Загружаемые файлы
foreach ((array)$request->getFile('options') as $fileOptionParameterName => $fileOptionValues) {
    /**
     * @var array $fileOptionValues
     */
    foreach ($fileOptionValues as $fileOptionId => $fileOptionValue) {
        if (! isset($options[$fileOptionId])) {
            $options[$fileOptionId] = [];
        }
        $options[$fileOptionId][$fileOptionParameterName] = $fileOptionValue['value'] ?? null;
    }
}
// Удаление файлов
foreach ((array)$request->getPost('options_del') as $optionId => $value) {
    $options[$optionId]['del'] = $value['value'] ?? '';
}
foreach ($options as $optionId => $optionData) {
    // Заберем опцию по коду
    $option = ClaramenteOptionsTable::getOptionById((int)$optionId);
    if (! $option) {
        CAdminMessage::ShowMessage(sprintf('Ошибка. Опция %d не найдена', $optionId));
        return;
    }
    // Опция доступна только администраторам
    if ($option->isAdminOnly && ! $USER->IsAdmin()) {
        CAdminMessage::ShowMessage('Опция доступна только администраторам');
        return;
    }
    // Преобразуем value перед сохранением
    $optionType = $option->getOptionType();
    if (null === $optionType) {
        CAdminMessage::ShowMessage(sprintf('Ошибка. Свойство %s не найдено', $option->type));
        return;
    }
    // Сохраним изменения
    $option->setValue($optionData);
}

// Редирект на страницу опций
LocalRedirect('/bitrix/admin/claramente_options.php?lang=' . LANG);