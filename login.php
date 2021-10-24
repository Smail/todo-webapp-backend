<?php
require_once 'private/config.php';

header('Content-Type: application/json;charset=utf-8');

if (!is_logged_in()) {
    $db = new SQLite3('database/db.sqlite3');
    $db->enableExceptions(true);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = SQLite3::escapeString(trim($_POST['username']));
        $password = SQLite3::escapeString(trim($_POST['password']));

        if (!empty($username) && !empty($password)) {
            try {
                $stmt = $db->prepare("SELECT COUNT(*) FROM User WHERE Username = :username");
                $stmt->bindValue(':username', $username);
                $res = $stmt->execute();
                $count = $res->fetchArray()[0]; // Retrieve the value of COUNT(*)

                if ($count == 1) {
                    $stmt = $db->prepare("SELECT * FROM User WHERE Username = :username");
                    $stmt->bindValue(':username', $username);
                    $res = $stmt->execute();
                    $row = $res->fetchArray();
                    $hashed_password = $row['PasswordHash'];

                    if (password_verify($password, $hashed_password)) {
                        // Paranoia
                        unset($hashed_password, $row['password']);

                        // Store data in session variables
                        $_SESSION['user_id'] = $row['UserId'];
                        $_SESSION['username'] = $row['Username'];
                        $_SESSION['first_name'] = $row['FirstName'];
                        $_SESSION['last_name'] = $row['LastName'];
                        $_SESSION['is_logged_in'] = true;

                        echo json_user_data();
                    } else {
                        echo json_encode(array('Error' => 'Invalid password'));
                    }
                } else if ($count == 0) {
                    echo json_encode(array('Error' => "Unknown username: $username"));
                } else {
                    echo json_encode(array('Error' => 'Unknown error 0x0001'));
                }
            } catch (Exception $e) {
                echo json_encode(array('Error' => 'Unknown error 0x0002'));
            } finally {
                $db->close();
            }
        } else {
            $errors[] = empty($username) ? 'Username is empty' : '';
            $errors[] = empty($password) ? 'Password is empty' : '';
            echo json_encode(array('Error' => $errors));
        }
    } else {
        echo json_encode(array('Error' => 'Not a post request'));
    }
} else {
    echo json_user_data();
}

function json_user_data() : string {
    return json_encode(array(
        'userId' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'firstName' => $_SESSION['first_name'],
        'lastName' => $_SESSION['last_name'],
    ));
}