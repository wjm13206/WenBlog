<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Response;
use App\Core\Security;
use App\Models\User;

final class ProfileController extends AdminController
{
    public function index(): void
    {
        $this->render('profile/index', [
            'user' => $this->app->auth->user(),
        ], '个人资料');
    }

    public function update(): void
    {
        $user = $this->app->auth->user();
        if ($user === null) {
            Response::redirect(admin_url('login'));
        }
        $displayName = trim((string) ($this->app->request->post('display_name') ?? ''));
        $email = trim((string) ($this->app->request->post('email') ?? ''));
        $bio = trim((string) ($this->app->request->post('bio') ?? ''));
        $password = (string) ($this->app->request->post('password') ?? '');
        $confirm = (string) ($this->app->request->post('password_confirm') ?? '');
        $current = (string) ($this->app->request->post('current_password') ?? '');

        $errors = [];
        if ($displayName === '') {
            $errors['display_name'] = '显示名称不能为空。';
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = '请填写有效的邮箱地址。';
        }
        if ($password !== '') {
            if (!Security::verifyPassword($current, (string) $user['password_hash'])) {
                $errors['current_password'] = '当前密码不正确。';
            }
            if (strlen($password) < 8) {
                $errors['password'] = '新密码至少 8 个字符。';
            }
            if ($password !== $confirm) {
                $errors['password_confirm'] = '两次输入的新密码不一致。';
            }
        }

        if ($errors !== []) {
            $this->app->session->setErrors($errors);
            Response::redirect(admin_url('profile'));
        }

        try {
            (new User($this->app))->updateProfile((int) $user['id'], [
                'display_name' => $displayName,
                'email'        => $email,
                'bio'          => $bio,
                'password'     => $password,
            ]);
            if ($password !== '') {
                $this->app->session->regenerate();
            }
            $this->success('个人资料已更新。');
        } catch (\Throwable $e) {
            $this->app->session->setErrors(['form' => $e->getMessage()]);
        }
        Response::redirect(admin_url('profile'));
    }
}