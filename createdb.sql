CREATE DATABASE IF NOT EXISTS wishlist_db;
USE wishlist_db;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100),
    email VARCHAR(150) UNIQUE,
    password_hash VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE holidays (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100),
    date DATE,
    owner_user_id INT,
    FOREIGN KEY (owner_user_id) REFERENCES users(id)
);

CREATE TABLE wishlist_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200),
    description TEXT,
    url VARCHAR(500),
    price DECIMAL(10, 2),
    status ENUM('expected', 'purchased', 'presented') DEFAULT 'expected',
    item_type ENUM('for_me', 'for_others', 'universal') DEFAULT 'universal',
    photo_path VARCHAR(500),
    holiday_id INT,
    FOREIGN KEY (holiday_id) REFERENCES holidays(id)
);
CREATE TABLE favorite_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    item_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (item_id) REFERENCES wishlist_items(id),
    UNIQUE KEY unique_favorite (user_id, item_id)
);
CREATE TABLE user_shared_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    original_item_id INT,
    holiday_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (original_item_id) REFERENCES wishlist_items(id),
    FOREIGN KEY (holiday_id) REFERENCES holidays(id)
);