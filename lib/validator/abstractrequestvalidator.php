<?php

declare(strict_types=1);

namespace Claramente\Options\Validator;

use Bitrix\Main\Application;
use Bitrix\Main\HttpRequest;
use Bitrix\Main\Request;
use Exception;

abstract class AbstractRequestValidator
{
    protected array $fields;
    protected string $moduleId = 'claramente.options';
    protected Request|HttpRequest $request;

    public function __construct()
    {
        $this->request = Application::getInstance()->getContext()->getRequest();
        $this->fields = array_merge($this->request->getPostList()->toArray(), $this->request->getQueryList()->toArray());
    }


    /**
     * @throws Exception
     */
    public function checkAccess(string $access = "U"): bool
    {
        global $APPLICATION;
        if ($APPLICATION->GetGroupRight($this->moduleId) < $access) {
            throw new Exception('Доступ запрещен');
        }

        return true;
    }

    /**
     * @throws Exception
     */
    public function checkSession(): bool
    {
        if (! check_bitrix_sessid()) {
            throw new Exception('Сессия истекла');
        }

        return true;
    }
}