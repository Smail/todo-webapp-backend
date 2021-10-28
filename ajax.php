<?php
require_once 'private/config.php';
require_once 'private/Database.php';

if (isset($_POST['action'])) {
    // TODO authentication does not work yet
    //if (!is_logged_in()) {
    //    http_response_code(401);
    //    echo json_encode(array('Error' => 'Not logged in'));
    //} else {
    $unknownAction = 'Unknown action';
    $response = match ($_POST['action']) {
        // TODO change 2 to $_SESSION['user_id']
        'get_all_projects' => get_all_projects(2),
        'get_project' => get_project(2, $_POST['projectId']),
        'get_tasks' => get_tasks(2, $_POST['projectId']),
        'update_task_name' => update_task_name(2, $_POST['taskId'], $_POST['taskName']),
        'create_task' => create_task(),
        default => $unknownAction,
    };

    if ($response === $unknownAction) {
        // Send 400 Bad Request
        http_response_code(400);
    }

    echo $response;
//}
} else {
    http_response_code(400);
    echo "'action' was not defined";
}

function create_task(): string {
    $projectId = $_POST['projectId'] ?? null;
    $taskName = $_POST['taskName'] ?? null;
    $taskContent = $_POST['taskContent'] ?? null;
    $taskDuration = $_POST['taskDuration'] ?? null;
    $taskDueDate = $_POST['taskDueDate'] ?? null;
    $bindings = [
        ':projectId' => $projectId,
        ':taskName' => $taskName,
        ':taskContent' => $taskContent,
        ':taskDuration' => $taskDuration,
        ':taskDueDate' => $taskDueDate,
    ];
    $get_ids_query =
        "SELECT TaskId FROM ProjectTasks
         WHERE ProjectId = :projectId AND TaskName = :taskName AND TaskContent = :taskContent
           AND Duration = :taskDuration AND DueDate = :taskDueDate";
    $db = new Database();

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
        $db->rollback_transaction();
        http_response_code(501);
        return 'Concurrent insert or delete';
    }
}

function get_all_projects($userId): string {
    $db = new Database();
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

function get_project($userId, $projectId): string {
    return "";
}

function get_tasks($userId, $projectId): string {
    $db = new Database();
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
