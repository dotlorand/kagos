<?php
/**
 * 
 * jatek logika es fobb jatek funkciok
 * 
 */

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

    // Increment round.
    if (!update_current_round($connection, $new_round)) {
        return false;
    }

    // Retrieve all custom recurring rules.
    $custom_rules_query = "SELECT team_id, field, amount FROM custom_rules";
    $custom_rules_result = mysqli_query($connection, $custom_rules_query);

    $custom_rules = [];
    if ($custom_rules_result) {
        while ($rule = mysqli_fetch_assoc($custom_rules_result)) {
            $custom_rules[$rule['team_id']][] = $rule; // Group rules by team ID.
        }
        mysqli_free_result($custom_rules_result);
    }

    // Retrieve alliance counts: count teams per alliance (ignore empty alliances).
    $alliance_counts = [];
    $alliance_query = "SELECT alliance, COUNT(*) AS count FROM csapatok WHERE alliance <> '' GROUP BY alliance";
    $result_alliance = mysqli_query($connection, $alliance_query);
    if ($result_alliance) {
        while ($row = mysqli_fetch_assoc($result_alliance)) {
            $alliance_counts[$row['alliance']] = (int)$row['count'];
        }
        mysqli_free_result($result_alliance);
    }

    // Retrieve all teams.
    $team_query = "SELECT * FROM csapatok";
    $team_result = mysqli_query($connection, $team_query);

    if (!$team_result) {
        return false;
    }

    // Define the extra politikák mapping.
    // Keys are the politikák values (as stored in the hidden JSON "value" property)
    // and the inner associative array keys are the team stats to adjust.
    $politics_mapping = [
        'totemizmus'       => ['termeles' => 1],
        'zikkurat'         => ['kutatasi_pontok' => 1],
        'nomad'            => ['katonai_pontok' => 1],
        'torzsi_szovetseg' => ['diplomaciai_pontok' => 1],
        'monoteizmus'      => ['kutatasi_pontok' => 1, 'diplomaciai_pontok' => 1, 'termeles' => -1],
        'politeizmus'      => ['kutatasi_pontok' => 1, 'termeles' => 1, 'diplomaciai_pontok' => -1],
        'xii_tabla'        => ['termeles' => 1],
        'pantheon'         => ['kutatasi_pontok' => 1],
        'nepgyules'        => ['diplomaciai_pontok' => 1],
        'legio'            => ['katonai_pontok' => 1],
        'akropolisz'       => ['kutatasi_pontok' => 1],
        'strategosz'       => ['katonai_pontok' => 1],
        'deloszi_szovetseg'=> ['diplomaciai_pontok' => 1],
        'ezustbany'        => ['termeles' => 1],
        'karavella'        => ['termeles' => 1],
        'monopolium'       => ['diplomaciai_pontok' => 1],
        'keresztes'        => ['katonai_pontok' => 1],
        'obszervatori'     => ['kutatasi_pontok' => 1],
        'inkvizicio'       => ['katonai_pontok' => 1, 'termeles' => 1, 'kutatasi_pontok' => -2],
        'gyarmatositas'    => ['kutatasi_pontok' => 1, 'diplomaciai_pontok' => 1, 'termeles' => -2],
        'kapitalizmus'     => ['termeles' => 1],
        'vilagbank'        => ['diplomaciai_pontok' => 1],
        'erasmus'          => ['kutatasi_pontok' => 1],
        'nemzeti_hadsereg' => ['katonai_pontok' => 1],
        'new_deal'         => ['termeles' => 1, 'diplomaciai_pontok' => 1, 'kutatasi_pontok' => -2],
        'schengeni'        => ['kutatasi_pontok' => 1, 'termeles' => 1, 'katonai_pontok' => -2],
        'emberi_jogok'     => ['kutatasi_pontok' => 1, 'diplomaciai_pontok' => 1, 'termeles' => -2],
        'nato'             => ['katonai_pontok' => 1, 'diplomaciai_pontok' => 1, 'kutatasi_pontok' => -2],
        'munkaverseny'     => ['termeles' => 1],
        'kgst'             => ['diplomaciai_pontok' => 1],
        'varsoi'           => ['katonai_pontok' => 1],
        'komintern'        => ['kutatasi_pontok' => 1],
        'gulag'            => ['termeles' => 1, 'kutatasi_pontok' => 1, 'diplomaciai_pontok' => -2],
        'allamrendor'      => ['katonai_pontok' => 1, 'kutatasi_pontok' => -2],
        'atomfegyver'      => ['katonai_pontok' => 1, 'kutatasi_pontok' => 1],
        'propaganda'       => ['termeles' => 1, 'diplomaciai_pontok' => 1, 'kutatasi_pontok' => -2]
    ];

    while ($team = mysqli_fetch_assoc($team_result)) {
        $team_id = $team['id'];
        $updated_team = $team;
        $updates = [];
        $params = [];
        $param_types = "";

        // Apply default game rules.
        foreach ($game_update_rules as $rule) {
            $target = $rule['target'];
            $increment = $rule['calculate']($team);

            $updated_team[$target] = (int)$team[$target] + $increment;
            $updates[] = "$target = ?";
            $params[] = $updated_team[$target];
            $param_types .= "i";
        }

        // Apply custom recurring rules for the current team.
        if (isset($custom_rules[$team_id])) {
            foreach ($custom_rules[$team_id] as $rule) {
                $field = $rule['field'];
                $amount = (int)$rule['amount'];
                if (isset($updated_team[$field])) {
                    $updated_team[$field] += $amount;
                    $index = array_search("$field = ?", $updates);
                    if ($index !== false) {
                        $params[$index] = $updated_team[$field];
                    } else {
                        $updates[] = "$field = ?";
                        $params[] = $updated_team[$field];
                        $param_types .= "i";
                    }
                }
            }
        }

        // *** Extra Politikák Bonus Block ***
        if (!empty($team['politikak'])) {
            $decoded_politics = json_decode($team['politikak'], true);
            if (is_array($decoded_politics)) {
                foreach ($decoded_politics as $chip) {
                    if (isset($chip['value']) && isset($politics_mapping[$chip['value']])) {
                        foreach ($politics_mapping[$chip['value']] as $stat => $delta) {
                            if (isset($updated_team[$stat])) {
                                $updated_team[$stat] += $delta;
                            }
                        }
                    }
                }
                // For each affected field, update our parameter array so it reflects the new values.
                $fields_to_update = ['termeles', 'kutatasi_pontok', 'diplomaciai_pontok', 'katonai_pontok'];
                foreach ($fields_to_update as $field) {
                    $index = array_search("$field = ?", $updates);
                    if ($index !== false) {
                        $params[$index] = $updated_team[$field];
                    }
                }
            }
        }
        // *** End Extra Politikák Bonus Block ***

        // *** Alliance Bonus Block ***
        if (!empty($team['alliance']) && isset($alliance_counts[$team['alliance']]) && $alliance_counts[$team['alliance']] >= 2) {
            $updated_team['diplomaciai_pontok'] += 1;
            $dipl_index = array_search("diplomaciai_pontok = ?", $updates);
            if ($dipl_index !== false) {
                $params[$dipl_index] = $updated_team['diplomaciai_pontok'];
            } else {
                $updates[] = "diplomaciai_pontok = ?";
                $params[] = $updated_team['diplomaciai_pontok'];
                $param_types .= "i";
            }
        }
        
        // *** Clamp resource values so none fall below zero ***
        $resource_fields = ['bevetel','termeles','kutatasi_pontok','diplomaciai_pontok','katonai_pontok','bankok','gyarak','egyetemek','laktanyak'];
        foreach ($resource_fields as $field) {
            $updated_team[$field] = max(0, $updated_team[$field]);  // enforce non-negative
            $index = array_search("$field = ?", $updates);
            if ($index !== false) {
                $params[$index] = $updated_team[$field];
            }
        }
        // *** End clamping ***

        // Build and execute the team update query.
        $update_query = "UPDATE csapatok SET " . implode(", ", $updates) . " WHERE id = ?";
        $params[] = $team_id;
        $param_types .= "s";

        $stmt = mysqli_prepare($connection, $update_query);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, $param_types, ...$params);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }

        // Log the updated team data into history.
        log_team_history($connection, $new_round, $updated_team);
    }

    
    mysqli_free_result($team_result);
    
    return true;
}


?>