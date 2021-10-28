<?php
require_once 'Database.php';

class User {
    private int $id = -1;
    private string $passwordHash;

    public function __construct(
        private string $username,
        private string $first_name,
        private string $last_name,
        string         $password,
    ) {
        $this->username = SQLite3::escapeString($this->username);
        $this->first_name = SQLite3::escapeString($this->first_name);
        $this->last_name = SQLite3::escapeString($this->last_name);
        $this->passwordHash = SQLite3::escapeString(password_hash($password, PASSWORD_DEFAULT));
    }

    public function insertIntoDatabase(): bool {
        if (User::existsUsernameInDatabase($this->username)) {
            return false;
        }

        $db = new Database();
        return $db->exec("INSERT INTO User(Username, PasswordHash, FirstName, LastName)
                          VALUES ('$this->username', '$this->passwordHash', '$this->first_name', '$this->last_name')");
    }

    public function getId(): int {
        if ($this->id >= 0) {
            return $this->id;
        }

        return ($this->id = User::getIdByUsername($this->username));
    }

    public static function getIdByUsername($username): int {
        $db = new Database();
        $res = $db->create_stmt('SELECT UserId FROM User WHERE Username = :username', [':username' => $username])->execute();

        if ($row = $res->fetchArray(SQLITE3_NUM)) {
            return $row[0];
        }
        return -1;

    }

    public static function existsUsernameInDatabase($username): bool {
        $db = new Database();
        $res = $db->create_stmt('SELECT COUNT(*) FROM User WHERE Username = :username', [':username' => $username])->execute();

        if ($row = $res->fetchArray(SQLITE3_NUM)) {
            return intval($row[0]) > 0;
        }
        return false;
    }
}
