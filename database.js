const fs = require("fs");
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

    db.exec(createTablesQuery);
}
