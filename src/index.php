<?php
session_start();

// Database configuration using environment variables
$host = getenv('DB_HOST');
$dbname = getenv('DB_NAME');
$username = getenv('DB_USERNAME');
$password = getenv('DB_PASSWORD');

try {
    // Create a new PDO instance
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create the database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname`");
    // Use the database
    $pdo->exec("USE `$dbname`");

    // Create the users table for authentication
    $createUsersTable = "
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            email VARCHAR(100) NOT NULL UNIQUE
        );
    ";
    $pdo->exec($createUsersTable);

    // Create the routes table
    $createRoutesTable = "
        CREATE TABLE IF NOT EXISTS routes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            source VARCHAR(255) NOT NULL,
            destination VARCHAR(255) NOT NULL,
            distance_km INT,
            estimated_time VARCHAR(50)
        );
    ";
    $pdo->exec($createRoutesTable);

    // Handle user registration
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register'])) {
        $username = $_POST['username'];
        $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
        $email = $_POST['email'];

        $stmt = $pdo->prepare("INSERT INTO users (username, password, email) VALUES (:username, :password, :email)");
        $stmt->execute(['username' => $username, 'password' => $password, 'email' => $email]);
        echo "Registration successful. You can now log in.<br>";
    }

    // Handle user login
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
        $username = $_POST['username'];
        $password = $_POST['password'];

        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username");
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user'] = $user['username'];
            echo "Login successful. Welcome, " . htmlspecialchars($user['username']) . "!<br>";
        } else {
            echo "Invalid login credentials.<br>";
        }
    }

    // Logout functionality
    if (isset($_GET['logout'])) {
        session_destroy();
        header("Location: {$_SERVER['PHP_SELF']}");
    }

    // HTML structure and form
    echo '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Logistics Application</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                margin: 20px;
                background-color: #f4f4f4;
            }
            h1, h2 {
                text-align: center;
                color: #333;
            }
            form {
                margin: 0 auto;
                max-width: 400px;
                padding: 1em;
                background: #fff;
                border-radius: 5px;
                box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            }
            input, button {
                width: 100%;
                padding: 10px;
                margin: 5px 0;
                border-radius: 5px;
                border: 1px solid #ccc;
            }
            button {
                background-color: #007BFF;
                color: white;
                cursor: pointer;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin: 20px 0;
            }
            th, td {
                padding: 12px;
                border: 1px solid #ccc;
                text-align: left;
            }
            th {
                background-color: #007BFF;
                color: white;
            }
            tr:hover {
                background-color: #f1f1f1;
            }
            footer {
                text-align: center;
                margin-top: 20px;
                font-size: 0.9em;
                color: #555;
            }
        </style>
    </head>
    <body>';

    echo '<h1>Logistics Application</h1>';

    // Show registration or login form if user is not logged in
    if (!isset($_SESSION['user'])) {
        echo '<h2>Login</h2>
        <form method="post">
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit" name="login">Login</button>
        </form>';

        echo '<h2>Sign Up</h2>
        <form method="post">
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <input type="email" name="email" placeholder="Email" required>
            <button type="submit" name="register">Sign Up</button>
        </form>';
    } else {
        echo "<p>Welcome, " . htmlspecialchars($_SESSION['user']) . "! <a href='?logout'>Logout</a></p>";

        // Search form
        echo '<h2>Search Routes</h2>
        <form method="post">
            <input type="text" name="source" placeholder="Source" required>
            <input type="text" name="destination" placeholder="Destination" required>
            <button type="submit" name="search">Search</button>
        </form>';

        // Handle route search
        if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['search'])) {
            $source = $_POST['source'];
            $destination = $_POST['destination'];

            $stmt = $pdo->prepare("SELECT * FROM routes WHERE source = :source AND destination = :destination");
            $stmt->execute(['source' => $source, 'destination' => $destination]);
            $routes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($routes) {
                echo "<table><tr><th>ID</th><th>Source</th><th>Destination</th><th>Distance (km)</th><th>Estimated Time</th></tr>";
                foreach ($routes as $route) {
                    echo "<tr>
                            <td>{$route['id']}</td>
                            <td>{$route['source']}</td>
                            <td>{$route['destination']}</td>
                            <td>{$route['distance_km']}</td>
                            <td>{$route['estimated_time']}</td>
                          </tr>";
                }
                echo "</table>";
            } else {
                echo "No routes found for the specified source and destination.<br>";
            }
        }
    }

    echo '<footer>
            <p>&copy; ' . date("Y") . ' Logistics Application</p>
          </footer>
    </body>
    </html>';

} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}
?>
