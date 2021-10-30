<?php
require_once 'private/config.php';
require_once 'private/Database.php';
require_once 'private/TODODatabase.php';
require_once 'authorize.php';

if (!isset($_POST['action'])) {
    // Send 400 Bad Request
    http_response_code(400);
    echo "'action' was not defined";
} else if ($_POST['action'] === 'create_token') {
    login($_POST['username'] ?? '', $_POST['password'] ?? '', get_private_key_path(), get_private_key_passphrase());
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
            'get_project', 'get_tasks' => json_encode($todo_db->get_tasks(
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
            'update_task' => json_encode(['wasSuccessful' => $todo_db->update_task(
                intval($_POST['taskId']),
                $_POST['taskName'] ?? null,
                $_POST['taskContent'] ?? null,
                intval($_POST['taskDuration'] ?? null),
                $_POST['taskDueDate'] ?? null,
                isset($_POST['taskName']),
                isset($_POST['taskContent']),
                isset($_POST['taskDuration']),
                isset($_POST['taskDueDate']))]),
            'update_task_name' => json_encode(['wasSuccessful' => $todo_db->update_task_name(
                intval($_POST['taskId']),
                $_POST['taskName'])]),
            'delete_task' => $not_impl,
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
