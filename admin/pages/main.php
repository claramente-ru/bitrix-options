<?php

use Bitrix\Main\Request;
use Claramente\Options\Admin\AdminForm;
use Claramente\Options\Entity\ClaramenteOptionTabsTable;
use Claramente\Options\Types\StringOption;

/**
 * @var Request $request
 */

// Сохранение формы
if ($request->isPost()) {
    require_once __DIR__ . '/../save/main.php';
}

// Главная страница модуля
$form = new AdminForm();
$tabControl = $form->getForm('claramente_options_form', $form->getFormTabs());
$tabControl->SetShowSettings(false);
$tabControl->Begin([
    'FORM_ACTION' => $request->getRequestUri()
]);
// Проходимся по всем tabs
foreach ($form->getFormTabs() as $formTab) {
    $tabControl->BeginNextFormTab();

    // Вкладка - Параметры (содержит опции которые не привязаны к вкладкам)
    if ($formTab['ID'] || 'options' === $formTab['DIV']) {
        // Получить все опции вкладки
        foreach ($form->getTabOptions($formTab['ID']) as $option) {
            // Свойство опции
            $optionType = $option->getOptionType();
            if (null === $optionType) {
                $optionType = new StringOption();
            }

            // Форма редактирования опции
            $optionId = sprintf('options[%d]', $option->id);
            $optionType->getFormFieldValue(
                $optionId,
                $tabControl,
                $option
            );
        }
    }

    // Вкладка - Вкладки (tabs)
    if ('tabs' === $formTab['DIV']) {
        foreach ($form->getFormTabs(false) as $i => $tabField) {
            // Информация из сущности вкладок
            $tabEntity = ClaramenteOptionTabsTable::getTabById($tabField['ID']);
            if (! $tabEntity) {
                // Ошибка. Не нашли вкладку в базе данных
                continue;
            }

            // Визуальное разделение
            $sectionName = sprintf('Вкладка: %s', $tabField['TITLE']);
            $tabControl->AddSection('tab-edit-' . $tabField['DIV'], $sectionName);

            $tabControl->AddEditField($tabField['DIV'] . '[name]', '📝 Заголовок', true, [], $tabEntity->name);
            $tabControl->AddEditField($tabField['DIV'] . '[code]', '🔤 Символьный код', true, [], $tabEntity->code);
            $tabControl->AddEditField($tabField['DIV'] . '[sort]', '🔝️ Сортировка', false, [], $tabEntity->sort);
            $tabControl->AddCheckBoxField($tabField['DIV'] . '[del]', '❌ Удалить', false, ['Y', 'N'], false);
        }
        // Добавить новый tab
        $tabControl->AddSection('tab-add', '📥 Добавить новую вкладку');
        $tabControl->AddEditField('tab_add[name]', '📝 Заголовок', false, [], '');
        $tabControl->AddEditField('tab_add[code]', '🔤 Символьный код', false, [], '');
        $tabControl->AddEditField('tab_add[sort]', '🔝️ Сортировка', false, [], 100);
    }

    // Вкладка о нас
    if ('about' === $formTab['DIV']) {
        $tabControl->AddViewField(
            'about-license',
            '⚖️ Лицензия',
            '<a target="_blank" href="https://github.com/claramente-ru/bitrix-options/blob/master/LICENSE">MIT</a>'
        );
        $tabControl->AddViewField(
            'about-git',
            '𝗚𝐈𝗧️ GitHub',
            '<a target="_blank" href="https://github.com/claramente-ru/bitrix-options">https://github.com/claramente-ru/bitrix-options</a>'
        );
        $tabControl->AddViewField(
            'about-packagist',
            '🐘️ Packagist',
            '<a target="_blank" href="https://packagist.org/packages/claramente/claramente.options">https://packagist.org/packages/claramente/claramente.options</a>'
        );
        $tabControl->AddViewField(
            'about-developer',
            '⚒️ Разработчик',
            '<a target="_blank" href="https://claramente.ru">© Светлые головы</a>'
        );
    }
}

// Кнопка добавить новый параметр
$buttonAddNewParameter = '<a href="/bitrix/admin/claramente_options.php?lang=' . LANG . '&page=option"><input type="button" value="Добавить параметр" title="Добавить новый параметр" class="adm-btn-add"></a>';
$tabControl->Buttons(
    [
        'disabled' => false,
        'btnApply' => false,
    ],
    $buttonAddNewParameter
);

$tabControl->Show();
include_once __DIR__ . '/../include/info.php';
?>
<script>
    document.addEventListener("DOMContentLoaded", function () {
        document.querySelectorAll("tr[id^='tr_options']").forEach(function (input) {
            if (input.getAttribute("type") === "hidden") {
                return;
            }
            let tdName = input.querySelector("td")
            let optionId = input.id.match(/\[(.*?)\]/)[1];
            let editLink = document.createElement("a");
            editLink.textContent = "✏️";
            editLink.href = "/bitrix/admin/claramente_options.php?lang=<?= LANG?>&page=option&ID=" + optionId;
            editLink.style.marginLeft = "10px";
            editLink.style.textDecoration = "none";

            tdName.append(editLink);
        });
    });
</script>