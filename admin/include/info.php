<div class="adm-info-message-wrap">
    <div class="adm-info-message adm-info-blue">
        <div>Глобальные методы использования модуля:</div>
        <div><strong>Получение значения опции для текущего языка:</strong> cm_option(code, site_id)</div>
        <div><strong>Получение значения опции для всех языков:</strong> cm_option_all_lang(code, site_id)</div>
        <div><strong>Установка нового значения опции:</strong> cm_option_set(code, site_id, value)</div>
        <div><strong>Проверка существования опции для текущего языка:</strong> cm_option_exists(code, site_id)</div>
        <div><strong>Проверка заполнения опции для текущего языка:</strong> cm_option_filled(code, site_id)</div>
        <div><strong>Удаление опции:</strong> cm_option_delete(code, site_id)</div>
    </div>
</div>

<div class="adm-info-message-wrap">
    <div class="adm-info-message adm-info-blue">
        <div><strong>Тип «Число»</strong> — целое или десятичное число без знака: допускаются только цифры и разделитель</div>
        <div>Разделитель дробной части при вводе — точка или запятая, хранится точка: 12,50 сохраняется как 12.5</div>
        <div>Точность (знаков после разделителя) задается в настройках параметра, 0 — только целые числа</div>
        <div>Округления нет: значение с лишними знаками после разделителя не сохраняется, выводится ошибка</div>
        <div>Любое другое значение (знаки «+» и «-», пробелы и разделители тысяч внутри числа, 1e3) не сохраняется с ошибкой «Значение должно быть числом»</div>
        <div>Не более 15 значащих цифр в целой и дробной части вместе</div>
        <div>cm_option() возвращает int для целых и float для дробных чисел (12.5)</div>
        <div>Для вывода с нужным количеством знаков используйте number_format(), например number_format(cm_option('price'), 2, ',', ' ')</div>
    </div>
</div>

<style>
    .adm-info-blue {
        color: #004085 !important;
        background: #cfe2ff !important;
        border-color: #b8daff !important;
    }
</style>