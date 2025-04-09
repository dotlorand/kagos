<?php
ob_start(); // Start output buffering

session_start();
include(__DIR__ . '/../components/head.php');
include_once(__DIR__ . '/../logic/game_backend.php');

// -----------------------------
// Normal ENSZ Proposal Vote System
// -----------------------------

// Ensure the game is active.
if (get_game_phase($connection) !== 'active') {
    header("Location: /init");
    exit;
}

// Get the current round (like in manage_game.php).
$current_round = get_current_round($connection) + 1;

// ENSZ is available only on even rounds.
if ($current_round % 2 !== 0) {
    header("Location: /round");
    exit;
}

// Define the list of proposals.
$proposals = [
    "Minden ország kapjon +1 termelést",
    "Minden ország kapjon +1 tudományos pontot",
    "Minden ország kapjon +1 katonai pontot",
    "A törzsi falu politikai berendezkedéssel rendelkező államok kapjanak +1 katonai pontot körönként",
    "Az arisztokratikus köztársaság és a türannisz politikai berendezkedéssel rendelkező országok kapjanak +1 katonai pontot és +1 termelést körünként",
    "Minden olyan ország, amely háborúban áll, veszítsen 25 petákot körünként",
    "A modern demokráciák és a kommunista politikai berendezkedő országok minden körben veszítsenek 10 petákot",
    "Ne lehessen egy országban sem 30 banknál több fenntartani",
    "A kalmár köztársaság politikai berendezkedő országok nem háborúzhatnak",
    "Embargó: a választott országgal nem lehet kereskedni (kivéve a világbank)",
    "A választott ország veszítse el a meglévő tudományos pontjainak a felét!",
    "A választott ország veszítse el a rendelkezésre álló ipari termelésének és petákjainak a felét!",
    "A választott ország segítséget kap (minden erőforrásból +50-et)!",
    "Szabad javaslat tétel",
    "Szabad javaslat tétel",
    "Szabad javaslat tétel",
    "Szabad javaslat tétel"
];

$proposal_index = (($current_round / 2 - 1) % count($proposals));
$current_proposal = $proposals[$proposal_index];

// For proposals 9–12, we require a target vote input.
$requires_target = in_array($proposal_index, [9, 10, 11, 12]);

// Check if the vote has been finalized (via a global record).
$vote_finalized = false;
$query = "SELECT COUNT(*) as count FROM ensz_votes WHERE round = ? AND proposal_index = ? AND team_id = 'global' AND vote_option = 'finalized'";
$stmt = mysqli_prepare($connection, $query);
mysqli_stmt_bind_param($stmt, "ii", $current_round, $proposal_index);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $final_count);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);
if ($final_count > 0) {
    $vote_finalized = true;
}

// Retrieve all teams.
$teams = [];
$query = "SELECT id, nev, diplomaciai_pontok, winner FROM csapatok ORDER BY letrehozva";
$result = mysqli_query($connection, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $teams[] = $row;
    }
    mysqli_free_result($result);
}
$team_names = [];
foreach ($teams as $team) {
    $team_names[$team['id']] = $team['nev'];
}

// Retrieve existing votes for the current round/proposal.
$existing_votes = [];
$query = "SELECT team_id, vote_option, vote_count, target FROM ensz_votes 
          WHERE round = ? AND proposal_index = ? AND team_id <> 'global'";
$stmt = mysqli_prepare($connection, $query);
mysqli_stmt_bind_param($stmt, "ii", $current_round, $proposal_index);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $vote_team_id, $vote_option_db, $vote_count_db, $vote_target);
while (mysqli_stmt_fetch($stmt)) {
    $existing_votes[$vote_team_id] = [
         'vote_option' => $vote_option_db,
         'vote_count' => $vote_count_db,
         'target' => $vote_target
    ];
}
mysqli_stmt_close($stmt);

