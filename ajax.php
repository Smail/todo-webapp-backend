<?php
require_once 'private/config.php';
require_once 'private/Database.php';

// TODO When login works, this needs to be removed, so this is for testing only
$_SESSION['user_id'] = 2;

if (isset($_POST['action'])) {
    $unknownAction = 'Unknown action';
    $db = new Database();
    $response = match ($_POST['action']) {
        'get_all_projects', 'get_user_projects' => get_user_projects($db, $_SESSION['user_id']),
        'get_project' => get_project($db, $_SESSION['user_id'], $_POST['projectId']),
        'get_tasks' => get_tasks($db, $_SESSION['user_id'], $_POST['projectId']),
        'update_task_name' => update_task_name($db, $_SESSION['user_id'], $_POST['taskId'], $_POST['taskName']),
        'create_task' => create_task($db,
            $userId = $_SESSION['user_id'],
            $projectId = $_POST['projectId'],
            $taskName = $_POST['taskName'],
            $taskContent = $_POST['taskContent'],
            $taskDuration = $_POST['taskDuration'],
            $taskDueDate = $_POST['taskDueDate'],
        ),
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

function equals_current_user_id(?int $userId): bool {
    return !empty($_SESSION['user_id']) && !empty($userId) && $_SESSION['user_id'] === $userId;
}

function is_string_empty(?string $str): bool {
    return !isset($str) || $str == null || strlen(trim($str)) === 0;
}

/**
 * @param Database $db
 * @param int $userId
 * @param int $projectId
 * @param string $taskName
 * @param string $taskContent
 * @param string $taskDuration
 * @param string $taskDueDate
 * @return string|null
 */
function create_task(Database $db, int $userId, int $projectId, string $taskName,
                     string   $taskContent = '', string $taskDuration = '', string $taskDueDate = ''): ?string {
    if ($userId < 1) {
        throw new InvalidArgumentException('User ID is less than 1');
    }
    if ($projectId < 1) {
        throw new InvalidArgumentException('Project ID is less than 1');
    }
    if (is_string_empty($taskName)) {
        throw new InvalidArgumentException('Task name cannot be empty');
    }
    // Does current user own the project ID
    if (equals_current_user_id(get_project_owner_id($db, $projectId))) {
        http_response_code(403);
        // throw new RuntimeException('User does not own this project');
        return null;
    }

    $get_ids_query =
        "SELECT TaskId FROM ProjectTasks
         WHERE ProjectId = :projectId AND TaskName = :taskName AND TaskContent = :taskContent
           AND Duration = :taskDuration AND DueDate = :taskDueDate";
    $bindings = [
        ':userId' => $userId,
        ':projectId' => $projectId,
        ':taskName' => $taskName,
        ':taskContent' => $taskContent,
        ':taskDuration' => $taskDuration,
        ':taskDueDate' => $taskDueDate,
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
            "INSERT INTO ProjectTasks(ProjectId, TaskName, TaskContent, Duration, DueDate)
         VALUES (:projectId, :taskName, :taskContent, :taskDuration, :taskDueDate)",
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
            return json_encode($diff);
        } else {
            throw new RuntimeException('Concurrent insert or delete');
        }
    } catch (Exception) {
        $db->rollback_transaction();
        http_response_code(501);
        return null;
    }
}

/**
 * Returns the user ID of the user, that owns (i.e. created) that project / project ID.
 *
 * @param Database $db
 * @param int $projectId
 * @return int|null Returns user ID or null of project ID does not exist.
 */
function get_project_owner_id(Database $db, int $projectId): ?int {
    $stmt = $db->create_stmt(
        'SELECT UserId
         FROM Project
         WHERE ProjectId = :projectId',
        [':projectId' => $projectId],
    );
    return ($row = $stmt->execute()?->fetchArray(SQLITE3_NUM)) ? $row[0] : null;
}

function update_task_name(Database $db, int $userId, int $taskId, string $newTaskName): bool {
    throw new RuntimeException('Not implemented yet');
    $db->begin_transaction();
    $stmt = $db->create_stmt(
        'UPDATE ProjectTasks
         SET TaskName = :taskName
         WHERE TaskId = :taskId',
        [':taskId' => $taskId, ':taskName' => $newTaskName],
    );

    $stmt->execute();
    $stmt->close();
    return false;
}

function get_user_projects(Database $db, $userId): string {
    $stmt = $db->create_stmt(
        "SELECT ProjectId AS id, ProjectName AS name
         FROM Project
         WHERE Project.UserId = :userId",
        [
            ':userId' => $userId
        ]
    );

    return query_result_to_json($stmt->execute());
}

function get_project(Database $db, $userId, $projectId): string {
    return "";
}

function get_tasks(Database $db, $userId, $projectId): string {
    $stmt = $db->create_stmt(
        "SELECT TaskId AS id, TaskName AS name, TaskContent AS content, Duration AS duration, DueDate AS dueDate
         FROM ProjectTasks JOIN Project on ProjectTasks.ProjectId = Project.ProjectId
         WHERE ProjectTasks.ProjectId = :projectId AND UserId = :userId",
        [
            ':userId' => $userId,
            ':projectId' => $projectId
        ]);

    return query_result_to_json($stmt->execute());
}

function query_result_to_json(SQLite3Result $result): string {
    $arr = array();
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $arr[] = $row;
    }

    return json_encode($arr);
}
