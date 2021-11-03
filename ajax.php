<?php
require_once 'private/config.php';
require_once 'private/Database.php';
require_once 'private/TODODatabase.php';
require_once 'authorize.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['action'])) {
        // Send 400 Bad Request
        http_response_code(400);
        echo "'action' was not defined";
    } else if ($_POST['action'] === 'create_token') {
        echo login($_POST['username'] ?? '', $_POST['password'] ?? '', get_private_key_path(), get_private_key_passphrase());
    } else if (($token = get_token_from_header()) != null && ($data = authorize_token($token)) != null) {
        $user_id = $data['sub'];
        $err_str = 'Unknown action';
        $not_impl = 'Not implemented';
        $db = new Database();
        $todo_db = new TODODatabase($db, $user_id);

        try {
            $response = match ($_POST['action']) {
                'create_project' => $not_impl,
                'get_user_projects' => json_encode($todo_db->get_user_projects()),
                'get_tasks', 'get_project' => json_encode($todo_db->get_tasks(
                    intval($_POST['projectId'])
                )),
                'delete_project' => $not_impl,
                'create_task' => json_encode(['taskId' => $todo_db->create_task(
                    intval($_POST['projectId']),
                    $_POST['taskName'],
                    $_POST['taskContent'],
                    intval($_POST['taskDuration']),
                    $_POST['taskDueDate'],
                )]),
                'get_task' => json_encode($todo_db->get_task(
                    intval($_POST['taskId'])
                )),
                'move_task' => json_encode(['wasSuccessful' => $todo_db->move_task(
                    intval($_POST['taskId']),
                    $_POST['newProjectId'])
                ]),
                'update_task' => json_encode(['wasSuccessful' => $todo_db->update_task(
                    intval($_POST['taskId']),
                    $_POST['taskName'] ?? null,
                    $_POST['taskContent'] ?? null,
                    intval($_POST['taskDuration'] ?? null),
                    $_POST['taskDueDate'] ?? null,
                    isset($_POST['taskName']),
                    isset($_POST['taskContent']),
                    isset($_POST['taskDuration']),
                    isset($_POST['taskDueDate']))
                ]),
                'update_task_name' => json_encode(['wasSuccessful' => $todo_db->update_task_name(
                    intval($_POST['taskId']),
                    $_POST['taskName'])
                ]),
                'delete_task' => json_encode(['wasSuccessful' => $todo_db->delete_task(
                    intval($_POST['taskId']),
                    filter_var($_POST['deletePermanently'], FILTER_VALIDATE_BOOLEAN) ?? false)
                ]),
                default => $err_str,
            };

            if ($response === $err_str) {
                // Send 400 Bad Request
                http_response_code(400);
            } elseif ($response === $not_impl) {
                http_response_code(503);
            }

            echo $response;
        } catch (UnauthorizedException $e) {
            // HTTP code for unauthorized
            http_response_code(403);
            echo $e->getMessage();
        }
    } else {
        if (get_token_from_header() == null) {
            // Send 401 Unauthorized
            http_response_code(401);
            header('WWW-Authenticate: Bearer realm = ' . $_SERVER['SERVER_NAME'] . '"/api"');
            echo 'Missing token. Example: Authorization: Bearer TOKEN';
        } else {
            // Send 403 Forbidden
            http_response_code(403);
            echo 'Invalid token';
        }
    }
}

//if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
//    echo 'Preflight OK';
//    exit;
//}

function login(string $username, string $password, string $private_key_file_path, string $passphrase): ?string {
    if (is_string_empty($username)) {
        return 'Username is empty';
    } else if (is_string_empty($password)) {
        return 'Password is empty';
    } else if (($response = create_token($username, $password, $private_key_file_path, $passphrase)) != null) {
        return $response;
    } else {
        // Send 400 Bad Request
        http_response_code(400);
        // With 401 we need to send username and password with Auth header and base64 encoding
        // header('WWW-Authenticate: Basic realm = "' . $_SERVER['SERVER_NAME'] . '/api"');
        return 'Invalid credentials';
    }
}
