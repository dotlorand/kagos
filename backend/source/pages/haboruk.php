<?php

include(__DIR__ . '/../../database/connect.php');

ob_start();
session_start();
include_once(__DIR__ . '/../logic/game_backend.php'); // Assumes $connection is set

// Utility: Retrieve all teams for dropdown lists.
function get_all_teams($connection) {
    $teams = [];
    $query = "SELECT id, nev FROM csapatok ORDER BY letrehozva";
    $result = mysqli_query($connection, $query);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $teams[] = $row;
        }
        mysqli_free_result($result);
    }
    return $teams;
}

function display_message() {
    if(isset($_SESSION['error'])) {
        echo '<div class="toast error">' . htmlspecialchars($_SESSION['error'], ENT_QUOTES, 'UTF-8') . '</div>';
        unset($_SESSION['error']);
    }
    if(isset($_SESSION['message'])) {
        echo '<div class="toast success">' . htmlspecialchars($_SESSION['message'], ENT_QUOTES, 'UTF-8') . '</div>';
        unset($_SESSION['message']);
    }
}

// Process starting a new war.
if (!isset($_SESSION['active_war']) && isset($_POST['start_war'])) {
    $attacker = trim($_POST['attacker'] ?? '');
    $defender = trim($_POST['defender'] ?? '');
    if ($attacker === '' || $defender === '') {
        $_SESSION['error'] = "Mindkét országot ki kell választani.";
        header("Location: /haboruk");
        exit;
    }
    if ($attacker === $defender) {
        $_SESSION['error'] = "A támadó és a védekező ország nem lehet ugyanaz.";
        header("Location: /haboruk");
        exit;
    }
    // Validate existence of both teams.
    $query = "SELECT id, nev FROM csapatok WHERE id IN (?, ?)";
    $stmt = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($stmt, "ss", $attacker, $defender);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $names = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $names[$row['id']] = $row['nev'];
    }
    mysqli_stmt_close($stmt);
    
    if (count($names) < 2) {
        $_SESSION['error'] = "Érvénytelen ország(ok) választva.";
        header("Location: /haboruk");
        exit;
    }
    
    // Store active war in session.
    $_SESSION['active_war'] = [
        'attacker' => $attacker,
        'defender' => $defender
    ];
    $_SESSION['message'] = "A háború elindult. Támadó: " . htmlspecialchars($names[$attacker], ENT_QUOTES, 'UTF-8') . ", Védekező: " . htmlspecialchars($names[$defender], ENT_QUOTES, 'UTF-8') . ".";
    header("Location: /haboruk");
    exit;
}

