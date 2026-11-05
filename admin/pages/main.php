<?php

use Bitrix\Main\Request;
use Claramente\Options\Admin\AdminForm;
use Claramente\Options\Entity\ClaramenteOptionTabsTable;
use Claramente\Options\Types\StringOption;

/**
 * @var Request $request
 * @var CUser $USER
 */

// Сохранение формы
if ($request->isPost()) {
    require_once __DIR__ . '/../save/main.php';
}

// Главная страница модуля
$form = new AdminForm();
// Вкладка настройки вкладок доступна только администраторам
$formTabs = array_values(array_filter(
    $form->getFormTabs(),
    static fn(array $formTab): bool => 'tabs' !== $formTab['DIV'] || $USER->IsAdmin()
));
$tabControl = $form->getForm('claramente_options_form', $formTabs);
$tabControl->SetShowSettings(false);
// Защита от CSRF: токен сессии проверяется при сохранении
$tabControl->BeginEpilogContent();
echo bitrix_sessid_post();
$tabControl->EndEpilogContent();
$tabControl->Begin([
    'FORM_ACTION' => $request->getRequestUri()
]);
// Проходимся по всем tabs
foreach ($formTabs as $formTab) {
    $tabControl->BeginNextFormTab();

    // Вкладка - Параметры (содержит опции которые не привязаны к вкладкам)
    if ($formTab['ID'] || 'options' === $formTab['DIV']) {
        // Получить все опции вкладки
        foreach ($form->getTabOptions($formTab['ID']) as $option) {
            // Скрываем опции доступные только администраторам
            if ($option->isAdminOnly && ! $USER->IsAdmin()) {
                continue;
            }

            // Свойство опции
            $optionType = $option->getOptionType();
            if (null === $optionType) {
                $optionType = new StringOption();
            }

            // Форма редактирования опции
            $optionId = sprintf('options[%d]', $option->id);
            if ($option->singleValueForAllLang) {
                // Одно значение опции для всех языков
                $optionType->getFormFieldValue(
                    $optionId,
                    $tabControl,
                    $option
                );
            } else {
                // Множественное значение опции для каждого языка
                $optionType->getFormFieldMultipleValue(
                    $optionId,
                    $tabControl,
                    $option
                );
            }
            $optionType->getFormFieldHint(
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

// Кнопка добавить новый параметр (для администраторов)
$buttonAddNewParameter = '';
if ($USER->IsAdmin()) {
    $buttonAddNewParameter = '<a href="/bitrix/admin/claramente_options.php?lang=' . LANG . '&page=option"><input type="button" value="Добавить параметр" title="Добавить новый параметр" class="adm-btn-add"></a>';
}
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
<?php if ($USER->IsAdmin()) { ?>
    document.addEventListener("DOMContentLoaded", function () {
        // Редактирование опций доступно только администраторам
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
<?php } ?>

    // Добавление строки значения для языка (доступно всем пользователям модуля)
    function addOptionRow(tableId, regexp) {
        let table = document.getElementById(tableId)
        if (! table) return;
        const option_type = table.getAttribute('data-option-type')
        if (! option_type) return;
        const rowsCount = table.rows.length
        let html = table.rows[rowsCount - 2].cells[0].innerHTML
        const regExpName = new RegExp('(' + regexp + ')\\[(\\d*)\\]\\[(value|lang)\\]', 'gi');
        html = html.match(regExpName)
        if (! html.length) return;
        const indexMatch = html[0].match(/\[(\d+)\]/gi);
        const foundIndex = indexMatch[indexMatch.length - 1].replace(/\[|\]/g, "");
        const name = html[0].replace(regExpName, '$1[' + (parseInt(foundIndex) + 1) + ']');
        BX.ajax({
            url: '/bitrix/admin/claramente_options.php?action=addrow',
            method: 'POST',
            dataType: 'json',
            data: {
                'sessid': BX.bitrix_sessid(),
                'id': name,
                'option_type': option_type,
            },
            onsuccess: function (response) {
                var newHtml;
                if (response.success && response.result) {
                    const oRow = table.insertRow(rowsCount - 1);
                    // Извлекаем скрипты для календаря
                    if (response.result.includes('<script>') && option_type === 'date') {
                        // Извлекаем скрипт
                        const scriptMatch = response.result.match(/<script>([\s\S]*?)<\/script>/);
                        if (scriptMatch) {
                            const scriptContent = scriptMatch[1];

                            // Удаляем скрипт из HTML
                            newHtml = response.result.replace(/<script>[\s\S]*?<\/script>/, '');

                            // Вставляем HTML в новую строку таблицы
                            oRow.innerHTML = newHtml;

                            // Выполняем скрипт с задержкой
                            setTimeout(() => {
                                executeOriginalScript(scriptContent);
                            }, 0);
                        } else {
                            oRow.innerHTML = response.result;
                        }
                    } else {
                        oRow.innerHTML = response.result;
                    }
                    setTimeout(() => {
                        BX.adminFormTools.modifyFormElements(oRow);
                    }, 0);
                }
            },
            onerror: function (response) {
                console.log(response.error)
            }
        })
    }

    function executeOriginalScript(scriptContent) {
        // Создаем новый элемент script
        const script = document.createElement('script');

        // Вставляем код скрипта
        script.textContent = scriptContent;

        // Добавляем в head для выполнения
        document.head.appendChild(script);

        // Удаляем после выполнения
        setTimeout(() => {
            if (script.parentNode) {
                document.head.removeChild(script);
            }
        }, 0);

    }
</script>