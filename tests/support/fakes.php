<?php

declare(strict_types=1);

/**
 * Заглушки Bitrix API и таблицы опций для офлайн-проверок
 */

namespace Bitrix\Main {
    class HttpRequest
    {
        public function __construct(public array $post = [], public array $query = [])
        {
        }

        public function isPost(): bool
        {
            return false;
        }

        public function getRequestUri(): string
        {
            return '/';
        }

        public function getPost($name)
        {
            return $this->post[$name] ?? null;
        }

        public function get($name)
        {
            return $this->query[$name] ?? null;
        }

        public function getFile($name)
        {
            return null;
        }

        public function getPostList(): object
        {
            return new class($this->post) {
                public function __construct(private array $values)
                {
                }

                public function toArray(): array
                {
                    return $this->values;
                }
            };
        }

        public function getQueryList(): object
        {
            return new class($this->query) {
                public function __construct(private array $values)
                {
                }

                public function toArray(): array
                {
                    return $this->values;
                }
            };
        }
    }

    /**
     * Сайты для SiteHelper
     */
    class SiteTable
    {
        public static array $sites = [];

        public static function query(): object
        {
            return new class {
                public function setSelect(array $select): self
                {
                    return $this;
                }

                public function fetchAll(): array
                {
                    return SiteTable::$sites;
                }
            };
        }
    }

    class Application
    {
        public static HttpRequest $request;

        public static function getInstance(): self
        {
            return new self();
        }

        public function getContext(): self
        {
            return $this;
        }

        public function getRequest(): HttpRequest
        {
            return self::$request;
        }
    }
}

namespace Bitrix\Main {
    /**
     * Запоминает регистрацию и снятие обработчиков событий
     */
    class EventManager
    {
        public static array $registered = [];
        public static array $unregistered = [];

        public static function getInstance(): self
        {
            return new self();
        }

        public function registerEventHandler(...$arguments): void
        {
            self::$registered[] = $arguments;
        }

        public function unRegisterEventHandler(...$arguments): void
        {
            self::$unregistered[] = $arguments;
        }
    }

    class SystemException extends \Exception
    {
    }
}

namespace Bitrix\Main\Localization {
    class LanguageTable
    {
        public static function getList(array $parameters = []): object
        {
            return new class {
                public function fetchAll(): array
                {
                    return [['ID' => 'ru', 'NAME' => 'Русский'], ['ID' => 'en', 'NAME' => 'English']];
                }
            };
        }
    }
}

namespace Bitrix\Main\Type {
    class Date
    {
        public function __construct(public int $timestamp = 0)
        {
        }

        public static function createFromTimestamp(int $timestamp): static
        {
            return new static($timestamp);
        }
    }

    class DateTime
    {
        public function format(string $format): string
        {
            return '00:00:00 01.01.2026';
        }
    }
}

namespace Claramente\Options\Admin {
    use CAdminForm;

    class AdminForm
    {
        public static array $options = [];
        public static ?CAdminForm $form = null;
        /** DIV вкладок, переданных в форму */
        public static array $formTabs = [];

        public function getFormTabs(bool $withTechnicalTabs = true): array
        {
            return $withTechnicalTabs
                ? [['ID' => 0, 'DIV' => 'options'], ['ID' => null, 'DIV' => 'tabs']]
                : [];
        }

        public function getTabOptions($id): array
        {
            return self::$options;
        }

        public function getForm($name, $tabs, $canExpand = true, $denyAutosave = false): CAdminForm
        {
            self::$formTabs = array_column($tabs, 'DIV');
            self::$form = new CAdminForm();

            return self::$form;
        }

        public function collectTab(string $name, string $div): array
        {
            return ['TAB' => $name, 'DIV' => $div];
        }

        public function getSelectTabs(): array
        {
            return [0 => 'Не выбрано'];
        }

        public function getSelectSites(): array
        {
            return ['' => 'Не выбрано'];
        }
    }
}

namespace Bitrix\Main\Entity {
    /**
     * Запросы ORM к строкам в памяти. Нужна для проверки настоящего ClaramenteOptionsTable (tests/cases/cache.php):
     * запоминает фильтр и время кэширования каждого запроса
     */
    class DataManager
    {
        /** Строки таблицы */
        public static array $rows = [];

