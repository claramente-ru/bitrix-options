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
    use CAdminForm;

    class AdminForm
    {
        public static array $options = [];
        public static ?CAdminForm $form = null;

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

        public function getForm($name, $tabs): CAdminForm
        {
            self::$form = new CAdminForm();

            return self::$form;
        }
    }
}

namespace Claramente\Options\Entity {
    use function fixture;

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
            self::$options[1] = fixture();

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
    const LANG = 'ru';

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
        return htmlspecialchars((string)$value, $flags, 'UTF-8', $doubleEncode);
    }

    class RedirectException extends RuntimeException
    {
    }

    function LocalRedirect($url): void
    {
        throw new RedirectException((string)$url);
    }
}
