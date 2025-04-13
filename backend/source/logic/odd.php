<?php

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['discovery_action']) && isset($_POST['team_id'])) {
    $team_id = trim($_POST['team_id']);
    // kutatasi adatok
    $stmt = mysqli_prepare($connection, "SELECT kutatasi_pontok, research_era, research_found, winner FROM csapatok WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "s", $team_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $research_points, $research_era, $research_found, $winner);
    if (!mysqli_stmt_fetch($stmt)) {
        $error = "Csapat nem található.";
    }
    mysqli_stmt_close($stmt);
    
    // korszakok
    $era_requirements = [
        1 => 11,
        2 => 8,
        3 => 7,
        4 => 9,
        5 => 8,
        6 => 7
    ];
    
    $action = $_POST['discovery_action'];
    
    if ($research_era < 6 || ($research_era == 6 && $research_found < $era_requirements[6])) {
        // 5-ös szorzo minden korszaknál
        $cost = $research_era * 5;
        if ($action === 'plus') {
            if ($research_points >= $cost) {
                $research_points -= $cost;
                $research_found++;

                // kövi korszakra menjen el van érve
                if ($research_found >= $era_requirements[$research_era]) {
                    if ($research_era < 6) {
                        $research_era++;
                        $research_found = 0;
                    }
                }
            } else {
                $error = "Nincs elég kutatási pont!";
            }
        } elseif ($action === 'minus') {
            if ($research_found > 0) {
                $research_found--;
                $research_points += $cost;
            }
        }
    } else {
        // vegso fazis req
        $final_cost = 50;
        if ($action === 'final_plus') {
            if ($research_points >= $final_cost) {
                $research_points -= $final_cost;
                $winner = 1;
            } else {
                $error = "Nincs elég kutatási pont!";
            }
        }
    }
    
    // update ha van eleg pont
    if (!isset($error)) {
        $stmt = mysqli_prepare($connection, "UPDATE csapatok SET kutatasi_pontok = ?, research_era = ?, research_found = ?, winner = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "iiiss", $research_points, $research_era, $research_found, $winner, $team_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        header("Location: /round?uuid=" . urlencode($team_id). "&access_key=" . htmlspecialchars($_GET['access_key'] ?? '', ENT_QUOTES, 'UTF-8'));
        exit;
    }
}

$final_stage_reached = 0;
