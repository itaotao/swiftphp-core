<?php

namespace SwiftPHP\Core\Controller;

use SwiftPHP\Core\Request\Request;
use SwiftPHP\Core\Response\Response;

class Controller
{
    protected $request;
    protected $viewPath;

    public function __construct()
    {
        $this->viewPath = dirname(__DIR__, 2) . '/app/view/';
    }

    public function __invoke(Request $request): Response
    {
        $this->request = $request;
        return $this->index();
    }

    protected function index(?Request $request = null): Response
    {
        return $this->json(['code' => 200, 'msg' => 'success']);
    }

    protected function assign(string $name, $value = null)
    {
        if (is_array($name)) {
            return $name;
        }
        return [$name => $value];
    }

    protected function fetch(string $template = '', array $vars = []): Response
    {
        if (empty($template)) {
            $template = $this->getDefaultTemplate();
        }

        $file = $this->viewPath . str_replace('.', '/', $template) . '.html';

        if (!file_exists($file)) {
            return $this->json(['code' => 404, 'msg' => 'Template not found: ' . $template]);
        }

        ob_start();
        extract($vars);
        include $file;
        $content = ob_get_clean();

        return Response::create($content, 200, ['Content-Type' => 'text/html']);
    }

    protected function getDefaultTemplate(): string
    {
        $class = get_class($this);
        $class = str_replace('App\\Controller\\', '', $class);
        $class = str_replace('\\', '/', $class);
        $class = strtolower(str_replace('Controller', '', $class));
        return $class . '/' . 'index';
    }

    protected function json(array $data = [], int $code = 200): Response
    {
        return Response::json($data, $code);
    }

    protected function xml(string $data = '', int $code = 200): Response
    {
        return Response::xml($data, $code);
    }

    protected function redirect(string $url, int $code = 302): Response
    {
        return Response::redirect($url, $code);
    }

    protected function notFound(string $message = 'Not Found'): Response
    {
        return Response::notFound($message);
    }

    protected function serverError(string $message = 'Internal Server Error'): Response
    {
        return Response::serverError($message);
    }

    protected function success($data = [], string $msg = 'success'): Response
    {
        return $this->json(['code' => 200, 'msg' => $msg, 'data' => $data]);
    }

    protected function error(string $msg = 'Error', int $code = 400): Response
    {
        return $this->json(['code' => $code, 'msg' => $msg]);
    }
}
