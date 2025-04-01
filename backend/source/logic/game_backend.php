<?php
/**
 * 
 * jatek logika es fobb jatek funkciok
 * 
 */

 // bug fix
function ensure_jatek_row($connection) {
    $query = "SELECT id FROM jatekok LIMIT 1";
    $result = mysqli_query($connection, $query);
    if (!$result || mysqli_num_rows($result) === 0) {
        $init_query = "INSERT INTO jatekok (current_round, phase) VALUES (0, 'init')";
        mysqli_query($connection, $init_query);
    }
}

function get_current_round($connection) {
    ensure_jatek_row($connection);
    $query = "SELECT current_round FROM jatekok LIMIT 1";
    $result = mysqli_query($connection, $query);
    if ($result && mysqli_num_rows($result) === 1) {
        $row = mysqli_fetch_assoc($result);
        return (int)$row['current_round'];
    }
    return 0;
}

// jatek statusz check init/active
function get_game_phase($connection) {
    ensure_jatek_row($connection);
    $query = "SELECT phase FROM jatekok LIMIT 1";
    $result = mysqli_query($connection, $query);
    if ($result && mysqli_num_rows($result) === 1) {
        $row = mysqli_fetch_assoc($result);
        return $row['phase'];
    }
    return 'init';
}

function update_current_round($connection, $new_round) {
    ensure_jatek_row($connection);
    $query = "UPDATE jatekok SET current_round = ?";
    $stmt = mysqli_prepare($connection, $query);
    if (!$stmt) {
        return false;
    }
    mysqli_stmt_bind_param($stmt, "i", $new_round);
    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $result;
}

// jatekok_history update
function log_team_history($connection, $round, $team) {
    $query = "INSERT INTO jatekok_history 
        (round, team_id, nev, allamforma, kontinens, bevetel, termeles, kutatasi_pontok, diplomaciai_pontok, katonai_pontok, bankok, gyarak, egyetemek, laktanyak)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($connection, $query);
    if (!$stmt) {
        return false;
    }
    mysqli_stmt_bind_param($stmt, "issssiiiiiiiii",
        $round,
        $team['id'],
        $team['nev'],
        $team['allamforma'],
        $team['kontinens'],
        $team['bevetel'],
        $team['termeles'],
        $team['kutatasi_pontok'],
        $team['diplomaciai_pontok'],
        $team['katonai_pontok'],
        $team['bankok'],
        $team['gyarak'],
        $team['egyetemek'],
        $team['laktanyak']
    );
    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $result;
}

// jatek inditasa
function start_game_now($connection) {
    ensure_jatek_row($connection);
    $query = "UPDATE jatekok SET phase = 'active'";
    if (!mysqli_query($connection, $query)) {
        return false;
    }
    
    // Log initial snapshot for round 0.
    $query = "SELECT * FROM csapatok";
    $result = mysqli_query($connection, $query);
    if ($result) {
        while ($team = mysqli_fetch_assoc($result)) {
            log_team_history($connection, 0, $team);
        }
    }
    return true;
}

// GAME LOGIC RULES
// minden összekötés ide jön
$game_update_rules = [
    [
        'target'    => 'bevetel',
        'calculate' => function($team) {
            return (int)$team['bankok'];
        }
    ],
    [
        'target'    => 'termeles',
        'calculate' => function($team) {
            return (int)$team['gyarak'];
        }
    ],
    [
        'target'    => 'kutatasi_pontok',
        'calculate' => function($team) {
            return (int)$team['egyetemek'];
        }
    ],
    [
        'target'    => 'katonai_pontok',
        'calculate' => function($team) {
            return (int)$team['laktanyak'];
        }
    ],
    [
        'target'    => 'diplomaciai_pontok',
        'calculate' => function($team) {
            return (int)$team['egyetemek'] + (int)$team['laktanyak'];
        }
    ],
];

// process round:
//      +1 round counter
//      update team data based on rules
//      log in history

function process_game_round($connection) {
    global $game_update_rules;
    $current_round = get_current_round($connection);
    $new_round = $current_round + 1;
    
    // new round
    if (!update_current_round($connection, $new_round)) {
        return false;
    }
    
    $query = "SELECT * FROM csapatok";
    $result = mysqli_query($connection, $query);
    if (!$result) {
        return false;
    }
    
    while ($team = mysqli_fetch_assoc($result)) {
        $team_id = $team['id'];
        $updated_team = $team;
        $updates = [];
        $params = [];
        $param_types = "";
        
        foreach ($game_update_rules as $rule) {
            $target = $rule['target'];
            $increment = $rule['calculate']($team);

            $updated_team[$target] = (int)$team[$target] + $increment;
            $updates[] = "$target = ?";
            $params[] = $updated_team[$target];
            $param_types .= "i";
        }
        
        $update_query = "UPDATE csapatok SET " . implode(", ", $updates) . " WHERE id = ?";
        $params[] = $team_id;
        $param_types .= "s";
        $stmt = mysqli_prepare($connection, $update_query);
        if (!$stmt) {
            continue;
        }
        mysqli_stmt_bind_param($stmt, $param_types, ...$params);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        log_team_history($connection, $new_round, $updated_team);
    }
    
    return true;
}

// idk
function process_purchase($connection, $team_id, $field, $cost) {
    $query = "SELECT $field FROM csapatok WHERE id = ?";
    $stmt = mysqli_prepare($connection, $query);
    if (!$stmt) {
        return false;
    }
    mysqli_stmt_bind_param($stmt, "s", $team_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $current_value);
    if (!mysqli_stmt_fetch($stmt)) {
        mysqli_stmt_close($stmt);
        return false;
    }
    mysqli_stmt_close($stmt);
    
    if ($current_value < $cost) {
        return false;
    }
    
    $new_value = $current_value - $cost;
    $update_query = "UPDATE csapatok SET $field = ? WHERE id = ?";
    $stmt = mysqli_prepare($connection, $update_query);
    if (!$stmt) {
        return false;
    }
    mysqli_stmt_bind_param($stmt, "is", $new_value, $team_id);
    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $result;
}
?>
