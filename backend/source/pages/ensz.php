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
    header("Location: /init" . "?access_key=" . htmlspecialchars($_GET['access_key'] ?? '', ENT_QUOTES, 'UTF-8'));
    exit;
}

// Get the current round (like in manage_game.php).
$current_round = get_current_round($connection) + 1;

// ENSZ is available only on even rounds.
if ($current_round % 2 !== 0) {
    header("Location: /round" . "?access_key=" . htmlspecialchars($_GET['access_key'] ?? '', ENT_QUOTES, 'UTF-8'));
    exit;
}

// Define the list of proposals.
$proposals = [
    "Minden ország kapjon +1 termelést",
    "Minden ország kapjon +1 tudományos pontot",
    "Minden ország kapjon +1 katonai pontot",
    "A törzsi falu politikai berendezkedéssel rendelkező államok kapjanak +1 katonai pontot körönként",
    "Az arisztokratikus köztársaság és a türannisz politikai berendezkedéssel rendelkező országok kapjanak +1 katonai pontot és +1 termelést körünként",
    "Minden olyan ország, amely háborúban áll, veszítsen 25 petákot körönként",
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
    "Szabad javaslat tétel",
    "Szabad javaslat tétel",
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
?>

<nav class="m-3">
    <a href="/round?access_key=<?php echo htmlspecialchars($_GET['access_key'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-secondary">Vissza a játék menedzsmenthez</a>
</nav>

<div class="container mb-4">
    <h1 class="mb-4">ENSZ</h1>
    
    <?php if (!empty($_SESSION['error'])): ?>
      <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo $_SESSION['error']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['success'])): ?>
      <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo $_SESSION['success']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php endif;
          unset($_SESSION['error']); unset($_SESSION['success']); ?>
    
    <div class="card mb-4">
      <div class="card-body">
        <h4 class="card-title">Jelenlegi javaslat:</h4>
        <p class="card-text"><?php echo htmlspecialchars($current_proposal, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php if (!$requires_target): ?>
          <h5>Szavazatok összesen:</h5>
          <p><strong>Igen:</strong> <?php echo $vote_totals['yes'] ?? 0; ?></p>
          <p><strong>Nem:</strong> <?php echo $vote_totals['no'] ?? 0; ?></p>
        <?php else: ?>
          <h5>Szavazatok összesen (célországok):</h5>
          <?php if (empty($vote_totals)): ?>
            <p>Nincs leadott szavazat.</p>
          <?php else: ?>
            <?php foreach ($vote_totals as $tid => $count): ?>
              <p>
                <?php echo htmlspecialchars($team_names[$tid] ?? $tid, ENT_QUOTES, 'UTF-8'); ?>: 
                <?php echo $count; ?> szavazat
              </p>
            <?php endforeach; ?>
          <?php endif; ?>
        <?php endif; ?>
        <?php if ($vote_finalized): ?>
          <hr>
          <h5 class="card-subtitle mb-2">Eredmény:</h5>
          <?php if (!$requires_target): ?>
            <?php if (($vote_totals['yes'] ?? 0) > ($vote_totals['no'] ?? 0)): ?>
              <p class="text-success fw-bold">A javaslat elfogadásra került.</p>
            <?php elseif (($vote_totals['yes'] ?? 0) < ($vote_totals['no'] ?? 0)): ?>
              <p class="text-danger fw-bold">A javaslat elutasításra került.</p>
            <?php else: ?>
              <p class="fw-bold">Döntetlen eredmény.</p>
            <?php endif; ?>
          <?php else: ?>
            <?php 
              arsort($vote_totals);
              $top_target = key($vote_totals);
              if (!empty($top_target)) {
                echo "<p class='fw-bold'>A legtöbb szavazatot kapott ország: "
                     . htmlspecialchars($team_names[$top_target] ?? $top_target, ENT_QUOTES, 'UTF-8')
                     . "</p>";
              } else {
                echo "<p class='fw-bold'>Egyik célország sem kapott szavazatot.</p>";
              }
            ?>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
    
    <form action="/ensz?access_key=<?php echo htmlspecialchars($_GET['access_key'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" method="post" id="globalVoteForm">
      <div class="table-responsive">
        <table class="table table-striped align-middle">
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
                  Szavazva (
                  <?php 
                    echo htmlspecialchars($existing_votes[$teamId]['vote_option'], ENT_QUOTES, 'UTF-8') . ", ";
                    echo htmlspecialchars($existing_votes[$teamId]['vote_count'], ENT_QUOTES, 'UTF-8');
                  ?> db)
                <?php else: ?>
                  Nem szavazott
                <?php endif; ?>
              </td>
              <td>
                <?php if (!$vote_finalized && !$voted): ?>
                  <?php if (!$requires_target): ?>
                    <div class="d-flex flex-wrap gap-2">
                      <div class="form-check">
                        <input class="form-check-input" type="radio" name="vote_option[<?php echo htmlspecialchars($teamId, ENT_QUOTES, 'UTF-8'); ?>]" value="yes" required>
                        <label class="form-check-label">Igen</label>
                      </div>
                      <div class="form-check">
                        <input class="form-check-input" type="radio" name="vote_option[<?php echo htmlspecialchars($teamId, ENT_QUOTES, 'UTF-8'); ?>]" value="no" required>
                        <label class="form-check-label">Nem</label>
                      </div>
                      <div class="form-check">
                        <input class="form-check-input" type="radio" name="vote_option[<?php echo htmlspecialchars($teamId, ENT_QUOTES, 'UTF-8'); ?>]" value="skip" required>
                        <label class="form-check-label">Nem szavaz</label>
                      </div>
                    </div>
                  <?php else: ?>
                    <select name="vote_option[<?php echo htmlspecialchars($teamId, ENT_QUOTES, 'UTF-8'); ?>]" class="form-select mt-2" required>
                      <option value="">-- Válassz célországot --</option>
                      <option value="skip">Nem szavaz</option>
                      <?php foreach ($teams as $optionTeam): ?>
                        <option value="<?php echo htmlspecialchars($optionTeam['id'], ENT_QUOTES, 'UTF-8'); ?>">
                          <?php echo htmlspecialchars($optionTeam['nev'], ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  <?php endif; ?>
                  <div class="mt-2">
                    <label class="form-label">Új szavazatok száma:</label>
                    <input type="number" name="vote_count[<?php echo htmlspecialchars($teamId, ENT_QUOTES, 'UTF-8'); ?>]" min="0" value="0" class="form-control w-auto d-inline-block">
                  </div>
                <?php else: ?>
                  -
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php if (!$vote_finalized): ?>
        <button type="submit" class="btn btn-primary">Szavazás véglegesítése</button>
      <?php endif; ?>
    </form>
</div>

<hr class="my-4">

<div class="container mb-5">
    <h2 class="text-warning">Győzelmi indítvány benyújtása</h2>
    <?php
    $winner_poll = null;
    $winner_query = "SELECT id, candidate_team_id, yes_votes, no_votes, status FROM ensz_winnerpoll WHERE status = 'ongoing' LIMIT 1";
    $winner_result = mysqli_query($connection, $winner_query);
    if ($winner_result && mysqli_num_rows($winner_result) === 1) {
        $winner_poll = mysqli_fetch_assoc($winner_result);
    }
    if ($winner_result) {
        mysqli_free_result($winner_result);
    }
    if ($winner_poll):
        $candidate_id = $winner_poll['candidate_team_id'];
    ?>
        <div class="alert alert-info">
            Folyamatban:<br>
            Indította: <strong><?php echo htmlspecialchars($team_names[$candidate_id] ?? $candidate_id, ENT_QUOTES, 'UTF-8'); ?></strong><br>
            Támogató szavazatok: <strong><?php echo (int)$winner_poll['yes_votes']; ?></strong> |
            Ellen szavazatok: <strong><?php echo (int)$winner_poll['no_votes']; ?></strong>
        </div>
        <form action="/ensz?access_key=<?php echo htmlspecialchars($_GET['access_key'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" method="post" id="winnerVoteForm">
          <div class="table-responsive">
            <table class="table table-striped align-middle">
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
                      Szavazva (
                      <?php 
                        echo htmlspecialchars($existing_votes[$teamId]['vote_option'], ENT_QUOTES, 'UTF-8') . ", ";
                        echo htmlspecialchars($existing_votes[$teamId]['vote_count'], ENT_QUOTES, 'UTF-8');
                      ?> db)
                    <?php else: ?>
                      Nem szavazott
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if (!$vote_finalized && !$voted): ?>
                      <?php if (!$requires_target): ?>
                        <div class="d-flex flex-wrap gap-2">
                          <div class="form-check">
                            <input class="form-check-input" type="radio" name="vote_option[<?php echo htmlspecialchars($teamId, ENT_QUOTES, 'UTF-8'); ?>]" value="yes" required>
                            <label class="form-check-label">Igen</label>
                          </div>
                          <div class="form-check">
                            <input class="form-check-input" type="radio" name="vote_option[<?php echo htmlspecialchars($teamId, ENT_QUOTES, 'UTF-8'); ?>]" value="no" required>
                            <label class="form-check-label">Nem</label>
                          </div>
                          <div class="form-check">
                            <input class="form-check-input" type="radio" name="vote_option[<?php echo htmlspecialchars($teamId, ENT_QUOTES, 'UTF-8'); ?>]" value="skip" required>
                            <label class="form-check-label">Nem szavaz</label>
                          </div>
                        </div>
                      <?php else: ?>
                        <select name="vote_option[<?php echo htmlspecialchars($teamId, ENT_QUOTES, 'UTF-8'); ?>]" class="form-select mt-2" required>
                          <option value="">-- Válassz célországot --</option>
                          <option value="skip">Nem szavaz</option>
                          <?php foreach ($teams as $optionTeam): ?>
                            <option value="<?php echo htmlspecialchars($optionTeam['id'], ENT_QUOTES, 'UTF-8'); ?>">
                              <?php echo htmlspecialchars($optionTeam['nev'], ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                          <?php endforeach; ?>
                        </select>
                      <?php endif; ?>
                      <div class="mt-2">
                        <label class="form-label">Új szavazatok száma:</label>
                        <input type="number" name="vote_count[<?php echo htmlspecialchars($teamId, ENT_QUOTES, 'UTF-8'); ?>]" min="0" value="0" class="form-control w-auto d-inline-block">
                      </div>
                    <?php else: ?>
                      -
                    <?php endif; ?>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <input type="hidden" name="vote_winner_poll" value="1">
          <button type="submit" class="btn btn-primary mb-2">Szavazás véglegesítése</button>
        </form>
        <form action="/ensz?access_key=<?php echo htmlspecialchars($_GET['access_key'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" method="post">
            <input type="hidden" name="finalize_winner_poll" value="1">
            <button type="submit" class="btn btn-danger">Javaslat lezárása</button>
        </form>
    <?php else: ?>
        <form action="/ensz?access_key=<?php echo htmlspecialchars($_GET['access_key'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" method="post" class="mt-3">
            <label class="form-label">(Költség: 50 diplomáciai pont):</label>
            <div class="mb-2">
                <select name="poll_candidate" class="form-select w-auto d-inline-block" required>
                    <option value="">-- Válassz csapatot --</option>
                    <?php foreach ($teams as $t): ?>
                        <option value="<?php echo htmlspecialchars($t['id'], ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars($t['nev'], ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <input type="hidden" name="start_winner_poll" value="1">
            <button type="submit" class="btn btn-primary">Győzelmi javaslat inditása</button>
        </form>
    <?php endif; ?>
</div>

<?php

if (isset($_POST['start_winner_poll'])) {
    $candidate = trim($_POST['poll_candidate'] ?? '');
    $cost = 50;
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
            $_SESSION['error'] = "Nincs elegendő diplomáciai pont a győzelmi javaslat indításához.";
        } else {
            $upd = mysqli_prepare($connection, "UPDATE csapatok SET diplomaciai_pontok = diplomaciai_pontok - ? WHERE id = ?");
            mysqli_stmt_bind_param($upd, "is", $cost, $candidate);
            mysqli_stmt_execute($upd);
            mysqli_stmt_close($upd);
            $ins = mysqli_prepare($connection, "INSERT INTO ensz_winnerpoll (candidate_team_id, yes_votes, no_votes, status) VALUES (?, 0, 0, 'ongoing')");
            mysqli_stmt_bind_param($ins, "s", $candidate);
            if (mysqli_stmt_execute($ins)) {
                $_SESSION['success'] = "Győzelmi javaslat elindítva!";
            } else {
                $_SESSION['error'] = "Hiba a győzelmi javaslat létrehozásakor: " . mysqli_stmt_error($ins);
            }
            mysqli_stmt_close($ins);
            header("Location: /ensz?access_key=" . htmlspecialchars($_GET['access_key'] ?? '', ENT_QUOTES, 'UTF-8'));
            exit;
        }
    }
    header("Location: /ensz?access_key=" . htmlspecialchars($_GET['access_key'] ?? '', ENT_QUOTES, 'UTF-8'));
    exit;
}

if (isset($_POST['vote_winner_poll'])) {
    $votesProcessed = false;
    $winner_poll = null;
    $query = "SELECT id, candidate_team_id, yes_votes, no_votes, status FROM ensz_winnerpoll WHERE status = 'ongoing' LIMIT 1";
    $res = mysqli_query($connection, $query);
    if ($res && mysqli_num_rows($res) === 1) {
        $winner_poll = mysqli_fetch_assoc($res);
    }
    if ($res) { mysqli_free_result($res); }
    if (!$winner_poll) {
        $_SESSION['error'] = "Nincs aktív győzelmi javaslat.";
    } else {
        foreach ($_POST['vote_option'] as $teamId => $vote_choice) {
            $vote_count = (int)($_POST['vote_count'][$teamId] ?? 0);
            if ($vote_count <= 0) {
                continue;
            }
            $votesProcessed = true;
            $cost = $vote_count * 3;
            $stmt = mysqli_prepare($connection, "SELECT diplomaciai_pontok FROM csapatok WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "s", $teamId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_bind_result($stmt, $voter_diplo);
            if (!mysqli_stmt_fetch($stmt)) {
                $_SESSION['error'] = "Szavazó csapat (" . htmlspecialchars($team_names[$teamId] ?? $teamId, ENT_QUOTES, 'UTF-8') . ") nem található.";
                mysqli_stmt_close($stmt);
                continue;
            }
            mysqli_stmt_close($stmt);
            if ($voter_diplo < $cost) {
                $_SESSION['error'] = "Nincs elegendő diplomáciai pont a " . htmlspecialchars($team_names[$teamId] ?? $teamId, ENT_QUOTES, 'UTF-8') . " csapat szavazatához.";
                continue;
            }
            $upd = mysqli_prepare($connection, "UPDATE csapatok SET diplomaciai_pontok = diplomaciai_pontok - ? WHERE id = ?");
            mysqli_stmt_bind_param($upd, "is", $cost, $teamId);
            mysqli_stmt_execute($upd);
            mysqli_stmt_close($upd);
            if ($vote_choice === 'yes') {
                $upd_poll = mysqli_prepare($connection, "UPDATE ensz_winnerpoll SET yes_votes = yes_votes + ? WHERE id = ?");
            } else if ($vote_choice === 'no') {
                $upd_poll = mysqli_prepare($connection, "UPDATE ensz_winnerpoll SET no_votes = no_votes + ? WHERE id = ?");
            } else {
                continue;
            }
            mysqli_stmt_bind_param($upd_poll, "ii", $vote_count, $winner_poll['id']);
            mysqli_stmt_execute($upd_poll);
            mysqli_stmt_close($upd_poll);
        }
        if (!$votesProcessed) {
            $_SESSION['success'] = "Nincs szavazat leadva.";
        } else {
            $_SESSION['success'] = "Szavazatok leadva a győzelmi javaslatban!";
        }
    }
    header("Location: /ensz?access_key=" . htmlspecialchars($_GET['access_key'] ?? '', ENT_QUOTES, 'UTF-8'));
    exit;
}

if (isset($_POST['finalize_winner_poll'])) {
    $winner_poll = null;
    $query = "SELECT id, candidate_team_id, yes_votes, no_votes, status FROM ensz_winnerpoll WHERE status = 'ongoing' LIMIT 1";
    $res = mysqli_query($connection, $query);
    if ($res && mysqli_num_rows($res) === 1) {
        $winner_poll = mysqli_fetch_assoc($res);
    }
    if ($res) { mysqli_free_result($res); }
    if (!$winner_poll) {
        $_SESSION['error'] = "Hiba. Nincs győzelmi javaslat.";
    } else {
        if ($winner_poll['yes_votes'] > $winner_poll['no_votes']) {
            $candidate = $winner_poll['candidate_team_id'];
            $upd = mysqli_prepare($connection, "UPDATE csapatok SET winner=1 WHERE id = ?");
            mysqli_stmt_bind_param($upd, "s", $candidate);
            mysqli_stmt_execute($upd);
            mysqli_stmt_close($upd);
            $_SESSION['success'] = "A(z) " . htmlspecialchars($team_names[$candidate] ?? $candidate, ENT_QUOTES, 'UTF-8') . " csapat nyert!";
        } else {
            $_SESSION['success'] = "A győzelmi javaslat lezárult, de nem lett nyertes.";
        }
        $upd_poll = mysqli_prepare($connection, "UPDATE ensz_winnerpoll SET status='final' WHERE id = ?");
        mysqli_stmt_bind_param($upd_poll, "i", $winner_poll['id']);
        mysqli_stmt_execute($upd_poll);
        mysqli_stmt_close($upd_poll);
    }
    header("Location: /ensz?access_key=" . htmlspecialchars($_GET['access_key'] ?? '', ENT_QUOTES, 'UTF-8'));
    exit;
}

ob_end_flush();
include(__DIR__ . '/../components/footer.html');
