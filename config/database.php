<?php

class Database {
    private static ?PDO $instance = null;

    private function __construct() {}
    private function __clone() {}

    private static function getEnv(string $key, string $default = ''): string {
        $val = getenv($key);
        if ($val !== false && $val !== '') return $val;
        return $_ENV[$key] ?? $default;
    }

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            // Lee los datos de conexión exclusivamente desde las variables de entorno
            $host     = self::getEnv('MYSQLHOST', 'localhost');
            $dbname   = self::getEnv('MYSQLDATABASE', '');
            $user     = self::getEnv('MYSQLUSER', 'root');
            $password = self::getEnv('MYSQLPASSWORD', '');
            $port     = self::getEnv('MYSQLPORT', '3306');
            $charset  = 'utf8mb4';

            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            try {
                self::$instance = new PDO($dsn, $user, $password, $options);
            } catch (PDOException $e) {
                http_response_code(500);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => 'Error de conexión a la base de datos.',
                    'error'   => $e->getMessage()
                ]);
                exit;
            }
        }
        return self::$instance;
    }
}
