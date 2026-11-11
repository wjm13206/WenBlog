<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Small fluent validator used by admin controllers.
 */
final class Validator
{
    /** @var array<string, string> */
    private array $errors = [];

    /** @param array<string, mixed> $data */
    public function __construct(private readonly array $data)
    {
    }

    public function required(string $field, string $label): self
    {
        $value = $this->data[$field] ?? null;
        if ($value === null || (is_string($value) && trim($value) === '') || (is_array($value) && $value === [])) {
            $this->add($field, $label . '不能为空');
        }

        return $this;
    }

    public function max(string $field, int $length, string $label): self
    {
        $value = $this->data[$field] ?? '';
        if (is_string($value) && mb_strlen($value, 'UTF-8') > $length) {
            $this->add($field, sprintf('%s不能超过 %d 个字符', $label, $length));
        }

        return $this;
    }

    public function min(string $field, int $length, string $label): self
    {
        $value = $this->data[$field] ?? '';
        if (is_string($value) && $value !== '' && mb_strlen($value, 'UTF-8') < $length) {
            $this->add($field, sprintf('%s至少需要 %d 个字符', $label, $length));
        }

        return $this;
    }

    public function email(string $field, string $label): self
    {
        $value = (string) ($this->data[$field] ?? '');
        if ($value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            $this->add($field, $label . '格式不正确');
        }

        return $this;
    }

    public function url(string $field, string $label): self
    {
        $value = (string) ($this->data[$field] ?? '');
        if ($value !== '' && filter_var($value, FILTER_VALIDATE_URL) === false) {
            $this->add($field, $label . '必须是完整网址');
        }

        return $this;
    }

    public function integer(string $field, int $min, int $max, string $label): self
    {
        $value = filter_var($this->data[$field] ?? null, FILTER_VALIDATE_INT);
        if ($value === false || $value < $min || $value > $max) {
            $this->add($field, sprintf('%s必须在 %d 到 %d 之间', $label, $min, $max));
        }

        return $this;
    }

    public function in(string $field, array $allowed, string $label): self
    {
        $value = (string) ($this->data[$field] ?? '');
        if (!in_array($value, $allowed, true)) {
            $this->add($field, $label . '取值无效');
        }

        return $this;
    }

    public function slug(string $field, string $label): self
    {
        $value = (string) ($this->data[$field] ?? '');
        if ($value !== '' && preg_match('/^[\p{L}\p{N}][\p{L}\p{N}-]*$/u', $value) !== 1) {
            $this->add($field, $label . '只能包含字母、数字、汉字和连字符');
        }

        return $this;
    }

    public function date(string $field, string $label): self
    {
        $value = (string) ($this->data[$field] ?? '');
        if ($value !== '' && strtotime($value) === false) {
            $this->add($field, $label . '格式不正确');
        }

        return $this;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function passes(): bool
    {
        return $this->errors === [];
    }

    /** @return array<string, string> */
    public function errors(): array
    {
        return $this->errors;
    }

    private function add(string $field, string $message): void
    {
        $this->errors[$field] ??= $message;
    }
}