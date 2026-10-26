<?php

declare(strict_types=1);

namespace {
    if (PHP_SAPI !== 'cli' || getenv('CLARAMENTE_TEST_MODE') !== 'offline') {
        http_response_code(404);
        exit(1);
    }
}

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

namespace Bitrix\Main\Type {
    class DateTime
    {
    }
}

namespace Claramente\Options\Admin {
    class AdminForm
    {
        public static array $options = [];
        public static ?\CAdminForm $form = null;

        public function getFormTabs(): array
        {
            return [
                [
                    'ID' => 0,
                    'DIV' => 'options',
                ],
            ];
        }

        public function getTabOptions($id): array
        {
            return self::$options;
        }

        public function getForm($name, $tabs): \CAdminForm
        {
            self::$form = new \CAdminForm();

            return self::$form;
        }
    }
}

namespace Claramente\Options\Entity {
    class ClaramenteOptionsTable
    {
        public static array $options = [];

        public static function getOptionById($id)
        {
            return self::$options[$id] ?? null;
        }

        public static function getByCode($code, $site)
        {
            return null;
        }

        public static function add($data)
        {
            self::$options[1] = \fixture();

            return self::update(1, $data);
        }

        public static function update($id, $data)
        {
            foreach ($data as $key => $value) {
                if (strtolower($key) === 'settings') {
                    self::$options[$id]->settings = $value;
                }
            }

            return new class {
                public function isSuccess(): bool
                {
                    return true;
                }

                public function getId(): int
                {
                    return 1;
                }
            };
        }
    }
}

namespace {
    use Bitrix\Main\Application;
    use Bitrix\Main\HttpRequest;
    use Bitrix\Main\Type\DateTime;
    use Claramente\Options\Entity\ClaramenteOptionsTable;
    use Claramente\Options\Services\MigrationService;
    use Claramente\Options\Structures\Entity\OptionEntityStructure;
    use Claramente\Options\Types\StringOption;

    class CAdminForm
    {
        public array $fields = [];

        public function __call($name, $arguments)
        {
            return null;
        }

        public function AddViewField($id, $content, $html, $required = false): void
        {
            $this->fields[$id] = $html;
        }
    }

    function htmlspecialcharsbx($value, $flags = ENT_COMPAT, $doubleEncode = true): string
    {
        return htmlspecialchars(
            (string)$value,
            $flags,
            'UTF-8',
            $doubleEncode
        );
    }

    class RedirectException extends \RuntimeException
    {
    }

    function LocalRedirect($url): void
    {
        throw new RedirectException();
    }

