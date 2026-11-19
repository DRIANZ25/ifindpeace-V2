function getDBConnection() {
    $host = getenv('DB_HOST');
    $port = getenv('DB_PORT') ? (int)getenv('DB_PORT') : 3306;
    $dbname = getenv('DB_DATABASE');
    $username = getenv('DB_USERNAME');
    $password = getenv('DB_PASSWORD');

    // Fallback for local XAMPP development
    if (!$host) {
        $host = 'localhost';
        $port = 3306;
        $dbname = 'ifindpeace';
        $username = 'root';
        $password = '';
    }

    $conn = new mysqli($host, $username, $password, $dbname, $port);

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    return $conn;
}