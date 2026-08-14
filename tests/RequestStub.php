<?php

namespace Modules\Sviat\OrdersExport;

use Okay\Core\Request;

/**
 * Ручний стаб замість мока: PHPUnit 13 узагалі відмовляється дублювати клас,
 * у якого є метод з іменем "method" — а саме так називається метод Request,
 * що віддає HTTP-метод запиту.
 */
class RequestStub extends Request
{
    /** @var callable|null */
    private $postCallback;

    /** @var bool */
    private $isPost;

    public function __construct($isPost = true, ?callable $postCallback = null)
    {
        $this->isPost = $isPost;
        $this->postCallback = $postCallback;
    }

    public function method($method = null)
    {
        return $this->isPost;
    }

    public function post($name = null, $type = null, $default = null)
    {
        if ($this->postCallback === null) {
            return null;
        }

        return call_user_func($this->postCallback, $name);
    }

    public function get($name, $type = null, $default = null, $stripTags = true)
    {
        return null;
    }
}
