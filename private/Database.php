<?php

class Database {
    private SQLite3 $db;

    public function __construct(?string $path = null) {
        $path = $path ?? ($_SERVER['DOCUMENT_ROOT'] . "/database/db.sqlite3");
        $this->db = new SQLite3($path);
        $this->db->enableExceptions(true);
    }

    public function create_stmt(string $query, array $value_bindings): SQLite3Stmt {
        $stmt = $this->db->prepare($query);

        foreach ($value_bindings as $key => $value) {
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
            if ($this->db->exec($query)) {
                $this->commit_transaction();
                return true;
            } else {
                throw new RuntimeException('Could not exec statement');
            }
        } catch (Exception $e) {
            $this->rollback_transaction();
            throw new RuntimeException($e->getMessage());
        }
    }

    public function get_db(): SQLite3 {
        return $this->db;
    }

    public static function escape_string(string $str): string {
        return SQLite3::escapeString($str);
    }

    public static function escape_strings(array $strings): array {
        $escaped_strings = [];
        foreach ($strings as $str) {
            $escaped_strings[$str] = SQLite3::escapeString($str);
        }
        return $escaped_strings;
    }

    public static function get_result_first_column_if_exists(SQLite3Stmt $stmt): ?string {
        return ($row = $stmt->execute()?->fetchArray(SQLITE3_NUM)) ? $row[0] : null;
    }

    public static function fetch_all(SQLite3Result $result, $mode = SQLITE3_BOTH): array {
        $arr = [];
        if ($res = $result) {
            while ($row = $res->fetchArray($mode)) {
                $arr[] = $row;
            }
        }
        return $arr;
    }
}
