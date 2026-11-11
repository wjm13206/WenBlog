<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use Throwable;

/**
 * Thin PDO wrapper. Every query uses prepared statements; identifiers are
 * never built from request data.
 */
final class Database
{
    private ?PDO $pdo = null;

    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config)
    {
    }

    public function connection(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        $host = (string) ($this->config['host'] ?? '127.0.0.1');
        $port = (int) ($this->config['port'] ?? 3306);
        $name = (string) ($this->config['name'] ?? '');
        $charset = (string) ($this->config['charset'] ?? 'utf8mb4');
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $name, $charset);

        $this->pdo = new PDO(
            $dsn,
            (string) ($this->config['user'] ?? ''),
            (string) ($this->config['pass'] ?? ''),
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );

        $this->syncTimeZone();

        return $this->pdo;
    }

    /**
     * Align MySQL session time with the current PHP timezone. Called again
     * after the site timezone option is loaded from the database.
     */
    public function syncTimeZone(): void
    {
        if (!$this->pdo instanceof PDO) {
            return;
        }
        try {
            $offset = (new \DateTimeImmutable('now', new \DateTimeZone(date_default_timezone_get())))->format('P');
            $this->pdo->exec('SET time_zone = ' . $this->pdo->quote($offset));
        } catch (Throwable) {
            // Hosting may restrict session time_zone; PHP-side dates still work.
        }
    }

    /** @param array<string|int, mixed> $params */
    public function query(string $sql, array $params = []): \PDOStatement
    {
        $statement = $this->connection()->prepare($sql);
        $statement->execute($params);

        return $statement;
    }

    /** @param array<string|int, mixed> $params */
    public function fetch(string $sql, array $params = []): ?array
    {
        $row = $this->query($sql, $params)->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @param array<string|int, mixed> $params
     * @return list<array<string, mixed>>
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    /** @param array<string|int, mixed> $params */
    public function value(string $sql, array $params = []): mixed
    {
        $value = $this->query($sql, $params)->fetchColumn();

        return $value === false ? null : $value;
    }

    /** @param array<string|int, mixed> $params */
    public function count(string $sql, array $params = []): int
    {
        return (int) $this->value($sql, $params);
    }

    /** @param array<string, mixed> $data */
    public function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $placeholders = array_map(static fn (string $column): string => ':' . $column, $columns);
        $sql = sprintf(
            'INSERT INTO `%s` (`%s`) VALUES (%s)',
            $table,
            implode('`, `', $columns),
            implode(', ', $placeholders)
        );
        $params = [];
        foreach ($data as $column => $value) {
            $params[':' . $column] = $value;
        }
        $this->query($sql, $params);

        return (int) $this->connection()->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     * @param string|array<string, mixed> $where
     * @param array<string|int, mixed> $params
     */
    public function update(string $table, array $data, string|array $where, array $params = []): int
    {
        if ($data === []) {
            return 0;
        }

        $sets = [];
        $bindings = [];
        foreach ($data as $column => $value) {
            $sets[] = sprintf('`%s` = :set_%s', $column, $column);
            $bindings[':set_' . $column] = $value;
        }
        [$whereSql, $whereBindings] = $this->buildWhere($where, $params);
        $sql = sprintf('UPDATE `%s` SET %s WHERE %s', $table, implode(', ', $sets), $whereSql);
        $statement = $this->query($sql, array_merge($bindings, $whereBindings));

        return $statement->rowCount();
    }

    /**
     * @param string|array<string, mixed> $where
     * @param array<string|int, mixed> $params
     */
    public function delete(string $table, string|array $where, array $params = []): int
    {
        [$whereSql, $whereBindings] = $this->buildWhere($where, $params);

        return $this->query(sprintf('DELETE FROM `%s` WHERE %s', $table, $whereSql), $whereBindings)->rowCount();
    }

    /**
     * Turn either a raw SQL fragment or a column => value map into a safe
     * WHERE clause. An empty map is rejected so a mistake cannot delete or
     * update every row in a table.
     *
     * @param string|array<string, mixed> $where
     * @param array<string|int, mixed> $params
     * @return array{0: string, 1: array<string|int, mixed>}
     */
    private function buildWhere(string|array $where, array $params): array
    {
        if (is_string($where)) {
            $where = trim($where);
            if ($where === '') {
                throw new \InvalidArgumentException('A WHERE clause is required.');
            }

            return [$where, $params];
        }

        if ($where === []) {
            throw new \InvalidArgumentException('A non-empty WHERE condition is required.');
        }

        $clauses = [];
        $bindings = [];
        foreach ($where as $column => $value) {
            $key = ':where_' . $column;
            $clauses[] = sprintf('`%s` = %s', $column, $key);
            $bindings[$key] = $value;
        }

        return [implode(' AND ', $clauses), array_merge($params, $bindings)];
    }

    public function lastInsertId(): int
    {
        return (int) $this->connection()->lastInsertId();
    }

    /**
     * Run a callback in a transaction, rolling back on any exception.
     *
     * @template T
     * @param callable():T $callback
     * @return T
     */
    public function transaction(callable $callback): mixed
    {
        $pdo = $this->connection();
        $pdo->beginTransaction();
        try {
            $result = $callback();
            $pdo->commit();

            return $result;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public function serverVersion(): string
    {
        try {
            return (string) $this->connection()->getAttribute(PDO::ATTR_SERVER_VERSION);
        } catch (PDOException) {
            return 'unknown';
        }
    }
}