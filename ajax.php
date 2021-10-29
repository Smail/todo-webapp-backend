<?php
require_once 'private/config.php';
require_once 'private/Database.php';

// TODO When login works, this needs to be removed, so this is for testing only
$_SESSION['user_id'] = 2;

if (isset($_POST['action'])) {
    $unknownAction = 'Unknown action';
    $db = new Database();
    $response = match ($_POST['action']) {
        'get_user_projects' => get_user_projects($db, $_SESSION['user_id']),
        'get_project' => get_project($db, $_SESSION['user_id'], $_POST['projectId']),
        'get_task' => get_task($db, $_SESSION['user_id'], $_POST['taskId']),
        'get_tasks' => get_tasks($db, $_SESSION['user_id'], $_POST['projectId']),
        'update_task_name' => json_encode(array('wasSuccessful' => update_task_name($db,
            $userId = $_SESSION['user_id'],
            $taskId = $_POST['taskId'],
            $newTaskName = $_POST['taskName']))),
        'create_task' => json_encode(array('taskId' => create_task($db,
            $userId = $_SESSION['user_id'],
            $projectId = $_POST['projectId'],
            $taskName = $_POST['taskName'],
            $taskContent = $_POST['taskContent'],
            $taskDuration = $_POST['taskDuration'],
            $taskDueDate = $_POST['taskDueDate'],
        ))),
        default => $unknownAction,
    };

    if ($response === $unknownAction) {
        // Send 400 Bad Request
        http_response_code(400);
    }

    echo $response;
} else {
    http_response_code(400);
    echo "'action' was not defined";
}

function equals_current_user_id(?int $user_id): bool {
    return !empty($_SESSION['user_id']) && !empty($user_id) && $_SESSION['user_id'] === $user_id;
}

function is_string_empty(?string $str): bool {
    return !isset($str) || $str == null || strlen(trim($str)) === 0;
}

/**
 * @param Database $db
 * @param int $user_id
 * @param int $project_id
 * @param string $task_name
 * @param string $task_content
 * @param string $task_duration
 * @param string $task_due_date
 * @return string|null
 */
function create_task(Database $db, int $user_id, int $project_id, string $task_name,
                     string   $task_content = '', string $task_duration = '', string $task_due_date = ''): ?string {
    if ($project_id < 1) {
        throw new InvalidArgumentException('Project ID is less than 1');
    }
    if (is_string_empty($task_name)) {
        throw new InvalidArgumentException('Task name cannot be empty');
    }
    // Does the current user own the given project ID
    if (!equals_current_user_id($user_id) || $user_id !== get_project_owner_id($db, $project_id)) {
        http_response_code(403);
        // throw new RuntimeException('User does not own this project');
        return 'User does not own this project';
    }

    $get_ids_query =
        'SELECT TaskId FROM ProjectTasks
         WHERE ProjectId = :projectId AND TaskName = :taskName AND TaskContent = :taskContent
           AND Duration = :taskDuration AND DueDate = :taskDueDate';
    $bindings = [
        ':userId' => $user_id,
        ':projectId' => $project_id,
        ':taskName' => $task_name,
        ':taskContent' => $task_content,
        ':taskDuration' => $task_duration,
        ':taskDueDate' => $task_due_date,
    ];

    // Check if there exists an identical entry
    $stmt = $db->create_stmt($get_ids_query, $bindings);

    // Add all existing IDs to an array, so that we can later check which ID was added.
    $res = $stmt->execute();
    $existing_ids = [];
    while ($row = $res->fetchArray(SQLITE3_NUM)) {
        $existing_ids[] = $row[0];
    }
    $stmt->close();

    // Insert the new task into the database
    try {
        $db->begin_transaction();
        $stmt = $db->create_stmt(
            'INSERT INTO ProjectTasks(ProjectId, TaskName, TaskContent, Duration, DueDate)
             VALUES (:projectId, :taskName, :taskContent, :taskDuration, :taskDueDate)',
            $bindings,
        );
        $stmt->execute();
        $stmt->close();

        // Fetch all task IDs with identical entries again and compare them to the previously fetched IDs in $existing_ids.
        // Return the ID, which was previously not in the database, i.e. which is not in $existing_ids.
        $stmt = $db->create_stmt($get_ids_query, $bindings);
        $res = $stmt->execute();
        $ids = [];
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $ids[] = $row['TaskId'];
        }
        $stmt->close();
        $diff = array_diff($ids, $existing_ids);

        // $diff should only contain exactly element. If there are more elements, then this means, that an identical request
        // was sent simultaneously to the server and the other process was faster. Essentially, someone inserted or deleted
        // a task in the meantime, and we don't want to allow something like this and will abort here and rollback.
        // I am not sure if this is even possible with transactions, since there are various conflicting sources.
        $length = count($diff);
        if ($length === 1) {
            $db->commit_transaction();
            return array_pop($diff);
        } else {
            throw new RuntimeException('Concurrent insert or delete');
        }
    } catch (Exception) {
        $db->rollback_transaction();
        // Not implemented yet response code
        http_response_code(501);
        return null;
    }
}

