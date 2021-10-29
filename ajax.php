<?php
require_once 'private/config.php';
require_once 'private/Database.php';
require_once 'private/TODODatabase.php';

// TODO When login works, this needs to be removed, so this is for testing only
$_SESSION['user_id'] = 2;

if (isset($_POST['action'])) {
    $err_str = 'Unknown action';
    $user_id = get_current_user_id();
    $db = new Database();
    $todo_db = new TODODatabase($db, $user_id);
    check_permissions($user_id);

    $response = match ($_POST['action']) {
        'get_user_projects' => json_encode($todo_db->get_user_projects()),
        'get_project', 'get_tasks' => json_encode($todo_db->get_tasks(
            intval($_POST['projectId'])
        )),
        'create_task' => json_encode(array('taskId' => $todo_db->create_task(
            intval($_POST['projectId']),
            $_POST['taskName'],
            $_POST['taskContent'],
            intval($_POST['taskDuration']),
            $_POST['taskDueDate'],
        ))),
        'get_task' => $todo_db->get_task(
            intval($_POST['taskId'])
        ),
        'update_task_name' => json_encode(array('wasSuccessful' => $todo_db->update_task_name(
            intval($_POST['taskId']),
            $_POST['taskName']))),
        default => $err_str,
    };

    if ($response === $err_str) {
        // Send 400 Bad Request
        http_response_code(400);
    }

    echo $response;
} else {
    http_response_code(400);
    echo "'action' was not defined";
}

function equals_current_user_id(?int $user_id): bool {
    return !empty($user_id) && $user_id === get_current_user_id();
}

function get_current_user_id(): int {
    if (!empty($_SESSION['user_id']) && ($user_id = intval($_SESSION['user_id'])) > 0) {
        return $user_id;
    }
    throw new RuntimeException('Could not determine current user\'s ID');
}

/**
 * Check if user is authorized.
 */
function check_permissions(int $user_id, bool $should_throw = false): bool {
    $is_user_id_valid = equals_current_user_id($user_id);
    if ($should_throw) {
        throw new RuntimeException('Unauthorized');
    } else {
        return $is_user_id_valid;
    }
}

function is_string_empty(?string $str): bool {
    return !isset($str) || $str == null || strlen(trim($str)) === 0;
}