// Process a war round if a war is active.
if (isset($_SESSION['active_war']) && isset($_POST['round_result'])) {
    $result = $_POST['round_result']; // Expected values: "attacker" or "defender"
    $attacker_id = $_SESSION['active_war']['attacker'];
    $defender_id = $_SESSION['active_war']['defender'];

    // Function to get the current military points of a team.
    function get_military_points($connection, $team_id) {
        $query = "SELECT katonai_pontok FROM csapatok WHERE id = ?";
        $stmt = mysqli_prepare($connection, $query);
        mysqli_stmt_bind_param($stmt, "s", $team_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $points);
        mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);
        return (int)$points;
    }

    $attacker_points = get_military_points($connection, $attacker_id);
    $defender_points = get_military_points($connection, $defender_id);

    // Check that the attacker can actually attack.
    if ($attacker_points < 5) {
        $_SESSION['error'] = "A támadó csapatnak nincs elegendő katonai pontja a támadáshoz. A háború véget ért.";
        unset($_SESSION['active_war']);
        header("Location: /haboruk");
        exit;
    }

    // Deduct cost of attack from attacker (5 points per round).
    $new_attacker_points = $attacker_points - 5;
    $stmt = mysqli_prepare($connection, "UPDATE csapatok SET katonai_pontok = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "is", $new_attacker_points, $attacker_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    // Process round outcome.
    if ($result === "attacker") {
        // BEFORE deducting defender's points, check if the defender can pay.
        if ($defender_points < 5) {
            // Defender cannot pay → attacker conquers defender.
            $query = "SELECT * FROM csapatok WHERE id = ?";
            $stmt = mysqli_prepare($connection, $query);
            mysqli_stmt_bind_param($stmt, "s", $defender_id);
            mysqli_stmt_execute($stmt);
            $defender_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);

            $stmt = mysqli_prepare($connection, $query);
            mysqli_stmt_bind_param($stmt, "s", $attacker_id);
            mysqli_stmt_execute($stmt);
            $attacker_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);

            // List the resource fields to be transferred.
            $resources = ['bevetel', 'termeles', 'kutatasi_pontok', 'diplomaciai_pontok', 'katonai_pontok', 'bankok', 'gyarak', 'egyetemek', 'laktanyak'];

            // Transfer defender's resources to the attacker and reset defender's values.
            foreach ($resources as $field) {
                $attacker_data[$field] = (int)$attacker_data[$field] + (int)$defender_data[$field];
                $defender_data[$field] = 0;
            }

            // Update attacker record.
            $updateQuery = "UPDATE csapatok SET bevetel = ?, termeles = ?, kutatasi_pontok = ?, diplomaciai_pontok = ?, katonai_pontok = ?, bankok = ?, gyarak = ?, egyetemek = ?, laktanyak = ? WHERE id = ?";
            $stmt = mysqli_prepare($connection, $updateQuery);
            // Nine integers then string – binding order: i i i i i i i i i s
            mysqli_stmt_bind_param($stmt, "iiiiiiiiis",
                $attacker_data['bevetel'],
                $attacker_data['termeles'],
                $attacker_data['kutatasi_pontok'],
                $attacker_data['diplomaciai_pontok'],
                $attacker_data['katonai_pontok'],
                $attacker_data['bankok'],
                $attacker_data['gyarak'],
                $attacker_data['egyetemek'],
                $attacker_data['laktanyak'],
                $attacker_id
            );
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            // Reset defender's resources.
            $updateDefQuery = "UPDATE csapatok SET bevetel = 0, termeles = 0, kutatasi_pontok = 0, diplomaciai_pontok = 0, katonai_pontok = 0, bankok = 0, gyarak = 0, egyetemek = 0, laktanyak = 0 WHERE id = ?";
            $stmt = mysqli_prepare($connection, $updateDefQuery);
            mysqli_stmt_bind_param($stmt, "s", $defender_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            $_SESSION['message'] = "A támadó ország meghódította a védekező országot, és átvette annak összes erőforrását!";
            unset($_SESSION['active_war']);
            header("Location: /haboruk");
            exit;
        } else {
            // Defender can pay, so deduct 5 points.
            $new_defender_points = $defender_points - 5;
            $stmt = mysqli_prepare($connection, "UPDATE csapatok SET katonai_pontok = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "is", $new_defender_points, $defender_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }
    // If the defender wins, no extra cost is applied to the defender (only attacker already paid).

    // Re-fetch the updated military points.
    $attacker_points = get_military_points($connection, $attacker_id);
    $defender_points = get_military_points($connection, $defender_id);

    // Check if the attacker has run out of military points.
    if ($attacker_points < 5) {
        $_SESSION['error'] = "A támadó csapat elfogyott a katonai pontokból, így a háború a védekező javára végződött.";
        unset($_SESSION['active_war']);
        header("Location: /haboruk");
        exit;
    }
    
    $_SESSION['message'] = "Kör feldolgozva. Támadó: $attacker_points pont, Védekező: $defender_points pont.";
    header("Location: /haboruk");
    exit;
}

?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title>Háborúk - Játék Menedzsment</title>
    <link rel="stylesheet" href="/public/static/css/pages/haboruk.css">
    <style>
        /* Basic styling for demonstration */
        .container { max-width: 800px; margin: 30px auto; padding: 20px; border: 1px solid #ccc; }
        .toast { padding: 10px; margin-bottom: 10px; }
        .error { background: #fdd; color: #900; }
        .success { background: #dfd; color: #090; }
    </style>
</head>
<body>
    <nav>
        <a href="/round">Vissza a játék menedzsmenthez</a>
    </nav>
    <div class="container">
        <h1>Háborúk</h1>
        <?php display_message(); ?>
        
        <?php
        // If no active war exists, show the form to start a new war.
        if (!isset($_SESSION['active_war'])):
            $teams = get_all_teams($connection);
        ?>
            <h2>Indíts új háborút</h2>
            <form action="/haboruk" method="post">
                <label>Támadó ország:
                    <select name="attacker" required>
                        <option value="">-- Válassz támadót --</option>
                        <?php foreach ($teams as $team): ?>
                            <option value="<?php echo htmlspecialchars($team['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars($team['nev'], ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <br><br>
                <label>Védekező ország:
                    <select name="defender" required>
                        <option value="">-- Válassz védekezőt --</option>
                        <?php foreach ($teams as $team): ?>
                            <option value="<?php echo htmlspecialchars($team['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars($team['nev'], ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <br><br>
                <button type="submit" name="start_war" value="1">Háború indítása</button>
            </form>
        <?php else: 
            // If a war is active, fetch the attacker and defender data and display current state.
            $activeWar = $_SESSION['active_war'];
            $attacker_id = $activeWar['attacker'];
            $defender_id = $activeWar['defender'];
            
            // Fetch team names and military points.
            $query = "SELECT id, nev, katonai_pontok FROM csapatok WHERE id IN (?, ?)";
            $stmt = mysqli_prepare($connection, $query);
            mysqli_stmt_bind_param($stmt, "ss", $attacker_id, $defender_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $teamsInfo = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $teamsInfo[$row['id']] = $row;
            }
            mysqli_stmt_close($stmt);
        ?>
            <h2>Aktív háború</h2>
            <p>
                <strong>Támadó:</strong> <?php echo htmlspecialchars($teamsInfo[$attacker_id]['nev'] ?? $attacker_id, ENT_QUOTES, 'UTF-8'); ?> &nbsp;
                (Katonai pont: <?php echo htmlspecialchars($teamsInfo[$attacker_id]['katonai_pontok'] ?? '0', ENT_QUOTES, 'UTF-8'); ?>)
            </p>
            <p>
                <strong>Védekező:</strong> <?php echo htmlspecialchars($teamsInfo[$defender_id]['nev'] ?? $defender_id, ENT_QUOTES, 'UTF-8'); ?> &nbsp;
                (Katonai pont: <?php echo htmlspecialchars($teamsInfo[$defender_id]['katonai_pontok'] ?? '0', ENT_QUOTES, 'UTF-8'); ?>)
            </p>
            
            <h3>Következő kör</h3>
            <form action="/haboruk" method="post">
                <p>Válaszd ki a kör győztesét:</p>
                <label>
                    <input type="radio" name="round_result" value="attacker" required>
                    Támadó győzött (védekező 5 pont levonása)
                </label>
                <br>
                <label>
                    <input type="radio" name="round_result" value="defender" required>
                    Védekező győzött (csak támadó 5 pont levonása)
                </label>
                <br><br>
                <button type="submit">Kör feldolgozása</button>
            </form>
            <br>
            <form action="/haboruk" method="post">
                <!-- Optional: Reset/cancel the active war manually -->
                <button type="submit" name="cancel_war" value="1">Aktív háború megszakítása</button>
            </form>
            <?php
                // Process manual cancellation.
                if (isset($_POST['cancel_war'])) {
                    unset($_SESSION['active_war']);
                    $_SESSION['message'] = "Az aktív háború megszakadt.";
                    header("Location: /haboruk");
                    exit;
                }
            ?>
        <?php endif; ?>
    </div>
</body>
</html>
<?php
ob_end_flush();
?>
