<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Response;

final class AuthController extends AdminController
{
    public function showLogin(): void
    {
        if ($this->app->auth->check()) {
            Response::redirect(admin_url());
        }
        $next = $this->app->request->query('next');
        $next = is_string($next) ? $next : '';
        echo $this->app->view->render('admin/auth/login', [
            'title' => '登录',
            'next'  => $this->safeNext($next),
        ], 'layouts/auth');
    }

    public function login(): void
    {
        $login = $this->stringInput('login');
        $password = (string) ($this->app->request->post('password') ?? '');
        $next = $this->stringInput('next');
        $next = $this->safeNext($next);

        if ($login === '' || $password === '') {
            $this->app->session->setErrors(['login' => '请输入账号和密码。']);
            $this->app->session->flashInput(['login' => $login]);
            Response::redirect(admin_url('login' . ($next !== '' ? '?next=' . rawurlencode($next) : '')));
        }

        if ($this->app->auth->tooManyAttempts($login)) {
            $this->app->session->setErrors(['login' => '登录尝试过于频繁，请 15 分钟后再试。']);
            Response::redirect(admin_url('login'));
        }

        if (!$this->app->auth->attempt($login, $password)) {
            $this->app->auth->recordFailure($login);
            $this->app->session->setErrors(['login' => '账号或密码不正确。']);
            $this->app->session->flashInput(['login' => $login]);
            Response::redirect(admin_url('login' . ($next !== '' ? '?next=' . rawurlencode($next) : '')));
        }

        $this->app->auth->clearFailures($login);
        $this->app->session->flash('success', '欢迎回来，' . (current_user()['display_name'] ?? '') . '。');
        Response::redirect($next !== '' ? $next : admin_url());
    }

    public function logout(): void
    {
        $this->app->auth->logout();
        $this->app->session->flash('success', '你已安全退出。');
        Response::redirect(admin_url('login'));
    }

    private function safeNext(string $next): string
    {
        if ($next === '' || !str_starts_with($next, '/admin') || str_starts_with($next, '//') || str_contains($next, '\\')) {
            return '';
        }

        return $next;
    }
}