        /** Выполненные запросы: ['filter' => ..., 'ttl' => ...] */
        public static array $queries = [];

        public static function query(): object
        {
            return new class {
                private array $filter = [];
                private int $ttl = 0;

                public function setFilter(array $filter): self
                {
                    $this->filter = $filter;

                    return $this;
                }

                public function setSelect(array $select): self
                {
                    return $this;
                }

                public function setCacheTtl(int $ttl): self
                {
                    $this->ttl = $ttl;

                    return $this;
                }

                public function fetchAll(): array
                {
                    DataManager::$queries[] = ['filter' => $this->filter, 'ttl' => $this->ttl];

                    return array_values(array_filter(DataManager::$rows, function (array $row): bool {
                        foreach ($this->filter as $field => $value) {
                            if ($row[$field] !== $value) {
                                return false;
                            }
                        }

                        return true;
                    }));
                }

                public function fetch(): array|false
                {
                    return $this->fetchAll()[0] ?? false;
                }
            };
        }
    }
}

namespace Claramente\Options\Entity {
    use Claramente\Options\Structures\Entity\OptionEntityStructure;
    use Claramente\Options\Structures\Entity\TabEntityStructure;

    use function fakeResult;
    use function fixture;

    /**
     * Таблица опций в памяти
     */
    class ClaramenteOptionsTable
    {
        /** @var OptionEntityStructure[] */
        public static array $options = [];

        /** Ошибка, которую вернет следующая операция записи */
        public static ?string $failNext = null;

        /** Сколько раз сбрасывались данные предзагрузчика */
        public static int $preloaderResets = 0;

        public static function clearPreloader(): void
        {
            self::$preloaderResets++;
        }

        private const FIELDS = [
            'NAME' => 'name', 'CODE' => 'code', 'VALUE' => 'value', 'TYPE' => 'type', 'SETTINGS' => 'settings',
            'SORT' => 'sort', 'TAB_ID' => 'tabId', 'SITE_ID' => 'siteId',
            'SINGLE_VALUE_FOR_ALL_LANG' => 'singleValueForAllLang', 'IS_ADMIN_ONLY' => 'isAdminOnly',
            'UPDATED_AT' => 'updatedAt',
        ];

        public static function getOptionById($id)
        {
            return isset(self::$options[(int)$id]) ? clone self::$options[(int)$id] : null;
        }

        public static function getByCode($code, $siteId = null, $cacheTtl = null, $getForAllLanguages = false)
        {
            foreach (self::$options as $option) {
                if ($option->code === $code && $option->siteId === $siteId) {
                    $option = clone $option;
                    // Как в настоящей таблице: без $getForAllLanguages значение по языкам заменяется значением текущего языка
                    if (null !== $option->value && ! $option->singleValueForAllLang && ! $getForAllLanguages) {
                        $values = @unserialize($option->value, ['allowed_classes' => false]);
                        $option->value = is_array($values) ? (string)(array_column($values, 'value', 'lang')[LANGUAGE_ID] ?? '') : '';
                    }

                    return $option;
                }
            }

            return null;
        }

        public static function getByTabId(int $tabId, ?string $siteId = null): array
        {
            return array_values(array_filter(
                self::$options,
                static fn(OptionEntityStructure $option): bool => $option->tabId === $tabId
                    && (null === $siteId || $option->siteId === $siteId)
            ));
        }

        public static function add($data)
        {
            if ($error = self::takeFailure()) {
                return fakeResult(false, 0, [$error]);
            }
            $id = self::$options ? max(array_keys(self::$options)) + 1 : 1;
            self::$options[$id] = fixture();
            self::$options[$id]->id = $id;
            self::$options[$id]->code = '';
            self::apply($id, $data);

            return fakeResult(true, $id);
        }

        public static function update($id, $data)
        {
            if ($error = self::takeFailure()) {
                return fakeResult(false, (int)$id, [$error]);
            }
            self::apply((int)$id, $data);

            return fakeResult(true, (int)$id);
        }

        public static function delete($id)
        {
            if ($error = self::takeFailure()) {
                return fakeResult(false, (int)$id, [$error]);
            }
            unset(self::$options[(int)$id]);

            return fakeResult(true, (int)$id);
        }

