CREATE TABLE IF NOT EXISTS user
(
    user_id    INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
    username   TEXT    NOT NULL UNIQUE,
    password   TEXT    NOT NULL,
    first_name TEXT    NOT NULL,
    last_name  TEXT    NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- The projects a user has created
CREATE TABLE IF NOT EXISTS project
(
    project_id   INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id      INTEGER     NOT NULL,
    project_name TEXT UNIQUE NOT NULL,
    FOREIGN KEY (user_id) REFERENCES user (user_id)
);

-- The tasks that were created by the users
CREATE TABLE IF NOT EXISTS task
(
    task_id INTEGER PRIMARY KEY AUTOINCREMENT,
    name    TEXT NOT NULL,
    content TEXT
);

CREATE TABLE IF NOT EXISTS project_tasks
(
    project_id INTEGER NOT NULL,
    task_id    INTEGER NOT NULL,
    FOREIGN KEY (project_id) REFERENCES project (project_id),
    FOREIGN KEY (task_id) REFERENCES task (task_id),
    PRIMARY KEY (project_id, task_id)
);
