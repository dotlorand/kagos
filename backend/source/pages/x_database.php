<?php

include(__DIR__ . '/../../database/connect.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['truncate_database'])) {
    $tablesToClear = [
        'csapatok',
        'custom_rules',
        'ensz_votes',
        'ensz_winnerpoll',
        'haboruk',
        'jatekok',
        'jatekok_history'
    ];

    foreach ($tablesToClear as $table) {
        $sql = "TRUNCATE TABLE `$table`";
        if (!mysqli_query($connection, $sql)) {
            echo "Hiba a(z) $table törlésekor: " . mysqli_error($connection);
            exit;
        }
    }

    echo "Adatok törlése sikeres!";
    exit;
}
?>

<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title>Adatbázis törlése</title>
</head>
<body>
    <h1>Adatok törlése az adatbázisból</h1>
    <form action="?access_key=<?php echo htmlspecialchars($_GET['access_key'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" method="post" onsubmit="return confirm('FONTOS: EZT JÁTÉK KÖZBEN NE FOGADD EL!! Ez minden adatot töröl az adatbázisból.');">
        <button type="submit" name="truncate_database">Adatok törlése</button>
    </form>
</body>
</html>