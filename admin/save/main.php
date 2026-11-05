<?php

global $USER;

use Bitrix\Main\Application;
use Claramente\Options\Entity\ClaramenteOptionsTable;
use Claramente\Options\Entity\ClaramenteOptionTabsTable;

// Значения опций сохраняют все пользователи модуля;
// опции «только для администраторов» и вкладки меняет только администратор

if (! check_bitrix_sessid()) {
    CAdminMessage::ShowMessage('Ошибка. Сессия истекла, обновите страницу и повторите сохранение');
    return;
}

$request = Application::getInstance()->getContext()->getRequest();

/**
 * Добавление новой вкладки
 */
// Вкладки, как и сами опции, создают, изменяют и удаляют только администраторы
$newTab = $USER->IsAdmin() ? $request->getPost('tab_add') : null;
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
$tabs = $USER->IsAdmin() ? $request->getPost('tabs') : null;
if (is_array($tabs) && $tabs) {
    foreach ($tabs as $tabId => $tab) {
        // Необходимо удалить вкладку
        if (isset($tab['del']) && $tab['del'] === 'Y') {
            ClaramenteOptionTabsTable::delete($tabId);
            continue;
        }

        $currentTab = ClaramenteOptionTabsTable::getTabById((int)$tabId);
        if (null === $currentTab) {
            continue;
        }

        // Проверка уникальности кода вкладки: код занят другой вкладкой
        $checkTab = ClaramenteOptionTabsTable::getByCode((string)($tab['code'] ?? ''));
        if (null !== $checkTab && $checkTab->id !== $currentTab->id) {
            CAdminMessage::ShowMessage(sprintf('Ошибка. Вкладка с кодом %s уже существует', $tab['code']));
            return;
        }

        // Обновим вкладку
        $updateTab = ClaramenteOptionTabsTable::update(
            $tabId,
            [
                'NAME' => $tab['name'] ?? 'Без имени',
                'SORT' => intval($tab['sort'] ?? 0) ?: 100,
                'CODE' => $tab['code']
            ]
        );
        if (! $updateTab->isSuccess()) {
            CAdminMessage::ShowMessage('Ошибка. ' . implode(',', $updateTab->getErrorMessages()));
            return;
        }
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
        if (is_array($fileOptionValue) && is_array(current($fileOptionValue))) {
            // Для множественного значения, обрабатываем каждый элемент
            foreach ($fileOptionValue as $index => $nestedValue) {
                if (! isset($options[$fileOptionId][$index]['lang'])) {
                    continue;
                }
                $options[$fileOptionId][$index]['value'][$fileOptionParameterName] =
                    $nestedValue['value'] ?? null;
            }
        } else {
            // Это одиночное значение
            if (! isset($options[$fileOptionId])) {
                $options[$fileOptionId] = [];
            }
            $options[$fileOptionId][$fileOptionParameterName] = $fileOptionValue['value'] ?? null;
        }
    }
}

// Удаление файлов
foreach ((array)$request->getPost('options_del') as $optionId => $value) {
    if (is_array($value) && is_array(current($value))) {
        // Для множественного значения, обрабатываем каждый элемент
        foreach ($value as $index => $nestedValue) {
            $options[$optionId][$index]['value']['del'] = $nestedValue['value'] ?? null;
        }
    } else {
        $options[$optionId]['del'] = $value['value'] ?? '';
    }
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