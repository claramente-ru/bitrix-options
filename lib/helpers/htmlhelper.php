<?php
declare(strict_types=1);

namespace Claramente\Options\Helpers;

use CBXSanitizer;

/**
 * Очистка HTML в значениях текстовых опций
 */
final class HtmlHelper
{
    /**
     * Теги, которые удаляются вместе с содержимым
     */
    private const DELETE_WITH_CONTENT = ['script', 'style', 'iframe', 'object', 'embed', 'noscript'];

    /**
     * Атрибуты ссылки: набор SECURE_LEVEL_LOW и rel (noreferrer, nofollow, noopener)
     */
    private const LINK_ATTRIBUTES = ['href', 'title', 'name', 'style', 'id', 'class', 'shape', 'coords', 'alt', 'target', 'rel'];

    /**
     * Безопасный HTML через CBXSanitizer низкого уровня (SECURE_LEVEL_LOW): форматирование, div, span, ссылки,
     * изображения, таблицы, атрибуты style, class, id; у ссылок дополнительно rel
     * Теги вне белого списка удаляются, script, style, iframe, object, embed, noscript — вместе с содержимым
     * Текст между тегами кодируется (& -> &amp;, " -> &quot;), уже закодированное повторно не кодируется
     * Строки без «<» не меняются
     * В поле формы значение выводится через @see self::decodeForForm()
     * @param string $value
     * @return string
     */
    public static function sanitize(string $value): string
    {
        if (! str_contains($value, '<')) {
            return $value;
        }
        // «<» без буквы, «/», «!» или «?» после него — это текст («a < b > c»), браузер его тегом не считает,
        // но CBXSanitizer принял бы «< b >» за тег и удалил бы его
        $placeholder = '%CM_LT_' . bin2hex(random_bytes(8)) . '%';
        $value = preg_replace('~<(?![a-zA-Z/!?])~', $placeholder, $value);

        $sanitizer = new CBXSanitizer();
        $sanitizer->SetLevel(CBXSanitizer::SECURE_LEVEL_LOW);
        $sanitizer->UpdateTags(['a' => self::LINK_ATTRIBUTES]);
        $sanitizer->ApplyDoubleEncode(false);
        $sanitizer->DeleteSanitizedTags(true);
        $sanitizer->setDelTagsWithContent(self::DELETE_WITH_CONTENT);

        $value = $sanitizer->SanitizeHtml($value);

        // «<» в конце значения склеился бы в шаблоне со следующим значением в тег («Цена <» + «img onerror=…>»)
        return str_replace($placeholder, '<', preg_replace('~' . preg_quote($placeholder, '~') . '$~', '&lt;', $value));
    }

    /**
     * Значение для поля формы: в значениях с HTML раскодируются &amp;, &quot;, &#039;, &lt;, &gt;,
     * чтобы редактор видел свой текст; при сохранении @see self::sanitize() закодирует их снова так же
     * Значения без HTML не меняются: текст «&lt;b&gt;» должен остаться текстом
     * @param string|null $value
     * @return string|null
     */
    public static function decodeForForm(?string $value): ?string
    {
        return null !== $value && str_contains($value, '<') ? htmlspecialchars_decode($value, ENT_QUOTES) : $value;
    }
}
