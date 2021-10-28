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
