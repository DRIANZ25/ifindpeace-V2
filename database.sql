-- iFindPeace Database Schema
-- Lost and Found System for Mindanao State University - General Santos

CREATE DATABASE IF NOT EXISTS ifindpeace;
USE ifindpeace;

-- Users Table
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(20) UNIQUE NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    course VARCHAR(100),
    year_level VARCHAR(20),
    user_type ENUM('user', 'admin') DEFAULT 'user',
    profile_picture VARCHAR(255),
    honesty_pledge_signed BOOLEAN DEFAULT FALSE,
    pledge_signed_date DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active'
);

-- Categories Table
CREATE TABLE categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(50) NOT NULL,
    category_description TEXT,
    icon VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Items Table
CREATE TABLE items (
    item_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    category_id INT NOT NULL,
    item_name VARCHAR(200) NOT NULL,
    item_type ENUM('lost', 'found') NOT NULL,
    description TEXT NOT NULL,
    location_found VARCHAR(200),
    location_lost VARCHAR(200),
    date_found DATE,
    date_lost DATE,
    image_path VARCHAR(255),
    color VARCHAR(50),
    brand VARCHAR(100),
    status ENUM('pending', 'claimed', 'returned', 'donated', 'expired') DEFAULT 'pending',
    is_verified BOOLEAN DEFAULT FALSE,
    view_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(category_id) ON DELETE CASCADE
);

-- Claims Table
CREATE TABLE claims (
    claim_id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    user_id INT NOT NULL,
    claim_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    id_proof_path VARCHAR(255),
    security_question VARCHAR(255),
    security_answer VARCHAR(255),
    claim_status ENUM('pending', 'approved', 'rejected', 'completed') DEFAULT 'pending',
    admin_notes TEXT,
    deadline DATE,
    FOREIGN KEY (item_id) REFERENCES items(item_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Notifications Table
CREATE TABLE notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    item_id INT,
    notification_type ENUM('match_found', 'claim_approved', 'claim_rejected', 'item_expired', 'deadline_reminder', 'admin_message') NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(item_id) ON DELETE SET NULL
);

-- Feedback Table
CREATE TABLE feedback (
    feedback_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    item_id INT,
    rating INT CHECK (rating >= 1 AND rating <= 5),
    comments TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(item_id) ON DELETE SET NULL
);

-- Activity Log Table
CREATE TABLE activity_log (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
);

-- Insert Default Categories
INSERT INTO categories (category_name, category_description, icon) VALUES
('Electronics', 'Devices such as phones, laptops, tablets, etc.', 'fa-laptop'),
('IDs & Documents', 'Student IDs, passports, licenses, etc.', 'fa-id-card'),
('Keys', 'Room keys, car keys, locker keys, etc.', 'fa-key'),
('Clothing', 'Jackets, uniforms, accessories, etc.', 'fa-shirt'),
('Books & Supplies', 'Textbooks, notebooks, school supplies', 'fa-book'),
('Bags & Wallets', 'Backpacks, handbags, wallets, purses', 'fa-bag-shopping'),
('Jewelry', 'Rings, necklaces, watches, accessories', 'fa-gem'),
('Others', 'Items that do not fit in other categories', 'fa-box');

-- Insert Default Admin User (Password: password - Change this in production!)
INSERT INTO users (student_id, full_name, email, password, user_type, honesty_pledge_signed) VALUES
('ADMIN001', 'System Administrator', 'admin@ifindpeace.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', TRUE);

-- Contact messages
CREATE TABLE contact_messages (
    message_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    subject VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create Indexes for Better Performance
CREATE INDEX idx_items_type ON items(item_type);
CREATE INDEX idx_items_status ON items(status);
CREATE INDEX idx_items_category ON items(category_id);
CREATE INDEX idx_items_user ON items(user_id);
CREATE INDEX idx_claims_status ON claims(claim_status);
CREATE INDEX idx_notifications_user ON notifications(user_id);
CREATE INDEX idx_notifications_read ON notifications(is_read);
