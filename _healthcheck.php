<?php
require_once __DIR__ . '/config/config.php';
mysqli_report(MYSQLI_REPORT_OFF);
$c = getDBConnection();
echo "SQL_MODE=" . $c->query('SELECT @@sql_mode m')->fetch_assoc()['m'] . PHP_EOL;
$tables = $c->query('SHOW TABLES');
echo "TABLES:";
while ($t = $tables->fetch_row()) {
    echo ' ' . $t[0];
}
echo PHP_EOL;
foreach (['users', 'items', 'categories', 'claims', 'notifications', 'activity_log', 'feedback'] as $t) {
    $q = $c->query("SELECT COUNT(*) n FROM `$t`");
    echo $t . '=' . ($q ? $q->fetch_assoc()['n'] : ('MISSING ' . $c->error)) . PHP_EOL;
}
$r = $c->query("SELECT u.full_name, u.student_id, COUNT(i.item_id) as item_count FROM users u JOIN items i ON u.user_id = i.user_id GROUP BY u.user_id LIMIT 1");
echo 'TOP_USERS=' . ($r === false ? ('FAIL ' . $c->error) : 'OK') . PHP_EOL;
$admin = $c->query("SELECT COUNT(*) n FROM users WHERE user_type = 'admin'");
echo 'ADMIN_COUNT=' . ($admin ? $admin->fetch_assoc()['n'] : 'FAIL') . PHP_EOL;
$cats = $c->query("SELECT category_id, category_name FROM categories ORDER BY category_id");
if ($cats) {
    while ($row = $cats->fetch_assoc()) {
        echo 'CAT ' . $row['category_id'] . ' ' . $row['category_name'] . PHP_EOL;
    }
}