        private static function apply(int $id, array $data): void
        {
            foreach ($data as $key => $value) {
                $property = self::FIELDS[strtoupper((string)$key)] ?? null;
                if ($property) {
                    self::$options[$id]->{$property} = $value;
                }
            }
        }

        private static function takeFailure(): ?string
        {
            $error = self::$failNext;
            self::$failNext = null;

            return $error;
        }
    }

    /**
     * Таблица вкладок в памяти
     */
    class ClaramenteOptionTabsTable
    {
        /** @var TabEntityStructure[] */
        public static array $tabs = [];

        public static function getByCode($code)
        {
            foreach (self::$tabs as $tab) {
                if ($tab->code === $code) {
                    return clone $tab;
                }
            }

            return null;
        }

        public static function getTabById($id)
        {
            return isset(self::$tabs[(int)$id]) ? clone self::$tabs[(int)$id] : null;
        }

        public static function add($data)
        {
            $id = self::$tabs ? max(array_keys(self::$tabs)) + 1 : 1;
            self::$tabs[$id] = new TabEntityStructure($id, (string)$data['NAME'], (string)$data['CODE'], (int)($data['SORT'] ?? 100));

            return fakeResult(true, $id);
        }

        public static function update($id, $data)
        {
            // Как в ORM: код обязателен
            if (array_key_exists('CODE', $data) && (string)$data['CODE'] === '') {
                return fakeResult(false, (int)$id, ['Не заполнено обязательное поле «CODE»']);
            }
            $tab = self::$tabs[(int)$id] ?? null;
            if ($tab) {
                self::$tabs[(int)$id] = new TabEntityStructure($tab->id, (string)($data['NAME'] ?? $tab->name), (string)($data['CODE'] ?? $tab->code), (int)($data['SORT'] ?? $tab->sort));
            }

            return fakeResult(true, (int)$id);
        }

        public static function delete($id)
        {
            unset(self::$tabs[(int)$id]);

            return fakeResult(true, (int)$id);
        }
    }
}

namespace Sprint\Migration\Exceptions {
    use Exception;

    class MigrationException extends Exception
    {
    }
}

namespace {
    const LANG = 'ru';
    const LANGUAGE_ID = 'ru';

    class CAdminForm
    {
        public array $fields = [];

        /** HTML дополнительных кнопок формы */
        public string $buttons = '';

        public function Buttons($params = false, $additionalHtml = ''): void
        {
            $this->buttons .= (string)$additionalHtml;
        }

        public function __call($name, $arguments)
        {
            return null;
        }

        /**
         * Как в Bitrix: значение выводится через htmlspecialcharsbx(htmlspecialcharsback($value))
         */
        public function AddTextField($id, $label, $value, $arParams = [], $required = false): void
        {
            $this->fields[$id] = '<textarea>' . htmlspecialcharsbx(htmlspecialcharsback((string)$value)) . '</textarea>';
        }

        public function AddViewField($id, $content, $html, $required = false): void
        {
            $this->fields[$id] = $html;
        }
    }

    /**
     * Токен сессии: проверка проходит, пока тест не выставит $GLOBALS['TEST_SESSID_VALID'] = false
     */
    function check_bitrix_sessid(): bool
    {
        return ($GLOBALS['TEST_SESSID_VALID'] ?? true) === true;
    }

    function bitrix_sessid_post(): string
    {
        return '<input type="hidden" name="sessid" value="test">';
    }

    function bitrix_sessid_get(): string
    {
        return 'sessid=test';
    }

    /**
     * Файлы Bitrix: GetFileArray() возвращает записи из $files
     */
    class CFile
    {
        public static array $files = [];

        /** id удаленных файлов */
        public static array $deleted = [];

        public static function Delete($id): void
        {
            self::$deleted[] = $id;
        }

        public static function GetFileArray($id): array|false
        {
            return self::$files[(int)$id] ?? false;
        }

        public static function IsImage($src): bool
        {
            return str_ends_with((string)$src, '.png');
        }

        public static function InputFile(...$args): string
        {
            return '<input type="file">';
        }

        public static function ShowImage($id, ...$args): string
        {
            return '<img data-file="' . $id . '">';
        }

