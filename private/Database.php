<?php

class Database {
    private SQLite3 $db;

    public function __construct(?string $path) {
        $path = $path ?? $_SERVER['DOCUMENT_ROOT'] . "/database/db.sqlite3";
        $this->db = new SQLite3($path);
        $this->db->enableExceptions(true);
    }

    public function create_stmt(string $query, array $valueBindings): SQLite3Stmt {
        $stmt = $this->db->prepare($query);

        foreach ($valueBindings as $key => $value) {
            if (!str_starts_with($key, ':')) {
                $key = ':' . $key;
            }
            $stmt->bindValue($key, $value);
        }
        return $stmt;
    }

    public function begin_transaction(): void {
        $this->db->exec('BEGIN TRANSACTION');
    }

    public function commit_transaction(): void {
        $this->db->exec('COMMIT TRANSACTION');
    }

    public function rollback_transaction(): void {
        $this->db->exec('ROLLBACK TRANSACTION');
    }

    public function exec($query, $is_transaction = true): bool {
        if (!$is_transaction) {
            return $this->db->exec($query);
        }

        $this->begin_transaction();

        try {
            $res = $this->db->exec($query);
            if ($res) {
                $this->commit_transaction();
                return true;
            }

            $this->rollback_transaction();
        } catch (Exception) {
            $this->rollback_transaction();
        }
        return false;
    }

    public function get_db(): SQLite3 {
        return $this->db;
    }

    public static function escape_strings(array $strings): array {
        $escaped_strings = [];
        foreach ($strings as $str) {
            $escaped_strings[$str] = SQLite3::escapeString($str);
        }
        return $escaped_strings;
    }
}
