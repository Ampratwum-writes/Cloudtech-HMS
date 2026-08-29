# Cloudtech HMS

A web-based Hostel Management System designed to simplify and centralize hostel operations, including student management, room management, applications, bookings, payments, maintenance, visitors, and security.

## Overview

**Cloudtech HMS** is a PHP and MySQL-based Hostel Management System developed as a practical web application project.

The system provides a centralized platform for managing hostel-related activities and information through a web interface.

## Features

* Student registration and management
* User authentication and logout
* Student dashboard
* Room management
* Hostel applications
* Room booking
* Payment management
* Maintenance reporting
* Visitor management
* Staff management
* Security management

## Technologies Used

| Technology | Purpose                       |
| ---------- | ----------------------------- |
| PHP        | Server-side application logic |
| MySQL      | Database management           |
| HTML5      | Page structure                |
| CSS3       | User interface styling        |
| JavaScript | Client-side functionality     |
| XAMPP      | Local development environment |
| AWS        | Cloud deployment              |

## Project Structure

```text
Cloudtech-HMS/
│
├── .gitignore
├── config.example.php
├── README.md
│
├── index.php
├── login.php
├── logout.php
├── register.php
│
├── add_booking.php
├── applications.php
├── bookings.php
├── maintenance.php
├── payments.php
├── rooms.php
├── security.php
├── staff.php
├── student_dashboard.php
├── students.php
├── visitors.php
│
├── app.js
├── style.css
│
└── partials/
    ├── header.php
    └── footer.php
```

## Requirements

Before running the project locally, make sure you have:

* PHP
* MySQL
* Apache
* XAMPP or another PHP development environment
* A web browser

## Local Installation

### 1. Clone the repository

```bash
git clone https://github.com/Ampratwum-writes/Cloudtech-HMS.git
```

### 2. Move the project into XAMPP

Place the project inside your XAMPP `htdocs` directory.

For example:

```text
C:\xampp\htdocs\Cloudtech-HMS
```

### 3. Start XAMPP

Start:

```text
Apache
MySQL
```

### 4. Create the database

Create a MySQL database for the application.

The database name should match the database name configured in your local `config.php`.

### 5. Configure the database connection

The repository does not contain the real `config.php` because it may contain database credentials.

Create:

```text
config.php
```

using:

```text
config.example.php
```

as the template.

Update the database connection values with your local MySQL credentials.

### 6. Open the application

Open your browser and navigate to:

```text
http://localhost/Cloudtech-HMS/
```

## Configuration

The project uses a local configuration file for database connectivity.

Example configuration:

```php
<?php

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "your_database_name";
$port = 3306;

$conn = new mysqli(
    $servername,
    $username,
    $password,
    $dbname,
    $port
);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}
?>
```

**Do not commit your real `config.php` to GitHub.**

## Security

Sensitive configuration files are excluded from the repository using `.gitignore`.

The repository ignores:

```text
config.php
.env
*.pem
*.key
*.sql
*.tar.gz
```

This helps prevent database credentials, private keys, and database backups containing real data from being accidentally committed.

## Deployment

The HMS has been developed with cloud deployment in mind and has been deployed using AWS services.

The deployment environment includes AWS infrastructure for hosting the web application and database.

## Future Improvements

Planned improvements may include:

* Improved role-based access control
* Stronger input validation
* Improved authentication and authorization
* Enhanced password security
* Better database constraints and relationships
* Improved error handling
* Responsive UI improvements
* Automated testing
* Improved cloud architecture
* Monitoring and logging

## Project Status

**Active Development**

The project is being maintained and improved as part of a practical Computer Science and Cloud Computing project.

## Author

**Edwin Ampratwum**

Computer Science
Ghana Communication Technology University
