<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['purchase_action']) && isset($_POST['team_id']) && isset($_POST['purchase_building'])) {

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    
    $action = trim($_POST['purchase_action']);
    $building_type = trim($_POST['purchase_building']);
    $team_id = trim($_POST['team_id']);
    
    $stmt = mysqli_prepare($connection, "SELECT bevetel, termeles, bankok, gyarak, egyetemek, laktanyak FROM csapatok WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "s", $team_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $bevetel, $termeles, $bankok, $gyarak, $egyetemek, $laktanyak);
    if (!mysqli_stmt_fetch($stmt)) {
        $_SESSION['error'] = "Csapat nem található.";
        header("Location: /round?uuid=" . urlencode($team_id). "&access_key=" . htmlspecialchars($_GET['access_key'] ?? '', ENT_QUOTES, 'UTF-8'));
        exit;
    }
    mysqli_stmt_close($stmt);
    
    $building_prices = [
        'bank' => [
            2  => 10, 4  => 15, 6  => 20, 8  => 30, 10 => 30,
            12 => 10, 14 => 20, 16 => 10, 18 => 30, 20 => 40,
            22 => 50, 24 => 25, 26 => 30, 28 => 50, 30 => 10,
        ],
        'gyar' => [
            2  => 10, 4  => 10, 6  => 15, 8  => 30, 10 => 30,
            12 => 10, 14 => 20, 16 => 10, 18 => 20, 20 => 40,
            22 => 50, 24 => 25, 26 => 30, 28 => 50, 30 => 10,
        ],
        'egyetem' => [
            2  => 10, 4  => 15, 6  => 10, 8  => 30, 10 => 10,
            12 => 30, 14 => 20, 16 => 20, 18 => 10, 20 => 40,
            22 => 50, 24 => 30, 26 => 25, 28 => 50, 30 => 10,
        ],
        'laktanya' => [
            2  => 10, 4  => 10, 6  => 20, 8  => 30, 10 => 10,
            12 => 30, 14 => 20, 16 => 10, 18 => 30, 20 => 40,
            22 => 50, 24 => 25, 26 => 30, 28 => 50, 30 => 10,
        ]
    ];
    
    global $current_round;
    if (isset($building_prices[$building_type][$current_round])) {
        $cost = $building_prices[$building_type][$current_round];
    } else {
        $cost = $current_round;
    }
    
    $currency_type = trim($_POST['purchase_currency'] ?? '');
    if ($currency_type === '') {
        $_SESSION['error'] = "Válassz fizetési módot!";
        header("Location: /round?uuid=" . urlencode($team_id). "&access_key=" . htmlspecialchars($_GET['access_key'] ?? '', ENT_QUOTES, 'UTF-8'));
        exit;
    }
    
    if ($currency_type === 'bevetel') {
        $available_currency = $bevetel;
    } elseif ($currency_type === 'termeles') {
        $available_currency = $termeles;
    } else {
        $_SESSION['error'] = "Érvénytelen fizetési mód!";
        header("Location: /round?uuid=" . urlencode($team_id). "&access_key=" . htmlspecialchars($_GET['access_key'] ?? '', ENT_QUOTES, 'UTF-8'));
        exit;
    }

    $_SESSION['purchase_currency'][$building_type] = $currency_type;

    
    switch ($building_type) {
        case 'bank':
            $current_building = $bankok;
            break;
        case 'gyar':
            $current_building = $gyarak;
            break;
        case 'egyetem':
            $current_building = $egyetemek;
            break;
        case 'laktanya':
            $current_building = $laktanyak;
            break;
        default:
            $_SESSION['error'] = "Érvénytelen épület típus!";
            header("Location: /round?uuid=" . urlencode($team_id). "&access_key=" . htmlspecialchars($_GET['access_key'] ?? '', ENT_QUOTES, 'UTF-8'));
            exit;
    }
    
    if ($action === 'plus') {
        if ($available_currency >= $cost) {
            $available_currency -= $cost;
            $current_building++;
        } else {
            $_SESSION['error'] = "Nincs elég pénz a vásárláshoz!";
            header("Location: /round?uuid=" . urlencode($team_id). "&access_key=" . htmlspecialchars($_GET['access_key'] ?? '', ENT_QUOTES, 'UTF-8'));
            exit;
        }
    } elseif ($action === 'minus') {
        if ($current_building > 0) {
            $current_building--;
            $available_currency += $cost;
        } else {
            $_SESSION['error'] = "Nincs épület visszaadásra!";
            header("Location: /round?uuid=" . urlencode($team_id). "&access_key=" . htmlspecialchars($_GET['access_key'] ?? '', ENT_QUOTES, 'UTF-8'));
            exit;
        }
    } else {
        $_SESSION['error'] = "Érvénytelen művelet!";
        header("Location: /round?uuid=" . urlencode($team_id). "&access_key=" . htmlspecialchars($_GET['access_key'] ?? '', ENT_QUOTES, 'UTF-8'));
        exit;
    }
    
    if ($currency_type === 'bevetel') {
        $new_bevetel = $available_currency;
        $new_termeles = $termeles;
    } else {
        $new_termeles = $available_currency;
        $new_bevetel = $bevetel;
    }
    
    $new_bankok = $bankok;
    $new_gyarak = $gyarak;
    $new_egyetem = $egyetemek;
    $new_laktanyak = $laktanyak;
    switch ($building_type) {
        case 'bank':
            $new_bankok = $current_building;
            break;
        case 'gyar':
            $new_gyarak = $current_building;
            break;
        case 'egyetem':
            $new_egyetem = $current_building;
            break;
        case 'laktanya':
            $new_laktanyak = $current_building;
            break;
    }
    
    $stmt = mysqli_prepare($connection, "UPDATE csapatok SET bevetel = ?, termeles = ?, bankok = ?, gyarak = ?, egyetemek = ?, laktanyak = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "iiiiiis", $new_bevetel, $new_termeles, $new_bankok, $new_gyarak, $new_egyetem, $new_laktanyak, $team_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    header("Location: /round?uuid=" . urlencode($team_id) . "&access_key=" . htmlspecialchars($_GET['access_key'] ?? '', ENT_QUOTES, 'UTF-8'));
    exit;
}
?>
