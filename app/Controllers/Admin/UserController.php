<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Response;
use App\Models\User;

final class UserController extends AdminController
{
    public function index(): void
    {
        $this->render('users/index', [
            'users' => (new User($this->app))->all(),
        ], '用户');
    }

    public function create(): void
    {
        $this->render('users/form', [
            'user' => [
                'id'           => null,
                'username'     => '',
                'display_name' => '',
                'email'        => '',
                'role'         => 'editor',
                'status'       => 'active',
                'bio'          => '',
            ],
        ], '新增用户');
    }

    public function store(): void
    {
        $data = $this->payload();
        $errors = $this->validate($data, true);
        if ($errors !== []) {
            $this->app->session->setErrors($errors);
            $this->app->session->flashInput($data);
            Response::redirect(admin_url('users/create'));
        }
        try {
            $id = (new User($this->app))->create($data);
            $this->success('用户已创建。');
            Response::redirect(admin_url('users/' . $id . '/edit'));
        } catch (\Throwable $e) {
            $this->app->session->setErrors(['form' => $e->getMessage()]);
            $this->app->session->flashInput($data);
            Response::redirect(admin_url('users/create'));
        }
    }

    public function edit(string $id): void
    {
        $user = (new User($this->app))->find((int) $id);
        if ($user === null) {
            Response::error(404, '用户不存在。', true);
        }
        $this->render('users/form', ['user' => $user], '编辑用户');
    }

    public function update(string $id): void
    {
        $users = new User($this->app);
        $user = $users->find((int) $id);
        if ($user === null) {
            Response::error(404, '用户不存在。', true);
        }
        $data = $this->payload();
        $errors = $this->validate($data, false);
        if ($errors !== []) {
            $this->app->session->setErrors($errors);
            $this->app->session->flashInput($data);
            Response::redirect(admin_url('users/' . $id . '/edit'));
        }
        try {
            $users->update((int) $id, $data);
            $this->success('用户资料已保存。');
        } catch (\Throwable $e) {
            $this->app->session->setErrors(['form' => $e->getMessage()]);
        }
        Response::redirect(admin_url('users/' . $id . '/edit'));
    }

    public function delete(string $id): void
    {
        $users = new User($this->app);
        if ($users->find((int) $id) === null) {
            Response::error(404, '用户不存在。', true);
        }
        try {
            $users->delete((int) $id);
            $this->success('用户已删除。');
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
        }
        Response::redirect(admin_url('users'));
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'username'     => trim((string) ($this->app->request->post('username') ?? '')),
            'display_name' => trim((string) ($this->app->request->post('display_name') ?? '')),
            'email'        => trim((string) ($this->app->request->post('email') ?? '')),
            'password'     => (string) ($this->app->request->post('password') ?? ''),
            'role'         => $this->stringInput('role', 'editor'),
            'status'       => $this->stringInput('status', 'active'),
            'bio'          => (string) ($this->app->request->post('bio') ?? ''),
        ];
    }

    /** @param array<string, mixed> $data @return array<string, string> */
    private function validate(array $data, bool $creating): array
    {
        $errors = [];
        if (!preg_match('/^[A-Za-z0-9_]{3,30}$/', (string) $data['username'])) {
            $errors['username'] = '用户名需为 3-30 位字母、数字或下划线。';
        }
        if (filter_var($data['email'], FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = '请填写有效的邮箱地址。';
        }
        if ($creating || (string) $data['password'] !== '') {
            if (strlen((string) $data['password']) < 8) {
                $errors['password'] = '密码至少 8 个字符。';
            }
        }
        if (!in_array($data['role'], ['admin', 'editor'], true)) {
            $errors['role'] = '请选择有效的角色。';
        }
        if (!in_array($data['status'], ['active', 'disabled'], true)) {
            $errors['status'] = '请选择有效的状态。';
        }

        return $errors;
    }
}