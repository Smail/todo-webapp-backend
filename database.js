const fs = require("fs");
const bcrypt = require("bcrypt");
const Database = require("better-sqlite3");
const DB_PATH = "database/db.sqlite3";

const initDatabase = !fs.existsSync(DB_PATH);
const db = new Database(DB_PATH);
process.on('exit', () => db.close());
process.on('SIGHUP', () => process.exit(128 + 1));
process.on('SIGINT', () => process.exit(128 + 2));
process.on('SIGTERM', () => process.exit(128 + 15));

// initiate database on first time use
if (initDatabase) {
    const createTablesQuery = fs.readFileSync("database/sql/CreateTables.sql", "utf8");

    console.log("Init database");
    db.exec(createTablesQuery);
}

function createTask(projectId, task) {
    return db.transaction(() => {
        let stmt = db.prepare(
            `INSERT INTO Task(TaskName, TaskContent, Duration, DueDate)
             VALUES (:name, :content, :duration, :dueDate)`
        );

        const info = stmt.run({name: task.name, content: task.content, duration: task.duration, dueDate: task.dueDate});
        if (info.changes === 1) {
            const taskId = info.lastInsertRowid;

            stmt = db.prepare(
                `INSERT INTO ProjectTask(ProjectId, TaskId)
                 VALUES (:projectId, :taskId)`
            );
            stmt.run({projectId, taskId});

            return taskId;
        }
        return -1;
    })();
}

function getProjects(userId) {
    const stmt = db.prepare(
        `SELECT P.ProjectId AS id, ProjectName AS name
         FROM Project P
                  JOIN UserProject UP on P.ProjectId = UP.ProjectId
         WHERE UP.UserId = :userId`);
    return stmt.all({userId});
}

function getTasks(userId, projectId) {
    return db.prepare(
        `SELECT T.TaskId      AS id,
                T.TaskName    AS name,
                T.TaskContent AS content,
                T.Duration    AS duration,
                T.DueDate     AS dueDate
         FROM UserProject UP
                  JOIN ProjectTask PT on UP.ProjectId = PT.ProjectId
                  JOIN Task T on PT.TaskId = T.TaskId
         WHERE PT.ProjectId = :projectId
           AND UP.UserId = :userId`
    ).all({userId, projectId});
}

function getUserId(username, password) {
    const stmt = db.prepare(
        `SELECT UserId AS id, PasswordHash AS hash
         FROM User
         WHERE LOWER(Username) = LOWER(:username)`
    );
    const user = stmt.get({username});

    if (user != null && bcrypt.compareSync(password, user.hash.replace(/^\$2y(.+)$/i, '$2a$1'))) {
        return user.id;
    }

    throw new Error("Invalid credentials");
}

function ownsUserProject(userId, projectId) {
    return db.prepare(
        `SELECT COUNT(*) AS count
         FROM UserProject UP
         WHERE UP.UserId = :userId
           AND UP.ProjectId = :projectId`
    ).get({userId, projectId}).count === 1;
}

function ownsUserTask(userId, taskId) {
    return db.prepare(
        `SELECT COUNT(*) AS count
         FROM UserProject UP
                  JOIN ProjectTask PT on UP.ProjectId = PT.ProjectId
         WHERE UP.UserId = :userId
           AND PT.TaskId = :taskId`
    ).get({userId, taskId}).count === 1;
}

function runTransactionLimitRows(stmt, binding, rowLimit = 1) {
    return db.transaction(() => {
        const info = stmt.run(binding);

        if (info.changes > rowLimit) {
            throw Error("Too many rows affected");
        }

        return info.changes;
    })();
}

function getTask(userId, taskId) {
    return db.prepare(
        `SELECT TaskId      AS id,
                TaskName    AS name,
                TaskContent AS content,
                Duration    AS duration,
                DueDate     AS dueDate
         FROM Task
         WHERE TaskId = :taskId
           AND EXISTS(
                 SELECT UP.UserId
                 FROM UserProject UP
                          JOIN ProjectTask PT on UP.ProjectId = PT.ProjectId
                 WHERE UP.UserId = :userId
                   AND PT.TaskId = :taskId)`
    ).get({userId, taskId});
}

function moveTask(userId, taskId, newProjectId) {
    const stmt = db.prepare(
        `UPDATE ProjectTask
         SET ProjectId = :newProjectId
         WHERE TaskId = :taskId
           AND EXISTS(
                 SELECT UserId
                 FROM UserProject UP
                 WHERE UP.UserId = :userId
                   AND UP.ProjectId = :newProjectId)`
    );

    return runTransactionLimitRows(stmt, {userId, taskId, newProjectId}, 1) === 1;
}

function updateTask(userId, taskId, task) {
    const stmt = db.prepare(
        `UPDATE Task
         SET TaskName    = :name,
             TaskContent = :content,
             Duration    = :duration,
             DueDate     = :dueDate
         WHERE TaskId = :taskId
           AND EXISTS(
                 SELECT Up.UserId
                 FROM UserProject UP
                          JOIN ProjectTask PT on UP.ProjectId = PT.ProjectId
                 WHERE UP.UserId = :userId
                   AND PT.TaskId = :taskId)`
    );

    return runTransactionLimitRows(stmt, {
        userId,
        taskId,
        name: task.name,
        content: task.content,
        duration: task.duration,
        dueDate: task.dueDate,
    }, 1) === 1;
}

function deleteTask(userId, taskId) {
    const stmt = db.prepare(
        `DELETE
         FROM Task
         WHERE TaskId = :taskId
           AND EXISTS(
                 SELECT Up.UserId
                 FROM UserProject UP
                          JOIN ProjectTask PT on UP.ProjectId = PT.ProjectId
                 WHERE UP.UserId = :userId
                   AND PT.TaskId = :taskId)`
    );

    return runTransactionLimitRows(stmt, {
        userId,
        taskId,
    }, 1) === 1;
}

module.exports = {
    getUserId,
    createTask,
    getProjects,
    getTask,
    getTasks,
    moveTask,
    updateTask,
    deleteTask,
    ownsUserProject,
    ownsUserTask,
}
