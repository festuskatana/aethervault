<?php
if (!function_exists('dbEnvValue')) {
    function dbEnvValue($key, $default = null) {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        return ($value === false || $value === null || $value === '') ? $default : $value;
    }
}

if (!function_exists('getDatabaseUrlConfig')) {
    function getDatabaseUrlConfig() {
        static $parsed = null;

        if ($parsed !== null) {
            return $parsed;
        }

        $databaseUrl = dbEnvValue('DATABASE_URL', '');
        if ($databaseUrl === '') {
            $parsed = [];
            return $parsed;
        }

        $parts = parse_url($databaseUrl);
        if (!$parts || empty($parts['scheme'])) {
            throw new Exception('Invalid DATABASE_URL format.');
        }

        $scheme = strtolower((string) $parts['scheme']);
        $driver = in_array($scheme, ['pgsql', 'postgres', 'postgresql'], true) ? 'pgsql' : $scheme;
        if (!in_array($driver, ['mysql', 'pgsql'], true)) {
            throw new Exception('Unsupported DATABASE_URL scheme: ' . $scheme);
        }

        parse_str((string) ($parts['query'] ?? ''), $query);

        $parsed = [
            'driver' => $driver,
            'host' => $parts['host'] ?? 'localhost',
            'port' => isset($parts['port']) ? (int) $parts['port'] : ($driver === 'pgsql' ? 5432 : 3306),
            'user' => $parts['user'] ?? 'root',
            'pass' => $parts['pass'] ?? '',
            'name' => isset($parts['path']) ? ltrim((string) $parts['path'], '/') : '',
            'sslmode' => $query['sslmode'] ?? ($driver === 'pgsql' ? 'require' : null),
        ];

        return $parsed;
    }
}

$dbUrlConfig = getDatabaseUrlConfig();

if (!defined('DB_DRIVER')) {
    define('DB_DRIVER', $dbUrlConfig['driver'] ?? dbEnvValue('DB_DRIVER', 'mysql'));
}

if (!defined('DB_HOST')) {
    define('DB_HOST', $dbUrlConfig['host'] ?? dbEnvValue('DB_HOST', 'localhost'));
}

if (!defined('DB_PORT')) {
    define('DB_PORT', (int) ($dbUrlConfig['port'] ?? dbEnvValue('DB_PORT', DB_DRIVER === 'pgsql' ? '5432' : '3306')));
}

if (!defined('DB_USER')) {
    define('DB_USER', $dbUrlConfig['user'] ?? dbEnvValue('DB_USER', 'root'));
}

if (!defined('DB_PASS')) {
    define('DB_PASS', $dbUrlConfig['pass'] ?? dbEnvValue('DB_PASS', ''));
}

if (!defined('DB_NAME')) {
    define('DB_NAME', $dbUrlConfig['name'] ?? dbEnvValue('DB_NAME', 'aether_vault'));
}

if (!defined('DB_SSLMODE')) {
    define('DB_SSLMODE', (string) ($dbUrlConfig['sslmode'] ?? dbEnvValue('DB_SSLMODE', DB_DRIVER === 'pgsql' ? 'require' : '')));
}

class DbResultCompat {
    private array $rows;
    private int $index = 0;
    public int $num_rows;

    public function __construct(array $rows) {
        $this->rows = $rows;
        $this->num_rows = count($rows);
    }

    public function fetch_assoc() {
        if ($this->index >= $this->num_rows) {
            return null;
        }

        return $this->rows[$this->index++];
    }
}

class DbStatementCompat {
    private DbConnectionCompat $connection;
    private PDOStatement $statement;
    private array $boundValues = [];
    private array $boundTypes = [];
    private ?DbResultCompat $result = null;
    public int $affected_rows = 0;

    public function __construct(DbConnectionCompat $connection, PDOStatement $statement) {
        $this->connection = $connection;
        $this->statement = $statement;
    }

    public function bind_param($types, &...$vars) {
        $typeChars = str_split((string) $types);
        foreach ($vars as $index => &$value) {
            $this->boundValues[$index] = &$value;
            $this->boundTypes[$index] = $typeChars[$index] ?? 's';
        }

        return true;
    }

