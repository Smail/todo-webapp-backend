INSERT INTO User(Username, PasswordHash, FirstName, LastName)
VALUES
-- Password: 123456789
('VanillaThunder', '$2y$10$MNk8/gyP8PLbvIAhvpGmn.aCEPPUSjjphcOk5Cl6FSsjfAEieD4uq', 'Max', 'Mustermann'),
-- Password: secure
('Smail', '$2y$10$KcZCHT19UYibSdQGsDhtuuWOnfO6CYc14Obrc4f0Y0s5y5R.WJeiW', 'Smail', 'Müller');

INSERT INTO Project(UserId, ProjectName)
VALUES (2, 'GitHub Projects'),
       (1, 'Groceries'),
       (2, 'Finances');

INSERT INTO ProjectTasks(ProjectId, TaskName, TaskContent)
VALUES (1, 'TODO Webapp', ''),
       (1, 'Linux Kernel', 'Rewrite whole kernel in Rust');

INSERT INTO ProjectTasks(ProjectId, TaskName, TaskContent, Duration, DueDate)
VALUES (1, 'Artificial General Intelligence', 'Build a world dominating AI.', 2400, '2021-12-31 13:45:00');

INSERT INTO ProjectTasks(ProjectId, TaskName, TaskContent)
VALUES (3, 'How to get rich',
        '1. Make Website
2. Make money
3. Buy fancy stuff'),
       (3, 'Buy Stocks', 'TSLA, AAPL, GOOG, SMAIL');

INSERT INTO ProjectTasks(ProjectId, TaskName, TaskContent)
VALUES (2, 'Tomatoes', ''),
       (2, 'Popcorn', ''),
       (2, 'Pizza', ''),
       (2, 'Mozzarella', '');