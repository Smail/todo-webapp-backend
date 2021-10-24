CREATE TABLE IF NOT EXISTS User
(
    UserId       INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
    Username     TEXT    NOT NULL UNIQUE,
    PasswordHash TEXT    NOT NULL,
    FirstName    TEXT    NOT NULL,
    LastName     TEXT    NOT NULL,
    CreatedAt    DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS Project
(
    ProjectId   INTEGER PRIMARY KEY AUTOINCREMENT,
    UserId      INTEGER NOT NULL,
    ProjectName TEXT    NOT NULL,
    FOREIGN KEY (UserId) REFERENCES User (UserId)
);

CREATE TABLE IF NOT EXISTS ProjectTasks
(
    TaskId      INTEGER PRIMARY KEY AUTOINCREMENT,
    ProjectId   INTEGER NOT NULL,
    TaskName    TEXT    NOT NULL,
    TaskContent TEXT,
    Duration    INTEGER, -- Duration in minutes
    DueDate     DATE,
    FOREIGN KEY (ProjectId) REFERENCES Project (ProjectId)
);
