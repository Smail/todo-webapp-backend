<?php
require_once 'private/Database.php';

class UnauthorizedException extends RuntimeException {}

class TODODatabase {
    public function __construct(
        private Database $db,
        private int      $user_id,
    ) {
    }

    /**
     * @param int $project_id
     * @param string $task_name
     * @param string $task_content
     * @param int|null $task_duration
     * @param string|null $task_due_date
     * @return string|null
     */
    public function create_task(int    $project_id, string $task_name,
                                string $task_content = '', ?int $task_duration = null, ?string $task_due_date = ''): ?string {
        if ($project_id < 1) {
            throw new InvalidArgumentException('Project ID is less than 1');
        }
        if (is_string_empty($task_name)) {
            throw new InvalidArgumentException('Task name cannot be empty');
        }
        // Does the current user own the given project ID
        if ($this->user_id !== TODODatabase::get_project_owner_id($this->db, $project_id)) {
            throw new UnauthorizedException('User does not own this project');
        }

        $get_ids_query = '
            SELECT TaskId FROM ProjectTasks
            WHERE ProjectId = :projectId AND TaskName = :taskName AND TaskContent = :taskContent
            AND Duration = :taskDuration AND DueDate = :taskDueDate
            AND EXISTS(
                SELECT *
                FROM Project P
                WHERE P.UserId = :userId
            )';
        $bindings = [
            ':userId' => $this->user_id,
            ':projectId' => $project_id,
            ':taskName' => $task_name,
            ':taskContent' => $task_content,
            ':taskDuration' => $task_duration,
            ':taskDueDate' => $task_due_date,
        ];

        // Check if there exists an identical entry
        $stmt = $this->db->create_stmt($get_ids_query, $bindings);

        // Add all existing IDs to an array, so that we can later check which ID was added.
        $res = $stmt->execute();
        $existing_ids = [];
        while ($row = $res->fetchArray(SQLITE3_NUM)) {
            $existing_ids[] = $row[0];
        }
        $stmt->close();

        // Insert the new task into the database
        try {
            $this->db->begin_transaction();
            $stmt = $this->db->create_stmt('
                INSERT INTO ProjectTasks(ProjectId, TaskName, TaskContent, Duration, DueDate)
                VALUES (:projectId, :taskName, :taskContent, :taskDuration, :taskDueDate)',
                $bindings,
            );
            $stmt->execute();
            $stmt->close();

            // Fetch all task IDs with identical entries again and compare them to the previously fetched IDs in $existing_ids.
            // Return the ID, which was previously not in the database, i.e. which is not in $existing_ids.
            $stmt = $this->db->create_stmt($get_ids_query, $bindings);
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
                $this->db->commit_transaction();
                return array_pop($diff);
            } else {
                throw new RuntimeException('Concurrent insert or delete');
            }
        } catch (Exception) {
            $this->db->rollback_transaction();
            // Not implemented yet response code
            http_response_code(501);
            return null;
        }
    }

    public function update_task_name(int $task_id, string $new_task_name): bool {
        if ($task_id < 1) {
            throw new InvalidArgumentException('Task ID is less than 1');
        }

        // Update task and check if user even owns this task ID
        $stmt = $this->db->create_stmt('
            UPDATE ProjectTasks
            SET TaskName = :taskName
            WHERE TaskId = :taskId
            AND EXISTS(
                SELECT *
                FROM ProjectTasks T
                    JOIN Project P on P.ProjectId = T.ProjectId
                WHERE P.UserId = :userId
                AND T.TaskId = :taskId
            )',
            [':userId' => $this->user_id, ':taskId' => $task_id, ':taskName' => $new_task_name],
        );

        try {
            $this->db->begin_transaction();
            $stmt->execute();
            $this->db->commit_transaction();

            return true;
        } catch (Exception) {
            $this->db->rollback_transaction();
        }
        return false;
    }

    public function get_user_projects(): array {
        $stmt = $this->db->create_stmt('
            SELECT ProjectId AS id, ProjectName AS name
            FROM Project
            WHERE Project.UserId = :userId',
            [':userId' => $this->user_id]
        );
        return Database::fetch_all($stmt->execute(), SQLITE3_ASSOC);
    }

    public function get_task(int $task_id): ?string {
        // TODO either check if user owns task by if ($this->...)... or by SQL EXISTS
        // Does the current user own the given task ID
        if ($this->user_id !== TODODatabase::get_task_owner_id($this->db, $task_id)) {
            throw new UnauthorizedException('User does not own this task');
        }
        $stmt = $this->db->create_stmt('
            SELECT *
            FROM ProjectTasks
            WHERE TaskId=:taskId
                AND EXISTS(
                    SELECT *
                    FROM ProjectTasks T
                        JOIN Project P on P.ProjectId = T.ProjectId
                    WHERE P.UserId = :userId
                    AND T.TaskId = :taskId
                )',
            [':taskId' => $task_id]
        );
        return Database::get_first_result_row_if_exists($stmt);
    }

    public function get_tasks(int $project_id): array {
        $stmt = $this->db->create_stmt('
            SELECT TaskId AS id, TaskName AS name, TaskContent AS content, Duration AS duration, DueDate AS dueDate
            FROM ProjectTasks JOIN Project on ProjectTasks.ProjectId = Project.ProjectId
            WHERE ProjectTasks.ProjectId = :projectId AND UserId = :userId',
            [':userId' => $this->user_id, ':projectId' => $project_id]
        );

        return Database::fetch_all($stmt->execute(), SQLITE3_ASSOC);
    }

    public static function get_task_owner_id(Database $db, int $task_id): ?int {
        if ($task_id < 1) {
            // IDs are always >= 1
            return null;
        }
        $stmt = $db->create_stmt('
            SELECT UserId
            FROM ProjectTasks T JOIN Project P on P.ProjectId = T.ProjectId
            WHERE T.TaskId = :taskId',
            [':taskId' => $task_id]
        );
        // intval returns 0 on failure and on intval(null). Since all IDs in the database are greater than 0, we don't have
        // a problem distinguishing between actual ID = 0 and failure.
        return ($owner_id = intval(Database::get_first_result_row_if_exists($stmt))) > 1 ? $owner_id : null;
    }

    /**
     * Returns the user ID of the user, that owns (i.e. created) that project / project ID.
     *
     * @param Database $db
     * @param int $project_id
     * @return int|null Returns user ID or null of project ID does not exist.
     */
    public static function get_project_owner_id(Database $db, int $project_id): ?int {
        $stmt = $db->create_stmt('
            SELECT UserId
            FROM Project
            WHERE ProjectId = :projectId',
            [':projectId' => $project_id],
        );
        // intval returns 0 on failure and on intval(null). Since all IDs in the database are greater than 0, we don't have
        // a problem distinguishing between actual ID = 0 and failure.
        return ($owner_id = intval(Database::get_first_result_row_if_exists($stmt))) > 1 ? $owner_id : null;
    }

    private function update_task_helper_get_sql_set_stmt(?string $value, bool $should_update,
                                                         string  $sql_column, string $binding_name): string {
        if ($should_update && !isset($value) || empty($sql_column)) {
            throw new InvalidArgumentException();
        }
        return $should_update ? "$sql_column = $binding_name" : '';
    }

    /**
     * Generic task update function.
     *
     * @param int $task_id
     * @param string|null $task_name
     * @param string|null $task_content
     * @param int|null $task_duration
     * @param string|null $task_due_date
     * @param bool $update_name
     * @param bool $update_content
     * @param bool $update_duration
     * @param bool $update_due_date
     * @return bool true if task was successfully updated.
     */
    public function update_task(int     $task_id,
                                ?string $task_name, ?string $task_content, ?int $task_duration, ?string $task_due_date,
                                bool    $update_name, bool $update_content, bool $update_duration, bool $update_due_date): bool {
        if ($task_id < 1 ||
            $update_name && !isset($task_name) ||
            $update_content && !isset($task_content) ||
            $update_duration && !isset($task_duration) ||
            $update_due_date && !isset($task_due_date)) {
            throw new InvalidArgumentException();
        }

        $set_name_sql = $this->update_task_helper_get_sql_set_stmt($task_name, $update_name,
            'TaskName', ':taskName');
        $set_content_sql = $this->update_task_helper_get_sql_set_stmt($task_content, $update_content,
            'TaskContent', ':taskContent');
        $set_duration_sql = $this->update_task_helper_get_sql_set_stmt($task_duration, $update_duration,
            'Duration', ':taskDuration');
        $set_due_date_sql = $this->update_task_helper_get_sql_set_stmt($task_due_date, $update_due_date,
            'DueDate', ':taskDueDate');

        // Update task and check if user even owns this task ID
        $stmt = $this->db->create_stmt("
            UPDATE ProjectTasks
            SET $set_name_sql $set_content_sql $set_duration_sql $set_due_date_sql
            WHERE TaskId = :taskId
            AND EXISTS(
                SELECT *
                FROM ProjectTasks T
                    JOIN Project P on P.ProjectId = T.ProjectId
                WHERE P.UserId = :userId
                AND T.TaskId = :taskId
            )",
            [
                ':userId' => $this->user_id,
                ':taskName' => $task_name,
                ':taskContent' => $task_content,
                ':taskDuration' => $task_duration,
                ':taskDueDate' => $task_due_date,
            ],
        );

        try {
            $this->db->begin_transaction();
            $stmt->execute();
            $this->db->commit_transaction();

            return true;
        } catch (Exception) {
            $this->db->rollback_transaction();
        }
        return false;
    }
}