    function check(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new \RuntimeException($message);
        }
    }

    function fixture(?string $settings = null): OptionEntityStructure
    {
        return new OptionEntityStructure(
            1,
            'Sample',
            'sample',
            null,
            'string',
            $settings,
            100,
            null,
            null,
            true,
            false,
            new DateTime(),
            new DateTime()
        );
    }

    require __DIR__ . '/../lib/types/abstractoption.php';
    require __DIR__ . '/../lib/types/stringoption.php';
    require __DIR__ . '/../lib/types/selectoption.php';
    require __DIR__ . '/../lib/types/booloption.php';
    require __DIR__ . '/../lib/types/dateoption.php';
    require __DIR__ . '/../lib/types/fileoption.php';
    require __DIR__ . '/../lib/types/textoption.php';
    require __DIR__ . '/../lib/types/stringsoption.php';
    require __DIR__ . '/../lib/services/optiontypes.php';
    require __DIR__ . '/../lib/services/migrationservice.php';
    require __DIR__ . '/../lib/structures/entity/optionentitystructure.php';

    class CustomOption extends StringOption
    {
        public static function getCode(): string
        {
            return 'custom';
        }

        public function getFormFieldValue(
            string $postName,
            CAdminForm &$form,
            OptionEntityStructure $option
        ): void {
            $form->AddViewField(
                $postName,
                '',
                'single value'
            );
        }

        public function getFormFieldMultipleValue(
            string $postName,
            CAdminForm &$form,
            OptionEntityStructure $option
        ): void {
            $form->AddViewField(
                $postName,
                '',
                'multiple values'
            );
        }

        public function beforeSaveSettings(HttpRequest $request, OptionEntityStructure $option): ?string
        {
            return serialize([
                'custom_setting' => 'retained',
            ]);
        }

        protected function getFieldFooter(): string
        {
            return '<span>Custom footer</span>' . parent::getFieldFooter();
        }
    }

    check(!function_exists('exec'), 'Subprocess guard');
    check(!function_exists('stream_socket_client'), 'Network guard');
    check(ini_get('allow_url_fopen') === '0', 'URL stream guard');
    check(getenv('CLARAMENTE_TEST_SENTINEL_TOKEN') === false, 'Credential guard');

    set_error_handler(static function ($severity, $message, $file, $line): bool {
        if ($severity === E_DEPRECATED) {
            return false;
        }

        throw new \ErrorException(
            $message,
            0,
            $severity,
            $file,
            $line
        );
    });

    $hint = "Use <script>alert('x')</script> & \"quotes\"\nNext&nbsp;line &#160; &amp; <a href=\"javascript:alert(1)\">link</a>";
    foreach ([new StringOption(), new CustomOption(), new \Claramente\Options\Types\SelectOption(),
        new \Claramente\Options\Types\BoolOption(), new \Claramente\Options\Types\DateOption(),
        new \Claramente\Options\Types\FileOption(), new \Claramente\Options\Types\TextOption(),
        new \Claramente\Options\Types\StringsOption()] as $type) {
        foreach ([null, serialize([]), serialize(['hint' => '']), serialize(['hint' => '  ']),
            serialize(['hint' => []])] as $settings) {
            $form = new CAdminForm();
            $type->getFormFieldHint('options[1]', $form, fixture($settings));
            check($form->fields === [], 'Absent/empty hint must not alter form');
        }
        foreach ([true, false] as $single) {
            $option = fixture(serialize(['hint' => $hint]));
            $option->singleValueForAllLang = $single;
            $form = new CAdminForm();
            $type->getFormFieldHint('options[1]', $form, $option);
            $html = $form->fields['options[1][hint]'];
            check(count($form->fields) === 1, 'Hint displayed once per option');
            check(!str_contains($html, '<script>') && !str_contains($html, '<a '), 'No executable HTML');
            check(str_contains($html, '&lt;script&gt;'), 'Tags displayed as text');
            check(str_contains($html, '&#039;') && str_contains($html, '&quot;'), 'Quotes encoded');
            check(str_contains($html, '<br />'), 'Line breaks retained');
            check(str_contains($html, '&nbsp;') && str_contains($html, '&#160;'), 'Entities retained');
            check(!str_contains($html, '&amp;amp;'), 'Entities not encoded twice');
        }
    }

    $USER = new class {
        public function IsAdmin(): bool
        {
            return false;
        }
    };
    $request = new HttpRequest();
    foreach ([true, false] as $single) {
        $visible = fixture(serialize(['hint' => 'Visible hint']));
        $visible->type = 'custom';
        $visible->singleValueForAllLang = $single;
        $hidden = clone $visible;
        $hidden->id = 2;
        $hidden->isAdminOnly = true;
        \Claramente\Options\Admin\AdminForm::$options = [$visible, $hidden];
        ob_start();
        require __DIR__ . '/../admin/pages/main.php';
        ob_end_clean();
        $fields = \Claramente\Options\Admin\AdminForm::$form->fields;
        check(array_keys($fields) === ['options[1]', 'options[1][hint]'], 'Common form field/hint order and access');
        check(str_contains($fields['options[1][hint]'], 'Visible hint'), 'Common form renders hint');
        check($fields['options[1]'] === ($single ? 'single value' : 'multiple values'), 'Value renderer unchanged');
    }

    define('LANG', 'en');
    $USER = new class {
        public function IsAdmin(): bool
        {
            return true;
        }
    };
    foreach (['string', 'select', 'custom'] as $type) {
        foreach (['new', 'edit', 'clear', 'legacy'] as $scenario) {
            ClaramenteOptionsTable::$options = $scenario === 'new' ? [] : [1 => fixture()];
            $post = [
                'code' => 'sample',
                'name' => 'Sample',
                'type' => $type,
                'site_id' => null,
                'single_value_for_all_lang' => 'Y',
                'hint' => $scenario === 'clear' ? '' : $hint,
                'settings' => [
                    'size' => '30',
                    'values' => ['First', '', 'Second'],
                ],
            ];
            if ($scenario === 'legacy') {
                unset($post['hint']);
            }
            Application::$request = new HttpRequest(
                ['option' => $post],
                $scenario === 'new' ? [] : ['ID' => 1]
            );
            try {
                require __DIR__ . '/../admin/save/option.php';
            } catch (RedirectException $error) {
            }
            $saved = unserialize(ClaramenteOptionsTable::$options[1]->settings);
            check(($saved['hint'] ?? '') === (in_array($scenario, ['clear', 'legacy'], true) ? '' : $hint), 'Hint round trip');
            if ($type === 'select') {
                check($saved['values'] === [0 => 'First', 2 => 'Second'], 'Select values preserved');
            } elseif ($type === 'custom') {
                check($saved['custom_setting'] === 'retained', 'Custom settings preserved');
            } else {
                check($saved['size'] === '30', 'String settings preserved');
            }
        }
    }

    $service = (new \ReflectionClass(MigrationService::class))->newInstanceWithoutConstructor();
    $method = new \ReflectionMethod(MigrationService::class, 'getMigrationData');
    $settings = serialize(['hint' => "Quote ' slash \\ newline\nUnicode: текст"]);
    if (PHP_VERSION_ID < 80100) {
        $method->setAccessible(true);
    }
    $params = $method->invoke($service, [
        'TAB_ID' => null, 'NAME' => 'Sample', 'CODE' => 'sample', 'TYPE' => 'string',
        'SITE_ID' => null, 'VALUE' => null, 'SETTINGS' => $settings, 'SORT' => 100,
        'SINGLE_VALUE_FOR_ALL_LANG' => true,
    ], null);
    check($params['OPTION_SETTINGS'] === $settings, 'Migration receives original serialized settings');
    $builder = new class($params) {
        public function __construct(private array $params)
        {
        }

        public function getVersionConfig(): object
        {
            return new class($this->params) {
                public function __construct(private array $params)
                {
                }

                public function getList(): array
                {
                    return [['values' => $this->params]];
                }
            };
        }

        public function render(): string
        {
            $version = 'SyntheticHintMigration';
            $description = 'Sample';
            $extendUse = '';
            $extendClass = '\\stdClass';
            $moduleVersion = '1.0.0';
            $author = 'Sample';
            ob_start();
            require __DIR__ . '/../lib/migration/template.php';

            return ob_get_clean();
        }
    };
    $generated = $builder->render();
    eval(substr($generated, 5));
    $migration = (new \ReflectionClass('Sprint\\Migration\\SyntheticHintMigration'))->newInstanceWithoutConstructor();
    $property = new \ReflectionProperty($migration, 'optionSettings');
    if (PHP_VERSION_ID < 80100) {
        $property->setAccessible(true);
    }
    check($property->getValue($migration) === $settings, 'Generated migration round trip');

    echo "PASS: hints, all built-in types, inheritance, settings save, migration, offline guards\n";
}
