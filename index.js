const express = require("express");
const jwt = require("jsonwebtoken");
const fs = require("fs");
const app = express();
const PORT = 8090;
const privateKey = fs.readFileSync("keys/token_rs256");
const publicKey = fs.readFileSync("keys/token_rs256.pub");
const {
    getProjects,
    getUserId,
    getTasks,
    moveTask,
    ownsUserProject,
    ownsUserTask,
    updateTask,
    getTask, deleteTask, deleteProject, createTask
} = require("./database");

app.use(express.json());

// Add headers before the routes are defined
app.use(function (req, res, next) {
    // Website you wish to allow to connect
    // TODO fix this; check proper way
    res.setHeader("Access-Control-Allow-Origin", "*");

    // Request methods you wish to allow
    res.setHeader("Access-Control-Allow-Methods", "GET, POST, OPTIONS, PUT, PATCH, DELETE");

    // Request headers you wish to allow
    res.setHeader("Access-Control-Allow-Headers", "X-Requested-With, Content-Type, Authorization");

    // Set to true if you need the website to include cookies in the requests sent
    // to the API (e.g. in case you use sessions)
    res.setHeader("Access-Control-Allow-Credentials", "true");

    // Pass to next layer of middleware
    next();
});

function createToken(username, password) {
    const userId = getUserId(username, password);
    const payload = {
        userId,
    };
    const key = {
        key: privateKey,
        passphrase: process.env.PASSPHRASE,
    };
    return jwt.sign(payload, key, {algorithm: "RS256"});

}

function retrieveToken(req, res, next) {
    const bearerHeader = req.headers["authorization"];

    if (typeof (bearerHeader) === "string") {
        const split = bearerHeader.split(" ", 2);

        if (split.length === 2 && split[0].toLowerCase() === "bearer") {
            req.token = split[1];
            next();
        } else {
            res.setHeader("WWW-Authenticate", "Bearer");
            res.status(401).send("Bearer announced but no token was provided");
        }
    } else {
        console.error("Bearer header not of type string");
        res.setHeader("WWW-Authenticate", "Bearer");
        res.sendStatus(401);
    }
}

function verifyToken(req, res, next) {
    jwt.verify(req.token, publicKey, function (err, decoded) {
        if (!err) {
            req.decodedPayload = decoded;

            if (typeof (decoded.userId) !== "number") {
                const errorMsg = "Argument 'userId' is not a number";
                console.error(errorMsg);
                res.status(401).send(errorMsg);
            } else {
                next();
            }
        } else {
            res.sendStatus(403);
        }
    });
}

function verifyProjectOwnership(req, res, next) {
    const userId = req.decodedPayload.userId;
    const projectId = req.params.projectId;

    if (typeof (projectId) !== "string" || !Number.parseInt(projectId)) {
        const errorMsg = "Argument 'projectId' is not a number: " + projectId;
        console.error(errorMsg);
        res.status(401).send(errorMsg);
    } else {
        if (ownsUserProject(userId, projectId)) {
            next();
        } else {
            const errorMsg = "User " + userId + " does not own project " + projectId;
            console.error(errorMsg);
            res.status(401).send(errorMsg);
        }
    }
}

function verifyTaskOwnership(req, res, next) {
    const userId = req.decodedPayload.userId;
    const taskId = req.params.taskId;

    if (ownsUserTask(userId, taskId)) {
        next();
    } else {
        const errorMsg = "User " + userId + " does not own task " + taskId;
        console.error(errorMsg);
        res.status(401).send(errorMsg);
    }
}

