<?php

ob_start();
session_start();
include(__DIR__ . '/../components/head.php');
include_once(__DIR__ . '/../logic/game_backend.php');

if (get_game_phase($connection) !== 'active') {
    header("Location: /init". "?access_key=" . htmlspecialchars($_GET['access_key'] ?? '', ENT_QUOTES, 'UTF-8'));
    exit;
}

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
        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">'
            . htmlspecialchars($_SESSION['error'], ENT_QUOTES, 'UTF-8')
            . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>'
            . '</div>';
        unset($_SESSION['error']);
    }
    if(isset($_SESSION['message'])) {
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">'
            . htmlspecialchars($_SESSION['message'], ENT_QUOTES, 'UTF-8')
            . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>'
            . '</div>';
        unset($_SESSION['message']);
    }
}

if (!isset($_SESSION['active_war']) && isset($_POST['start_war'])) {
    $attacker = trim($_POST['attacker'] ?? '');
    $defender = trim($_POST['defender'] ?? '');
    if ($attacker === '' || $defender === '') {
        $_SESSION['error'] = "Mindkét országot ki kell választani.";
        header("Location: /haboruk". "?access_key=" . htmlspecialchars($_GET['access_key'] ?? '', ENT_QUOTES, 'UTF-8'));
        exit;
    }
    if ($attacker === $defender) {
        $_SESSION['error'] = "A támadó és a védekező ország nem lehet ugyanaz.";
        header("Location: /haboruk". "?access_key=" . htmlspecialchars($_GET['access_key'] ?? '', ENT_QUOTES, 'UTF-8'));
        exit;
    }
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
        header("Location: /haboruk". "?access_key=" . htmlspecialchars($_GET['access_key'] ?? '', ENT_QUOTES, 'UTF-8'));
        exit;
    }
    
    $_SESSION['active_war'] = [
        'attacker' => $attacker,
        'defender' => $defender
    ];
    $_SESSION['message'] = "A háború elindult. Támadó: " 
                           . htmlspecialchars($names[$attacker], ENT_QUOTES, 'UTF-8') 
                           . ", Védekező: " 
                           . htmlspecialchars($names[$defender], ENT_QUOTES, 'UTF-8') 
                           . ".";
    header("Location: /haboruk". "?access_key=" . htmlspecialchars($_GET['access_key'] ?? '', ENT_QUOTES, 'UTF-8'));
    exit;
}