/**
 * Returns the user ID of the user, that owns (i.e. created) that project / project ID.
 *
 * @param Database $db
 * @param int $project_id
 * @return int|null Returns user ID or null of project ID does not exist.
 */
function get_project_owner_id(Database $db, int $project_id): ?int {
    $stmt = $db->create_stmt(
        'SELECT UserId
         FROM Project
         WHERE ProjectId = :projectId',
        [':projectId' => $project_id],
    );
    // intval returns 0 on failure and on intval(null). Since all IDs in the database are greater than 0, we don't have
    // a problem distinguishing between actual ID = 0 and failure.
    return ($owner_id = intval(Database::get_first_result_row_if_exists($stmt))) > 1 ? $owner_id : null;
}

function update_task_name(Database $db, int $user_id, int $task_id, string $new_task_name): bool {
    if ($user_id < 1) {
        throw new InvalidArgumentException('User ID is less than 1');
    }
    if ($task_id < 1) {
        throw new InvalidArgumentException('Project ID is less than 1');
    }

    $stmt = $db->create_stmt(
        'UPDATE ProjectTasks
         SET TaskName = :taskName
         WHERE TaskId = :taskId
            AND EXISTS(
                SELECT *
                FROM ProjectTasks T
                    JOIN Project P on P.ProjectId = T.ProjectId
                WHERE P.UserId = :userId
                AND T.TaskId = :taskId
            )',
        [':userId' => $user_id, ':taskId' => $task_id, ':taskName' => $new_task_name],
    );

    try {
        $db->begin_transaction();
        $stmt->execute();
        $db->commit_transaction();

        return true;
    } catch (Exception) {
        $db->rollback_transaction();
    }
    return false;
}

function get_user_projects(Database $db, $user_id): string {
    $stmt = $db->create_stmt(
        'SELECT ProjectId AS id, ProjectName AS name
         FROM Project
         WHERE Project.UserId = :userId',
        [':userId' => $user_id]
    );

    return query_result_to_json($stmt->execute());
}

function get_project(Database $db, $user_id, $project_id): string {
    // Not implemented yet response code
    http_response_code(501);
    return '';
}

function get_task_owner_id(Database $db, int $task_id): ?int {
    if ($task_id < 1) {
        // IDs are always >= 1
        return null;
    }
    $stmt = $db->create_stmt(
        'SELECT UserId
         FROM ProjectTasks T JOIN Project P on P.ProjectId = T.ProjectId
         WHERE T.TaskId = :taskId',
        [':taskId' => $task_id]
    );
    // intval returns 0 on failure and on intval(null). Since all IDs in the database are greater than 0, we don't have
    // a problem distinguishing between actual ID = 0 and failure.
    return ($owner_id = intval(Database::get_first_result_row_if_exists($stmt))) > 1 ? $owner_id : null;
}

function get_task(Database $db, int $user_id, int $task_id): ?string {
    // Does the current user own the given task ID
    if (!equals_current_user_id($user_id) || $user_id !== get_task_owner_id($db, $user_id)) {
        http_response_code(403);
        // throw new RuntimeException('User does not own this task');
        return 'User does not own this task';
    }
    $stmt = $db->create_stmt(
        'SELECT *
         FROM ProjectTasks
         WHERE TaskId=:taskId',
        [':taskId' => $task_id]
    );
    return Database::get_first_result_row_if_exists($stmt);
}

function get_tasks(Database $db, $user_id, $project_id): string {
    $stmt = $db->create_stmt(
        'SELECT TaskId AS id, TaskName AS name, TaskContent AS content, Duration AS duration, DueDate AS dueDate
         FROM ProjectTasks JOIN Project on ProjectTasks.ProjectId = Project.ProjectId
         WHERE ProjectTasks.ProjectId = :projectId AND UserId = :userId',
        [':userId' => $user_id, ':projectId' => $project_id]
    );

    return query_result_to_json($stmt->execute());
}

function query_result_to_json(SQLite3Result $result): string {
    $arr = array();
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $arr[] = $row;
    }

    return json_encode($arr);
}