app.post("/login", (req, res) => {
    const basicHeader = req.headers["authorization"];
    let username = "";
    let password = "";

    if (typeof (basicHeader) === "string") {
        const split = basicHeader.split(" ", 2);

        if (split.length === 2 && split[0].toLowerCase() === "basic") {
            const credentialsBase64 = split[1];
            // Schema: username:password
            const credentialsEncoded = Buffer.from(credentialsBase64, 'base64').toString('utf-8');
            const credentials = credentialsEncoded.split(":");
            const send401 = function (message) {
                res.setHeader("WWW-Authenticate", 'Basic realm="Login / Token generation"');

                if (message !== undefined && message !== null && typeof (message) === "string" && message.length > 0) {
                    res.status(401).send(message);
                } else {
                    res.sendStatus(401);
                }
            };

            if (credentials != null && credentials.length === 2 &&
                typeof (credentials[0]) === "string" && typeof (credentials[1]) === "string" &&
                credentials[0].length > 0 && credentials[1].length > 0) {
                username = credentials[0];
                password = credentials[1];

                try {
                    res.send({token: createToken(username, password)});
                } catch (e) {
                    send401(e.message);
                }
            } else {
                send401("No credentials provided");
            }
        }
    }
});

app.get("/verify", retrieveToken, verifyToken, (req, res) => {
    res.sendStatus(200);
});

app.get("/projects", retrieveToken, verifyToken, (req, res) => {
    res.send(getProjects(req.decodedPayload.userId));
});

app.delete("/project/:projectId", retrieveToken, verifyToken, verifyProjectOwnership, (req, res) => {
    res.send(deleteProject(req.decodedPayload.userId, req.params.projectId));
});

app.get("/project/:projectId/tasks", retrieveToken, verifyToken, verifyProjectOwnership, (req, res) => {
    res.send(getTasks(req.decodedPayload.userId, req.params.projectId));
});

app.post("/project/:projectId/task", retrieveToken, verifyToken, verifyProjectOwnership, (req, res) => {
    console.log(req.body);

    if (req.body.name === undefined) {
        res.status(400).send("Missing required attribute 'name'");
    } else {
        const task = {
            name: req.body.name,
            content: req.body.content,
            duration: req.body.duration,
            dueDate: req.body.dueDate,
        };

        try {
            res.send({id: createTask(req.params.projectId, task)});
        } catch (e) {
            console.error(e);
            res.sendStatus(500);
        }
    }
});

app.patch("/moveTask/:taskId/:projectId", retrieveToken, verifyToken, verifyProjectOwnership, verifyTaskOwnership,
    (req, res) => {
        try {
            if (moveTask(req.decodedPayload.userId, req.params.taskId, req.params.projectId)) {
                res.sendStatus(200);
            } else {
                res.status(500).send("Could not move task");
            }
        } catch (e) {
            console.error(e);
            res.sendStatus(500);
        }
    });

app.put("/task/:taskId", retrieveToken, verifyToken, verifyTaskOwnership, (req, res) => {
    const task = {
        name: req.body.name,
        content: req.body.content,
        duration: req.body.duration,
        dueDate: req.body.dueDate,
    };

    // A PUT request requires a transmission of the whole object
    for (const key in task) {
        if (task[key] === undefined) {
            res.sendStatus(400);
            return;
        }
    }

    try {
        updateTask(req.decodedPayload.userId, req.params.taskId, task);
        res.sendStatus(200);
    } catch (e) {
        console.error(e);
        res.sendStatus(500);
    }
});

app.patch("/task/:taskId", retrieveToken, verifyToken, verifyTaskOwnership, (req, res) => {
    const task = getTask(req.decodedPayload.userId, req.params.taskId);

    task.name = req.body.name ?? task.name;
    task.content = req.body.content ?? task.content;
    task.duration = req.body.duration ?? task.duration;
    task.dueDate = req.body.dueDate ?? task.dueDate;

    try {
        updateTask(req.decodedPayload.userId, req.params.taskId, task);
        res.sendStatus(200);
    } catch (e) {
        console.error(e);
        res.sendStatus(500);
    }
});

app.delete("/task/:taskId", retrieveToken, verifyToken, verifyTaskOwnership, (req, res) => {
    try {
        if (deleteTask(req.decodedPayload.userId, req.params.taskId)) {
            res.sendStatus(200);
        } else {
            res.status(500).send("Could not move task");
        }
    } catch (e) {
        console.error(e);
        res.sendStatus(500);
    }
});

app.listen(
    PORT,
    () => console.log(`Server alive at http://localhost:${PORT}`)
);