if (isset($_SESSION['active_war']) && isset($_POST['round_result'])) {
    $result = $_POST['round_result'];
    $attacker_id = $_SESSION['active_war']['attacker'];
    $defender_id = $_SESSION['active_war']['defender'];

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

    if ($attacker_points < 5) {
        $_SESSION['error'] = "A támadó csapatnak nincs elegendő katonai pontja a támadáshoz. A háború véget ért.";
        unset($_SESSION['active_war']);
        header("Location: /haboruk". "?access_key=" . htmlspecialchars($_GET['access_key'] ?? '', ENT_QUOTES, 'UTF-8'));
        exit;
    }

    $new_attacker_points = $attacker_points - 5;
    $stmt = mysqli_prepare($connection, "UPDATE csapatok SET katonai_pontok = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "is", $new_attacker_points, $attacker_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($result === "attacker") {
        if ($defender_points < 5) {
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

            $resources = ['bevetel', 'termeles', 'kutatasi_pontok', 'diplomaciai_pontok', 'katonai_pontok', 'bankok', 'gyarak', 'egyetemek', 'laktanyak'];

            foreach ($resources as $field) {
                $attacker_data[$field] = (int)$attacker_data[$field] + (int)$defender_data[$field];
                $defender_data[$field] = 0;
            }

            $updateQuery = "UPDATE csapatok SET bevetel = ?, termeles = ?, kutatasi_pontok = ?, diplomaciai_pontok = ?, katonai_pontok = ?, bankok = ?, gyarak = ?, egyetemek = ?, laktanyak = ? WHERE id = ?";
            $stmt = mysqli_prepare($connection, $updateQuery);

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

            $deleteDefQuery = "DELETE FROM csapatok WHERE id = ?";
            $stmt = mysqli_prepare($connection, $deleteDefQuery);
            mysqli_stmt_bind_param($stmt, "s", $defender_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            $res = mysqli_query($connection, "SELECT id FROM csapatok WHERE winner=0");
            if ($res && mysqli_num_rows($res) === 1) {
                $onlyRemaining = mysqli_fetch_assoc($res);
                $oneId = $onlyRemaining['id'];

                $updStmt = mysqli_prepare($connection, "UPDATE csapatok SET winner=1 WHERE id=?");
                mysqli_stmt_bind_param($updStmt, "s", $oneId);
                mysqli_stmt_execute($updStmt);
                mysqli_stmt_close($updStmt);

            }
            if ($res) {
                mysqli_free_result($res);
            }

            $stmt = mysqli_prepare(
                $connection, 
                "INSERT INTO haboruk (winner_id, loser_id) VALUES (?, ?)"
            );
            mysqli_stmt_bind_param($stmt, "ss", $attacker_id, $defender_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            $countQuery = "
                SELECT COUNT(*) 
                FROM csapatok 
                WHERE winner=0
                  AND id <> ?
            ";
            $stmt = mysqli_prepare($connection, $countQuery);
            mysqli_stmt_bind_param($stmt, "s", $attacker_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_bind_result($stmt, $remaining_teams_count);
            mysqli_stmt_fetch($stmt);
            mysqli_stmt_close($stmt);

            if ($remaining_teams_count > 0) {
                $countConqueredQuery = "
                    SELECT COUNT(DISTINCT loser_id) 
                    FROM haboruk 
                    WHERE winner_id = ?
                      AND loser_id IN (
                          SELECT id 
                          FROM csapatok 
                          WHERE winner=0
                            AND id <> ?
                      )
                ";
                $stmt = mysqli_prepare($connection, $countConqueredQuery);
                mysqli_stmt_bind_param($stmt, "ss", $attacker_id, $attacker_id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_bind_result($stmt, $count_conquered);
                mysqli_stmt_fetch($stmt);
                mysqli_stmt_close($stmt);

                if ($count_conquered == $remaining_teams_count) {
                    $upd = mysqli_prepare($connection, 
                        "UPDATE csapatok SET winner=1 WHERE id=?"
                    );
                    mysqli_stmt_bind_param($upd, "s", $attacker_id);
                    mysqli_stmt_execute($upd);
                    mysqli_stmt_close($upd);
                }
            }


            $_SESSION['message'] = "A támadó ország meghódította a védekező országot, és átvette annak összes erőforrását!";
            unset($_SESSION['active_war']);
            header("Location: /haboruk". "?access_key=" . htmlspecialchars($_GET['access_key'] ?? '', ENT_QUOTES, 'UTF-8'));
            exit;
        } else {
            $new_defender_points = $defender_points - 5;
            $stmt = mysqli_prepare($connection, "UPDATE csapatok SET katonai_pontok = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "is", $new_defender_points, $defender_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }

    $attacker_points = get_military_points($connection, $attacker_id);
    $defender_points = get_military_points($connection, $defender_id);

    if ($attacker_points < 5) {
        $_SESSION['error'] = "A támadó csapat elfogyott a katonai pontokból, a háború a védekező javára végződött.";
        unset($_SESSION['active_war']);
        header("Location: /haboruk". "?access_key=" . htmlspecialchars($_GET['access_key'] ?? '', ENT_QUOTES, 'UTF-8'));
        exit;
    }
    
    $_SESSION['message'] = "Kör feldolgozva. Támadó: $attacker_points pont, Védekező: $defender_points pont.";
    header("Location: /haboruk". "?access_key=" . htmlspecialchars($_GET['access_key'] ?? '', ENT_QUOTES, 'UTF-8'));
    exit;
}
?>

<div class="m-3">
  <a href="/round?access_key=<?php echo htmlspecialchars($_GET['access_key'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-secondary">Vissza a játék menedzsmenthez</a>
</div>

<div class="container mb-4">
  <h1 class="mb-4">Háborúk</h1>

  <?php display_message(); ?>

  <?php
  $milPoints = [];
  $mpRes = mysqli_query($connection, "SELECT nev, katonai_pontok FROM csapatok ORDER BY nev ASC");
  if ($mpRes) {
      while ($row = mysqli_fetch_assoc($mpRes)) {
          $milPoints[] = $row;
      }
      mysqli_free_result($mpRes);
  }
  ?>
  <div class="mb-4">
    <h3>Aktuális katonai pontok</h3>
    <table class="table table-bordered">
      <thead>
        <tr>
          <th>Csapat</th>
          <th>Katonai pontok</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($milPoints as $mp): ?>
          <tr>
            <td><?php echo htmlspecialchars($mp['nev'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars($mp['katonai_pontok'], ENT_QUOTES, 'UTF-8'); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php
  if (!isset($_SESSION['active_war'])):
      $teams = get_all_teams($connection);
  ?>
    <h2 class="mb-3">Indíts új háborút</h2>
    <form action="/haboruk?access_key=<?php echo htmlspecialchars($_GET['access_key'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" method="post" class="mb-4">
      <div class="mb-3">
        <label for="attacker" class="form-label">Támadó ország:</label>
        <select id="attacker" name="attacker" class="form-select w-auto" required>
          <option value="">-- Válassz támadót --</option>
          <?php foreach ($teams as $team): ?>
            <option value="<?php echo htmlspecialchars($team['id'], ENT_QUOTES, 'UTF-8'); ?>">
              <?php echo htmlspecialchars($team['nev'], ENT_QUOTES, 'UTF-8'); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="mb-3">
        <label for="defender" class="form-label">Védekező ország:</label>
        <select id="defender" name="defender" class="form-select w-auto" required>
          <option value="">-- Válassz védekezőt --</option>
          <?php foreach ($teams as $team): ?>
            <option value="<?php echo htmlspecialchars($team['id'], ENT_QUOTES, 'UTF-8'); ?>">
              <?php echo htmlspecialchars($team['nev'], ENT_QUOTES, 'UTF-8'); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <button type="submit" name="start_war" value="1" class="btn btn-danger">Háború indítása</button>
    </form>
  <?php
  else:
    $activeWar = $_SESSION['active_war'];
    $attacker_id = $activeWar['attacker'];
    $defender_id = $activeWar['defender'];
    
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

    $attacker_name = htmlspecialchars($teamsInfo[$attacker_id]['nev'] ?? $attacker_id, ENT_QUOTES, 'UTF-8');
    $attacker_points = htmlspecialchars($teamsInfo[$attacker_id]['katonai_pontok'] ?? '0', ENT_QUOTES, 'UTF-8');
    $defender_name = htmlspecialchars($teamsInfo[$defender_id]['nev'] ?? $defender_id, ENT_QUOTES, 'UTF-8');
    $defender_points = htmlspecialchars($teamsInfo[$defender_id]['katonai_pontok'] ?? '0', ENT_QUOTES, 'UTF-8');
  ?>
    <h2 class="mb-3">Aktív háború</h2>
    <div class="mb-4">
      <p><strong>Támadó:</strong> <?php echo $attacker_name; ?> 
         (Katonai pontok: <?php echo $attacker_points; ?>)</p>
      <p><strong>Védekező:</strong> <?php echo $defender_name; ?> 
         (Katonai pontok: <?php echo $defender_points; ?>)</p>
    </div>

    <h3>Következő kör</h3>
    <form action="/haboruk?access_key=<?php echo htmlspecialchars($_GET['access_key'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" method="post" class="mb-4">
      <p>Válaszd ki a kör győztesét:</p>
      <div class="form-check mb-2">
        <input class="form-check-input" type="radio" name="round_result" value="attacker" id="round_attacker" required>
        <label class="form-check-label" for="round_attacker">
          Támadó győzött (védekező 5 pont levonása)
        </label>
      </div>
      <div class="form-check mb-3">
        <input class="form-check-input" type="radio" name="round_result" value="defender" id="round_defender" required>
        <label class="form-check-label" for="round_defender">
          Védekező győzött (csak támadó 5 pont levonása)
        </label>
      </div>
      <button type="submit" class="btn btn-primary">Kör feldolgozása</button>
    </form>

    <form action="/haboruk?access_key=<?php echo htmlspecialchars($_GET['access_key'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" method="post">
      <button type="submit" name="cancel_war" value="1" class="btn btn-outline-secondary">Aktív háború megszakítása</button>
    </form>

    <?php
      if (isset($_POST['cancel_war'])) {
          unset($_SESSION['active_war']);
          $_SESSION['message'] = "Az aktív háború megszakadt.";
          header("Location: /haboruk". "?access_key=" . htmlspecialchars($_GET['access_key'] ?? '', ENT_QUOTES, 'UTF-8'));
          exit;
      }
    ?>
  <?php endif; ?>
</div>

<?php
ob_end_flush();
include(__DIR__ . '/../components/footer.html');
?>