        public static function ShowFile($id, ...$args): string
        {
            return '<a data-file="' . $id . '">';
        }
    }

    /**
     * Упрощенная замена CBXSanitizer: удаляет script/iframe с содержимым, обработчики on* и javascript: в ссылках,
     * как Bitrix кодирует текст между тегами (htmlspecialchars, ApplyDoubleEncode)
     * Запоминает настройки вызова; поведение настоящего санитайзера проверяется в Bitrix
     */
    class CBXSanitizer
    {
        public const SECURE_LEVEL_HIGH = 0;
        public const SECURE_LEVEL_MIDDLE = 1;
        public const SECURE_LEVEL_LOW = 2;

        /** Настройки и HTML каждого вызова SanitizeHtml() */
        public static array $calls = [];

        private array $settings = [
            'level' => null, 'htmlSpecChars' => true, 'doubleEncode' => true,
            'deleteSanitized' => false, 'deleteWithContent' => [], 'tags' => [],
        ];

        public function SetLevel($level): void
        {
            $this->settings['level'] = $level;
        }

        public function UpdateTags(array $tags): void
        {
            $this->settings['tags'] = $tags;
        }

        public function ApplyHtmlSpecChars($apply): void
        {
            // Как в Bitrix: вызов с false устарел и выдает предупреждение
            if (! $apply) {
                trigger_error('It is strongly not recommended to use \\CBXSanitizer::ApplyHtmlSpecChars(false)', E_USER_WARNING);
            }
            $this->settings['htmlSpecChars'] = (bool)$apply;
        }

        public function ApplyDoubleEncode($apply = true): void
        {
            $this->settings['doubleEncode'] = (bool)$apply;
        }

        public function DeleteSanitizedTags($apply = true): void
        {
            $this->settings['deleteSanitized'] = (bool)$apply;
        }

        public function setDelTagsWithContent(array $tags): void
        {
            $this->settings['deleteWithContent'] = $tags;
        }

        public function SanitizeHtml($html): string
        {
            self::$calls[] = $this->settings + ['html' => (string)$html];
            $html = preg_replace('~<(script|iframe)\b.*?</\1>~is', '', (string)$html);
            $html = preg_replace('~\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)~i', '', $html);
            $html = preg_replace('~href\s*=\s*"javascript:[^"]*"~i', '', $html);
            if (! $this->settings['htmlSpecChars']) {
                return $html;
            }
            // Как в Bitrix: теги — до первого «>», остальное — текст
            $parts = preg_split('/(<[^<>]+>)/u', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
            foreach ($parts as $i => &$part) {
                if ($i % 2 === 0) {
                    $part = htmlspecialchars($part, ENT_QUOTES, 'UTF-8', $this->settings['doubleEncode']);
                }
            }

            return implode('', $parts);
        }
    }

    class CModule
    {
        /** Установленные модули */
        public static array $modules = [];

        public static function IncludeModule($module): bool
        {
            return in_array($module, self::$modules, true);
        }
    }

    function htmlspecialcharsbx($value, $flags = ENT_COMPAT, $doubleEncode = true): string
    {
        return htmlspecialchars((string)$value, $flags, 'UTF-8', $doubleEncode);
    }

    /**
     * Приближенно, как в Bitrix: уже закодированные сущности не кодируются повторно
     */
    function htmlspecialcharsex($value): string
    {
        return htmlspecialchars((string)$value, ENT_COMPAT, 'UTF-8', false);
    }

    /**
     * Как в Bitrix: декодируются только &lt; &gt; &quot; &apos; &amp;
     */
    function htmlspecialcharsback($value): string
    {
        return str_replace(['&lt;', '&gt;', '&quot;', '&apos;', '&amp;'], ['<', '>', '"', "'", '&'], (string)$value);
    }

    class CAdminMessage
    {
        public static array $messages = [];

        public static function ShowMessage($message): void
        {
            self::$messages[] = is_array($message)
                ? trim(($message['MESSAGE'] ?? '') . "\n" . ($message['DETAILS'] ?? ''))
                : (string)$message;
        }
    }

    class RedirectException extends RuntimeException
    {
    }

    function LocalRedirect($url): void
    {
        throw new RedirectException((string)$url);
    }
}
