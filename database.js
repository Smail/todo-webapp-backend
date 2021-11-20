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

function getProjects(userId) {
    const stmt = db.prepare(
        `SELECT P.ProjectId AS id, ProjectName AS name
         FROM Project P
                  JOIN UserProject UP on P.ProjectId = UP.ProjectId
         WHERE UP.UserId = :userId`);
    return stmt.all({userId});
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

module.exports = {
    getUserId,
    getProjects,
    ownsUserProject,
    ownsUserTask,
}