    public function execute() {
        $params = [];
        foreach ($this->boundValues as $index => &$value) {
            $params[] = $this->castValueByType($value, $this->boundTypes[$index] ?? 's');
        }

        $executed = $this->statement->execute($params);
        if (!$executed) {
            $info = $this->statement->errorInfo();
            $this->connection->setError($info[2] ?? 'Statement execution failed');
            return false;
        }

        $this->affected_rows = $this->statement->rowCount();
        $this->connection->syncLastStatementMeta($this->statement, $this->affected_rows);
        $this->result = null;

        return true;
    }

    public function get_result() {
        if ($this->result === null) {
            $rows = $this->statement->fetchAll(PDO::FETCH_ASSOC);
            $this->result = new DbResultCompat($rows);
        }

        return $this->result;
    }

    private function castValueByType($value, string $type) {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'i' => (int) $value,
            'd' => (float) $value,
            default => (string) $value,
        };
    }
}

class DbConnectionCompat {
    private PDO $pdo;
    private string $driver;
    public string $error = '';
    private int $lastInsertIdValue = 0;
    private int $affectedRowsValue = 0;

    public function __construct(PDO $pdo, string $driver) {
        $this->pdo = $pdo;
        $this->driver = $driver;
    }

    public function prepare($sql) {
        try {
            $stmt = $this->pdo->prepare($sql);
            if (!$stmt) {
                $this->error = 'Failed to prepare statement';
                return false;
            }

            return new DbStatementCompat($this, $stmt);
        } catch (Throwable $exception) {
            $this->error = $exception->getMessage();
            return false;
        }
    }

    public function query($sql) {
        try {
            $stmt = $this->pdo->query($sql);
            if (!$stmt) {
                $info = $this->pdo->errorInfo();
                $this->error = $info[2] ?? 'Query failed';
                return false;
            }

            $this->syncLastStatementMeta($stmt, $stmt->rowCount());
            if ($stmt->columnCount() === 0) {
                return true;
            }

            return new DbResultCompat($stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (Throwable $exception) {
            $this->error = $exception->getMessage();
            return false;
        }
    }

    public function real_escape_string($string) {
        return substr($this->pdo->quote((string) $string), 1, -1);
    }

    public function begin_transaction() {
        return $this->pdo->beginTransaction();
    }

    public function commit() {
        return $this->pdo->commit();
    }

    public function rollback() {
        return $this->pdo->rollBack();
    }

    public function getDriver() {
        return $this->driver;
    }

    public function setError(string $message) {
        $this->error = $message;
    }

    public function syncLastStatementMeta(PDOStatement $statement, int $affectedRows) {
        $this->affectedRowsValue = $affectedRows;
        try {
            $lastInsertId = $this->pdo->lastInsertId();
            $this->lastInsertIdValue = $lastInsertId !== false ? (int) $lastInsertId : 0;
        } catch (Throwable $exception) {
            $this->lastInsertIdValue = 0;
        }
    }

    public function __get($name) {
        return match ($name) {
            'insert_id' => $this->lastInsertIdValue,
            'affected_rows' => $this->affectedRowsValue,
            default => null,
        };
    }
}

if (!function_exists('createDatabaseConnection')) {
    function createDatabaseConnection() {
        if (DB_USER === '' || DB_NAME === '') {
            throw new Exception('Database environment variables are missing.');
        }

        $driver = strtolower((string) DB_DRIVER);
        if (!in_array($driver, ['mysql', 'pgsql'], true)) {
            throw new Exception('Unsupported database driver: ' . $driver);
        }

        if ($driver === 'pgsql') {
            $dsn = sprintf(
                'pgsql:host=%s;port=%d;dbname=%s;sslmode=%s',
                DB_HOST,
                DB_PORT,
                DB_NAME,
                DB_SSLMODE !== '' ? DB_SSLMODE : 'require'
            );
        } else {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
        }

        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => true,
        ]);

        if ($driver === 'pgsql') {
            $pdo->exec("SET TIME ZONE '+03:00'");
        } else {
            $pdo->exec("SET time_zone = '+03:00'");
        }

        return new DbConnectionCompat($pdo, $driver);
    }
}
?>
