# iFindPeace - Lost and Found System

**Peace Comes When Things Are Found**

A centralized web-based lost and found platform for Mindanao State University - General Santos students.

## Features

### For Users
- **User Authentication**: Secure login and registration system
- **Honesty Pledge**: Users must sign an honesty pledge during registration
- **Item Reporting**: Report lost or found items with detailed information and images
- **Search & Filter**: Advanced search with category and type filters
- **Item Claims**: Claim found items with ID verification and security questions
- **Notifications**: Real-time notifications for matches and claim updates
- **User Dashboard**: Personal dashboard to manage items and track claims
- **Profile Management**: Update personal information and profile picture

### For Admins
- **Admin Dashboard**: Overview of platform statistics and activity
- **Item Management**: View, filter, update status, and delete items
- **User Management**: Manage user accounts and status
- **Claims Management**: Review and approve/reject item claims
- **Activity Logging**: Track all user and admin activities

## Technology Stack

- **Backend**: PHP
- **Database**: MySQL
- **Frontend**: HTML5, CSS3, JavaScript
- **Framework**: Bootstrap 5
- **Icons**: Font Awesome 6

## Installation

### Prerequisites
- XAMPP or any PHP/MySQL server
- PHP 7.4 or higher
- MySQL 5.7 or higher

### Setup Instructions

1. **Extract/Clone the Project**
   - Place the project folder in your XAMPP htdocs directory
   - Path: `C:\xampp\htdocs\ifindpeace V2\`

2. **Create Database**
   - Open phpMyAdmin (http://localhost/phpmyadmin)
   - Create a new database named `ifindpeace`
   - Import the `database.sql` file located in the project root

3. **Configure Database Connection**
   - Open `config/config.php`
   - Update the database credentials if needed:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   define('DB_NAME', 'ifindpeace');
   ```

4. **Set File Permissions**
   - Ensure the `uploads/` directory and its subdirectories have write permissions
   - The system will automatically create these directories if they don't exist

5. **Access the Application**
   - Open your browser and go to: `http://localhost/ifindpeace%20V2/`

## Default Admin Account

- **Email**: admin@ifindpeace.com
- **Password**: password
- **Note**: Change this password immediately after first login for security

## User Guide

### Registration
1. Click "Register" on the homepage
2. Fill in your details (Student ID, Name, Email, Password, etc.)
3. Sign the Honesty Pledge
4. Submit the form

### Reporting an Item
1. Login to your account
2. Click "Report Item" from the dashboard or navbar
3. Select whether the item is Lost or Found
4. Fill in the item details (name, description, location, date, etc.)
5. Upload an image (optional but recommended)
6. Submit the report

### Searching for Items
1. Use the search bar on the homepage
2. Or go to "Browse Items" for advanced filtering
3. Filter by type (Lost/Found) and category
4. Click on items to view details

### Claiming an Item
1. Find the item you want to claim
2. Click "View Details"
3. Click "Claim This Item" (only for found items)
4. Upload your ID proof
5. Provide a security question and answer
6. Wait for the item reporter/admin to review your claim

## Project Structure

```
ifindpeace V2/
├── admin/                  # Admin panel pages
│   ├── dashboard.php      # Admin dashboard
│   ├── items.php          # Manage items
│   ├── users.php          # Manage users
│   └── claims.php         # Manage claims
├── assets/                # Static assets
│   ├── css/
│   │   └── style.css      # Custom styles
│   └── js/
│       └── main.js        # JavaScript functions
├── config/                # Configuration files
│   └── config.php         # Database and app config
├── uploads/               # User uploads
│   ├── items/            # Item images
│   ├── profiles/         # Profile pictures
│   └── id_proofs/        # ID proof documents
├── database.sql          # Database schema
├── index.php             # Homepage
├── login.php             # Login page
├── register.php          # Registration page
├── logout.php            # Logout handler
├── dashboard.php         # User dashboard
├── report.php            # Report item form
├── items.php             # Browse items
├── item-details.php      # Item details page
├── my-items.php          # User's items
├── profile.php           # User profile
└── notifications.php      # User notifications
```

## Security Features

- Password hashing using PHP's `password_hash()`
- SQL injection prevention using prepared statements
- XSS protection with input sanitization
- Session-based authentication
- File upload validation
- CSRF protection (can be enhanced with tokens)

## Future Enhancements

- Email notifications for important events
- SMS notifications for urgent items
- Advanced matching algorithm using AI
- Mobile app (React Native or Flutter)
- Integration with MSU student database
- QR code generation for items
- Geolocation tracking for found items
- Chat system between claimant and reporter

## Support

For issues or questions, please contact:
- Email: support@ifindpeace.com
- Location: MSU-Gensan Campus

## License

This project is developed for Mindanao State University - General Santos.

---

**Developed with ❤️ for MSU-Gensan Students**
