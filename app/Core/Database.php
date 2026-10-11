<?php declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

/**
 * Обёртка над PDO. Синглтон.
 * PHP 7.4, MySQL 5.7 (без JSON-типов — используем TEXT).
 */
class Database
{
    /** @var Database|null */
    private static $instance = null;

    /** @var PDO */
    private $pdo;

    /** @var array */
    private $config;

    /** @var int */
    private $queryCount = 0;

    /**
     * Приватный конструктор — только через init().
     */
    private function __construct(array $config)
    {
        $this->config = $config;

        $dsn = 'mysql:host=' . $config['host']
             . ';port=' . $config['port']
             . ';dbname=' . $config['name']
             . ';charset=' . $config['charset'];

        try {
            $this->pdo = new PDO($dsn, $config['user'], $config['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . $config['charset'] . " COLLATE utf8mb4_unicode_ci, sql_mode='STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'",
            ]);
        } catch (PDOException $e) {
            Logger::error('Не удалось подключиться к БД: ' . $e->getMessage());
            throw new RuntimeException('Ошибка подключения к базе данных.');
        }
    }

    /**
     * Инициализация соединения (вызывается один раз из index.php).
     */
    public static function init(array $config): void
    {
        self::$instance = new self($config);
    }

    /**
     * Текущее соединение.
     */
    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            throw new RuntimeException('Database не инициализирован. Вызовите Database::init().');
        }
        return self::$instance;
    }

    /**
     * Голый PDO (для редких случаев).
     */
    public function pdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Выполнить запрос с параметрами.
     */
    public function query(string $sql, array $params = []): PDOStatement
    {
        $this->queryCount++;

        try {
            $stmt = $this->pdo->prepare($sql);
            foreach ($params as $key => $value) {
                $placeholder = is_int($key) ? $key + 1 : ':' . ltrim((string)$key, ':');
                if (is_int($value)) {
                    $type = PDO::PARAM_INT;
                } elseif (is_bool($value)) {
                    $type = PDO::PARAM_BOOL;
                } elseif ($value === null) {
                    $type = PDO::PARAM_NULL;
                } else {
                    $type = PDO::PARAM_STR;
                }
                $stmt->bindValue($placeholder, $value, $type);
            }
            $stmt->execute();
            return $stmt;
        } catch (PDOException $e) {
            Logger::error('SQL ошибка: ' . $e->getMessage(), ['sql' => $sql, 'params' => $params]);
            throw $e;
        }
    }

    /**
     * Одна строка.
     */
    public function fetch(string $sql, array $params = [])
    {
        $row = $this->query($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /**
     * Все строки.
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    /**
     * Первая колонка первой строки.
     */
    public function fetchColumn(string $sql, array $params = [])
    {
        $value = $this->query($sql, $params)->fetchColumn();
        return $value === false ? null : $value;
    }

    /**
     * Вставка строки, возвращает ID.
     */
    public function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $quoted = [];
        foreach ($columns as $column) {
            $quoted[] = '`' . str_replace('`', '', $column) . '`';
        }

        $placeholders = [];
        foreach ($columns as $column) {
            $placeholders[] = ':' . $column;
        }

        $sql = 'INSERT INTO `' . str_replace('`', '', $table) . '` ('
             . implode(', ', $quoted) . ') VALUES (' . implode(', ', $placeholders) . ')';

        $this->query($sql, $data);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Обновление строк (WHERE — строка с плейсхолдерами).
     */
    public function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $sets = [];
        foreach (array_keys($data) as $column) {
            $sets[] = '`' . str_replace('`', '', $column) . '` = :' . $column;
        }

        $sql = 'UPDATE `' . str_replace('`', '', $table) . '` SET ' . implode(', ', $sets) . ' WHERE ' . $where;

        $params = $data;
        foreach ($whereParams as $key => $value) {
            $params[is_int($key) ? $key : 'w' . $key] = $value;
        }

        // Приводим именованные параметры WHERE к виду :wN, если они числовые
        $index = 0;
        foreach ($whereParams as $key => $value) {
            if (is_int($key)) {
                $sql = preg_replace('/\?/', ':w' . $index, $sql, 1);
                $index++;
            }
        }

        return $this->query($sql, $params)->rowCount();
    }

    /**
     * Удаление строк.
     */
    public function delete(string $table, string $where, array $params = []): int
    {
        $sql = 'DELETE FROM `' . str_replace('`', '', $table) . '` WHERE ' . $where;
        return $this->query($sql, $params)->rowCount();
    }

    /**
     * Транзакция с автоматическим откатом при исключении.
     */
    public function transaction(callable $callback)
    {
        $this->beginTransaction();

        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->rollBack();
            throw $e;
        }
    }

    public function beginTransaction(): bool
    {
        if (!$this->pdo->inTransaction()) {
            return $this->pdo->beginTransaction();
        }
        return false;
    }

    public function commit(): bool
    {
        if ($this->pdo->inTransaction()) {
            return $this->pdo->commit();
        }
        return false;
    }

    public function rollBack(): bool
    {
        if ($this->pdo->inTransaction()) {
            return $this->pdo->rollBack();
        }
        return false;
    }

    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }

    public function lastInsertId(): int
    {
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Сколько запросов выполнено (для отладки).
     */
    public function queryCount(): int
    {
        return $this->queryCount;
    }
}
