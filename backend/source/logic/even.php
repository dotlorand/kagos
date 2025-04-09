<?php
// Process Building Purchase action for even rounds.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['purchase_action']) && isset($_POST['team_id']) && isset($_POST['purchase_building'])) {
    // Ensure the session is started for flashing error messages.
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    
    // Use the submitted values directly.
    $action = trim($_POST['purchase_action']);
    $building_type = trim($_POST['purchase_building']);
    $team_id = trim($_POST['team_id']);
    
    // Retrieve current currency and building counts.
    $stmt = mysqli_prepare($connection, "SELECT bevetel, termeles, bankok, gyarak, egyetemek, laktanyak FROM csapatok WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "s", $team_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $bevetel, $termeles, $bankok, $gyarak, $egyetemek, $laktanyak);
    if (!mysqli_stmt_fetch($stmt)) {
        $_SESSION['error'] = "Csapat nem található.";
        header("Location: /round?uuid=" . urlencode($team_id));
        exit;
    }
    mysqli_stmt_close($stmt);
    
    // Define building prices based on round and type.
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
    
    // Use the global current round (set in manage_game.php)
    global $current_round;
    if (isset($building_prices[$building_type][$current_round])) {
        $cost = $building_prices[$building_type][$current_round];
    } else {
        $cost = $current_round;
    }
    
    // Check that a payment method was selected.
    $currency_type = trim($_POST['purchase_currency'] ?? '');
    if ($currency_type === '') {
        $_SESSION['error'] = "Válassz fizetési módot!";
        header("Location: /round?uuid=" . urlencode($team_id));
        exit;
    }
    
    // Determine available currency.
    if ($currency_type === 'bevetel') {
        $available_currency = $bevetel;
    } elseif ($currency_type === 'termeles') {
        $available_currency = $termeles;
    } else {
        $_SESSION['error'] = "Érvénytelen fizetési mód!";
        header("Location: /round?uuid=" . urlencode($team_id));
        exit;
    }

    // Save the selection for this building type
    $_SESSION['purchase_currency'][$building_type] = $currency_type;

    
    // Determine current building count.
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
            header("Location: /round?uuid=" . urlencode($team_id));
            exit;
    }
    
    // Process the action.
    if ($action === 'plus') {
        if ($available_currency >= $cost) {
            $available_currency -= $cost;
            $current_building++;
        } else {
            $_SESSION['error'] = "Nincs elég pénz a vásárláshoz!";
            header("Location: /round?uuid=" . urlencode($team_id));
            exit;
        }
    } elseif ($action === 'minus') {
        if ($current_building > 0) {
            $current_building--;
            $available_currency += $cost;
        } else {
            $_SESSION['error'] = "Nincs épület visszaadásra!";
            header("Location: /round?uuid=" . urlencode($team_id));
            exit;
        }
    } else {
        $_SESSION['error'] = "Érvénytelen művelet!";
        header("Location: /round?uuid=" . urlencode($team_id));
        exit;
    }
    
    // Update the team's record if no error occurred.
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
    header("Location: /round?uuid=" . urlencode($team_id));
    exit;
}
?>