// Process proposal vote submission (only if not finalized).
$errors = [];
if (!$vote_finalized && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vote_option'], $_POST['vote_count'])) {
    foreach ($_POST['vote_option'] as $team_id => $submitted_value) {
        if (isset($existing_votes[$team_id])) {
            $errors[] = "A " . htmlspecialchars($team_names[$team_id] ?? $team_id, ENT_QUOTES, 'UTF-8') . " csapat már szavazott.";
            continue;
        }
        if ($requires_target) {
            if ($submitted_value === "skip") {
                continue;
            }
            if (!isset($team_names[$submitted_value])) {
                $errors[] = "Érvénytelen célország a " . htmlspecialchars($team_names[$team_id] ?? $team_id, ENT_QUOTES, 'UTF-8') . " csapatnál.";
                continue;
            }
            $insertVoteOption = "targeted";  // Constant for targeted votes.
            $chosen_target = $submitted_value;
        } else {
            if ($submitted_value !== "yes" && $submitted_value !== "no" && $submitted_value !== "skip") {
                $errors[] = "Érvénytelen szavazati opció a " . htmlspecialchars($team_names[$team_id] ?? $team_id, ENT_QUOTES, 'UTF-8') . " csapatnál.";
                continue;
            }
            $insertVoteOption = $submitted_value;
            $chosen_target = null;
        }
        $additional_votes = (int)($_POST['vote_count'][$team_id] ?? 0);
        if ($additional_votes <= 0) {
            continue;
        }
        $cost = $additional_votes * 3;
        $stmt = mysqli_prepare($connection, "SELECT diplomaciai_pontok FROM csapatok WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "s", $team_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $current_diplomacy);
        if (!mysqli_stmt_fetch($stmt)) {
            $errors[] = "Csapat nem található: " . htmlspecialchars($team_names[$team_id] ?? $team_id, ENT_QUOTES, 'UTF-8');
            mysqli_stmt_close($stmt);
            continue;
        }
        mysqli_stmt_close($stmt);
        if ($current_diplomacy < $cost) {
            $errors[] = "Nincs elegendő diplomáciai pont a " . htmlspecialchars($team_names[$team_id] ?? $team_id, ENT_QUOTES, 'UTF-8') . " csapatnál.";
            continue;
        }
        $new_diplomacy = $current_diplomacy - $cost;
        $stmt = mysqli_prepare($connection, "UPDATE csapatok SET diplomaciai_pontok = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "is", $new_diplomacy, $team_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        if ($requires_target) {
            $stmt = mysqli_prepare($connection, "INSERT INTO ensz_votes (round, team_id, proposal_index, vote_option, vote_count, target) VALUES (?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "isisis", $current_round, $team_id, $proposal_index, $insertVoteOption, $additional_votes, $chosen_target);
        } else {
            $stmt = mysqli_prepare($connection, "INSERT INTO ensz_votes (round, team_id, proposal_index, vote_option, vote_count) VALUES (?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "isisi", $current_round, $team_id, $proposal_index, $insertVoteOption, $additional_votes);
        }
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    if (empty($errors)) {
        $stmt = mysqli_prepare($connection, "INSERT INTO ensz_votes (round, team_id, proposal_index, vote_option, vote_count) VALUES (?, 'global', ?, 'finalized', 0)");
        mysqli_stmt_bind_param($stmt, "ii", $current_round, $proposal_index);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $_SESSION['success'] = "Szavazatok rögzítve és a szavazás véglegesítve!";
        $vote_finalized = true;
    } else {
        $_SESSION['error'] = implode("<br>", $errors);
    }
    header("Location: /ensz");
    exit;
}

$error = $_SESSION['error'] ?? '';
unset($_SESSION['error']);
$success = $_SESSION['success'] ?? '';
unset($_SESSION['success']);

// Aggregate vote totals.
if (!$requires_target) {
    $query = "SELECT vote_option, SUM(vote_count) as total_votes FROM ensz_votes 
          WHERE round = ? AND proposal_index = ? AND team_id <> 'global' GROUP BY vote_option";
    $stmt = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($stmt, "ii", $current_round, $proposal_index);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $vote_option_db, $total_votes);
    $vote_totals = ['yes' => 0, 'no' => 0];
    while (mysqli_stmt_fetch($stmt)) {
        if ($vote_option_db === 'yes') {
            $vote_totals['yes'] = $total_votes;
        } elseif ($vote_option_db === 'no') {
            $vote_totals['no'] = $total_votes;
        }
    }
    mysqli_stmt_close($stmt);
} else {
    $query = "SELECT target, SUM(vote_count) as total_votes FROM ensz_votes 
          WHERE round = ? AND proposal_index = ? AND team_id <> 'global' GROUP BY target";
    $stmt = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($stmt, "ii", $current_round, $proposal_index);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $target_id, $total_votes);
    $vote_totals = [];
    while (mysqli_stmt_fetch($stmt)) {
        $vote_totals[$target_id] = $total_votes;
    }
    mysqli_stmt_close($stmt);
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title>ENSZ - Világszövetség</title>
    <link rel="stylesheet" href="/public/static/css/pages/ensz.css">
</head>
<body>
    <nav>
        <a href="/round">Vissza a játék menedzsmenthez</a>
    </nav>
    <div class="container">
        <h1>ENSZ - Világszövetség</h1>
        <h2>Jelenlegi javaslat:</h2>
        <p><?php echo htmlspecialchars($current_proposal, ENT_QUOTES, 'UTF-8'); ?></p>
        
        <?php if (!$requires_target): ?>
            <div class="vote-totals">
                <p><strong>Igen</strong> szavazatok: <?php echo $vote_totals['yes']; ?></p>
                <p><strong>Nem</strong> szavazatok: <?php echo $vote_totals['no']; ?></p>
            </div>
        <?php else: ?>
            <div class="vote-totals">
                <?php foreach ($vote_totals as $tid => $count): ?>
                    <p><?php echo htmlspecialchars($team_names[$tid] ?? $tid, ENT_QUOTES, 'UTF-8'); ?>: <?php echo $count; ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div class="toast error"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="toast success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if ($vote_finalized): ?>
            <h3>Eredmény: 
            <?php 
                if (!$requires_target) {
                    if ($vote_totals['yes'] > $vote_totals['no']) {
                        echo "A javaslat elfogadásra került.";
                    } elseif ($vote_totals['yes'] < $vote_totals['no']) {
                        echo "A javaslat elutasításra került.";
                    } else {
                        echo "Döntetlen eredmény.";
                    }
                } else {
                    arsort($vote_totals);
                    $top_target = key($vote_totals);
                    echo "A legtöbb szavazat: " . htmlspecialchars($team_names[$top_target] ?? $top_target, ENT_QUOTES, 'UTF-8');
                }
            ?>
            </h3>
        <?php endif; ?>
        
        <h2>Csapatok szavazatai</h2>
        <form action="/ensz" method="post" id="globalVoteForm">
            <table>
                <thead>
                    <tr>
                        <th>Csapat</th>
                        <th>Diplomáciai pontok</th>
                        <th>Státusz</th>
                        <th>Új szavazat</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($teams as $team): 
                        $teamId = $team['id'];
                        $voted = isset($existing_votes[$teamId]);
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($team['nev'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($team['diplomaciai_pontok'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>
                            <?php if ($voted): ?>
                                Szavazva (<?php echo htmlspecialchars($existing_votes[$teamId]['vote_option'], ENT_QUOTES, 'UTF-8'); ?>, 
                                <?php echo htmlspecialchars($existing_votes[$teamId]['vote_count'], ENT_QUOTES, 'UTF-8'); ?> db)
                            <?php else: ?>
                                Nem szavazott
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!$vote_finalized): ?>
                                <?php if (!$voted): ?>
                                    <?php if (!$requires_target): ?>
                                        <label>
                                            <input type="radio" name="vote_option[<?php echo htmlspecialchars($teamId, ENT_QUOTES, 'UTF-8'); ?>]" value="yes" required> Igen
                                        </label>
                                        <label>
                                            <input type="radio" name="vote_option[<?php echo htmlspecialchars($teamId, ENT_QUOTES, 'UTF-8'); ?>]" value="no" required> Nem
                                        </label>
                                        <label>
                                            <input type="radio" name="vote_option[<?php echo htmlspecialchars($teamId, ENT_QUOTES, 'UTF-8'); ?>]" value="skip" required> Nem szavaz
                                        </label>
                                    <?php else: ?>
                                        <label>
                                            <select name="vote_option[<?php echo htmlspecialchars($teamId, ENT_QUOTES, 'UTF-8'); ?>]" required>
                                                <option value="">-- Válassz célországot --</option>
                                                <option value="skip">Nem szavaz</option>
                                                <?php foreach ($teams as $optionTeam): ?>
                                                    <option value="<?php echo htmlspecialchars($optionTeam['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                                        <?php echo htmlspecialchars($optionTeam['nev'], ENT_QUOTES, 'UTF-8'); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </label>
                                    <?php endif; ?>
                                    <label>
                                        Új szavazatok száma:
                                        <input type="number" name="vote_count[<?php echo htmlspecialchars($teamId, ENT_QUOTES, 'UTF-8'); ?>]" min="0" value="0">
                                    </label>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if (!$vote_finalized): ?>
                <button type="submit">Szavazás véglegesítése</button>
            <?php endif; ?>
        </form>
    </div>

    <!-- ================================== -->
    <!-- Winner Proposition Section (Separate from proposals) -->
    <!-- ================================== -->
    <hr>
    <div class="container" style="margin-top:30px;">
        <h2>Winner Proposition</h2>
        <?php
        // Retrieve the current winner proposition (if any) from ensz_winnerpoll.
        $winner_poll = null;
        $winner_query = "SELECT id, candidate_team_id, yes_votes, no_votes, status FROM ensz_winnerpoll WHERE status = 'ongoing' LIMIT 1";
        $winner_result = mysqli_query($connection, $winner_query);
        if ($winner_result && mysqli_num_rows($winner_result) === 1) {
            $winner_poll = mysqli_fetch_assoc($winner_result);
        }
        if ($winner_result) {
            mysqli_free_result($winner_result);
        }
        ?>

        <?php if ($winner_poll): ?>
            <?php
            $candidate_id = $winner_poll['candidate_team_id'];
            echo "<p style='margin-bottom:10px;'>Winner proposition folyamatban: <strong>" 
                 . htmlspecialchars($team_names[$candidate_id] ?? $candidate_id, ENT_QUOTES, 'UTF-8') 
                 . "</strong><br>";
            echo "Támogató szavazatok: " . (int)$winner_poll['yes_votes'] 
                 . " | Ellenvélemény szavazatok: " . (int)$winner_poll['no_votes'] . "</p>";
            ?>
            <form action="/ensz" method="post" style="margin-bottom:20px;">
                <label>Válaszd ki a szavazó csapatot (minimum 50 pont, minimum 17 szavazat): 
                    <select name="voting_team_id" required>
                        <option value="">-- Válassz csapatot --</option>
                        <?php foreach ($teams as $t): ?>
                            <option value="<?php echo htmlspecialchars($t['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars($t['nev'], ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <br>
                <label>Szavazati opció:
                    <input type="radio" name="winner_vote" value="yes" required> Támogat
                    <input type="radio" name="winner_vote" value="no" required> Ellenvélemény
                </label>
                <br>
                <label>Szavazat száma (minimum 17):
                    <input type="number" name="vote_count_winner" min="17" required>
                </label>
                <!-- Each vote costs 3 diplomacy points -->
                <input type="hidden" name="vote_winner_poll" value="1">
                <button type="submit">Szavazat leadása</button>
            </form>
            <form action="/ensz" method="post">
                <input type="hidden" name="finalize_winner_poll" value="1">
                <button type="submit">Winner proposition lezárása</button>
            </form>
        <?php else: ?>
            <form action="/ensz" method="post" style="margin-top:20px;">
                <label>Indíts winner propositiont magadra (minimum 17 szavazat, azaz legalább 51 pont szükséges):
                    <select name="poll_candidate" required>
                        <option value="">-- Válassz csapatot --</option>
                        <?php foreach ($teams as $t): ?>
                            <option value="<?php echo htmlspecialchars($t['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars($t['nev'], ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <br>
                <label>Felajánlott szavazatok száma (minimum 17):
                    <input type="number" name="poll_votes" min="17" required>
                </label>
                <input type="hidden" name="start_winner_poll" value="1">
                <button type="submit">Start Winner Proposition</button>
            </form>
        <?php endif; ?>

        <?php
        // Process Winner Proposition actions.
        // Start Winner Proposition.
        if (isset($_POST['start_winner_poll'])) {
            $candidate = trim($_POST['poll_candidate'] ?? '');
            $votes = (int)($_POST['poll_votes'] ?? 0);
            if ($votes < 17) {
                $_SESSION['error'] = "Minimum 17 szavazat szükséges a winner proposition indításához.";
            } else {
                // Cost is votes*3 diplomacy points.
                $cost = $votes * 3;
                // Check candidate's diplomacy points.
                $stmt = mysqli_prepare($connection, "SELECT diplomaciai_pontok FROM csapatok WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "s", $candidate);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_bind_result($stmt, $candidate_diplo);
                if (!mysqli_stmt_fetch($stmt)) {
                    $_SESSION['error'] = "Csapat nem található.";
                    mysqli_stmt_close($stmt);
                } else {
                    mysqli_stmt_close($stmt);
                    if ($candidate_diplo < $cost) {
                        $_SESSION['error'] = "Nincs elegendő diplomáciai pont a winner proposition indításához.";
                    } else {
                        // Deduct points from candidate.
                        $upd = mysqli_prepare($connection, "UPDATE csapatok SET diplomaciai_pontok = diplomaciai_pontok - ? WHERE id = ?");
                        mysqli_stmt_bind_param($upd, "is", $cost, $candidate);
                        mysqli_stmt_execute($upd);
                        mysqli_stmt_close($upd);
                        // Insert new winner proposition row.
                        $ins = mysqli_prepare($connection, "INSERT INTO ensz_winnerpoll (candidate_team_id, yes_votes, no_votes, status) VALUES (?, ?, 0, 'ongoing')");
                        mysqli_stmt_bind_param($ins, "si", $candidate, $votes);
                        if (mysqli_stmt_execute($ins)) {
                            $_SESSION['success'] = "Winner proposition elindítva!";
                        } else {
                            $_SESSION['error'] = "Hiba a winner proposition létrehozásakor: " . mysqli_stmt_error($ins);
                        }
                        mysqli_stmt_close($ins);
                        header("Location: /ensz");
                        exit;
                    }
                }
            }
            header("Location: /ensz");
            exit;
        }
        
        // Vote on Winner Proposition.
        if (isset($_POST['vote_winner_poll'])) {
            // Reload the current winner proposition.
            $winner_poll = null;
            $query = "SELECT id, candidate_team_id, yes_votes, no_votes, status FROM ensz_winnerpoll WHERE status = 'ongoing' LIMIT 1";
            $res = mysqli_query($connection, $query);
            if ($res && mysqli_num_rows($res) === 1) {
                $winner_poll = mysqli_fetch_assoc($res);
            }
            if ($res) { mysqli_free_result($res); }
            if (!$winner_poll) {
                $_SESSION['error'] = "Nincs aktív winner proposition.";
            } else {
                $voter = trim($_POST['voting_team_id'] ?? '');
                $vote_choice = trim($_POST['winner_vote'] ?? ''); // 'yes' or 'no'
                $vote_count = (int)($_POST['vote_count_winner'] ?? 0);
                if ($vote_count < 17) {
                    $_SESSION['error'] = "Minimum 17 szavazat szükséges a szavazáshoz.";
                } else {
                    $cost = $vote_count * 3;
                    // Check voter's diplomacy points.
                    $stmt = mysqli_prepare($connection, "SELECT diplomaciai_pontok FROM csapatok WHERE id = ?");
                    mysqli_stmt_bind_param($stmt, "s", $voter);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_bind_result($stmt, $voter_diplo);
                    if (!mysqli_stmt_fetch($stmt)) {
                        $_SESSION['error'] = "Szavazó csapat nem található.";
                        mysqli_stmt_close($stmt);
                    } else {
                        mysqli_stmt_close($stmt);
                        if ($voter_diplo < $cost) {
                            $_SESSION['error'] = "Nincs elegendő diplomáciai pont a szavazáshoz.";
                        } else {
                            // Deduct points.
                            $upd = mysqli_prepare($connection, "UPDATE csapatok SET diplomaciai_pontok = diplomaciai_pontok - ? WHERE id = ?");
                            mysqli_stmt_bind_param($upd, "is", $cost, $voter);
                            mysqli_stmt_execute($upd);
                            mysqli_stmt_close($upd);
                            // Update the winner proposition row.
                            if ($vote_choice === 'yes') {
                                $upd_poll = mysqli_prepare($connection, "UPDATE ensz_winnerpoll SET yes_votes = yes_votes + ? WHERE id = ?");
                            } else {
                                $upd_poll = mysqli_prepare($connection, "UPDATE ensz_winnerpoll SET no_votes = no_votes + ? WHERE id = ?");
                            }
                            mysqli_stmt_bind_param($upd_poll, "ii", $vote_count, $winner_poll['id']);
                            mysqli_stmt_execute($upd_poll);
                            mysqli_stmt_close($upd_poll);
                            $_SESSION['success'] = "Szavazat leadva a winner propositionben!";
                        }
                    }
                }
            }
            header("Location: /ensz");
            exit;
        }
        
        // Finalize Winner Proposition.
        if (isset($_POST['finalize_winner_poll'])) {
            $winner_poll = null;
            $query = "SELECT id, candidate_team_id, yes_votes, no_votes, status FROM ensz_winnerpoll WHERE status = 'ongoing' LIMIT 1";
            $res = mysqli_query($connection, $query);
            if ($res && mysqli_num_rows($res) === 1) {
                $winner_poll = mysqli_fetch_assoc($res);
            }
            if ($res) { mysqli_free_result($res); }
            if (!$winner_poll) {
                $_SESSION['error'] = "Nincs aktív winner proposition a lezáráshoz.";
            } else {
                if ($winner_poll['yes_votes'] > $winner_poll['no_votes']) {
                    $candidate = $winner_poll['candidate_team_id'];
                    $upd = mysqli_prepare($connection, "UPDATE csapatok SET winner=1 WHERE id = ?");
                    mysqli_stmt_bind_param($upd, "s", $candidate);
                    mysqli_stmt_execute($upd);
                    mysqli_stmt_close($upd);
                    $_SESSION['success'] = "A(z) " . htmlspecialchars($team_names[$candidate] ?? $candidate, ENT_QUOTES, 'UTF-8') . " csapat nyert!";
                } else {
                    $_SESSION['success'] = "A winner proposition lezárult, de nem lett nyertes.";
                }
                $upd_poll = mysqli_prepare($connection, "UPDATE ensz_winnerpoll SET status='final' WHERE id = ?");
                mysqli_stmt_bind_param($upd_poll, "i", $winner_poll['id']);
                mysqli_stmt_execute($upd_poll);
                mysqli_stmt_close($upd_poll);
            }
            header("Location: /ensz");
            exit;
        }
        ?>
    </div>
    <a href='/szovetsegek'>Szövetségek</a>
</body>
</html>
<?php
ob_end_flush(); // Flush output buffering
?>
