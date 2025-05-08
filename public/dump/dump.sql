CREATE TABLE employees (
    id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    address VARCHAR(255) NOT NULL,
    salary INT(10) NOT NULL
);
CREATE TABLE login (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL  -- Store hashed passwords
);

-- Insert a test user (username: testuser, password: password123)
INSERT INTO login (username, password) VALUES ('admin', '$2y$10$9G/vPZd/vzH8UBsL2wb3DeR56I46jnZFv7o0JxaJUaTTqgHBsCcnG');