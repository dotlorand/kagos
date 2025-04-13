<?php
session_start();
include(__DIR__ . '/../components/head.php');
include_once(__DIR__ . '/../logic/game_backend.php');

if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

if (get_game_phase($connection) !== 'active') {
    header("Location: /init". "?access_key=" . htmlspecialchars($_GET['access_key'] ?? '', ENT_QUOTES, 'UTF-8'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['next_round'])) {
    if (process_game_round($connection)) {
        header("Location: /round". "?access_key=" . htmlspecialchars($_GET['access_key'] ?? '', ENT_QUOTES, 'UTF-8'));
        exit;
    } else {
        $error = "Hiba a kör lezárása során!";
    }
}

$current_round = get_current_round($connection) + 1;

if ($current_round % 2 == 1) {
    include(__DIR__ . '/../logic/odd.php');
} else {
    include(__DIR__ . '/../logic/even.php');
}


$teams = [];
$query = "SELECT id, nev FROM csapatok ORDER BY letrehozva";
$result = mysqli_query($connection, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $teams[] = $row;
    }
    mysqli_free_result($result);
} else {
    error_log("Database query failed: " . mysqli_error($connection));
}

// If no UUID is set, redirect to the first team
if (!isset($_GET['uuid']) && count($teams) > 0) {
    header("Location: /round?uuid=" . urlencode($teams[0]['id']). "&access_key=" . htmlspecialchars($_GET['access_key'] ?? '', ENT_QUOTES, 'UTF-8'));
    exit;
}


$team = null;
if (isset($_GET['uuid']) && $_GET['uuid'] !== '') {
    $uuid = trim($_GET['uuid']);
    if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid)) {
        $stmt = mysqli_prepare($connection, "SELECT * FROM csapatok WHERE id = ?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $uuid);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            if ($res && mysqli_num_rows($res) === 1) {
                $team = mysqli_fetch_assoc($res);
            }
            mysqli_stmt_close($stmt);
        }
    }
}
?>

<div class="container mt-3">

  <div class="mb-4">
    <h1 class="mb-3">Kör: <?php echo $current_round; ?></h1>
    <?php if (isset($error)): ?>
      <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php endif; ?>
  </div>

  <div class="d-flex flex-wrap gap-2 mb-4 align-items-start">
    <a href="/management?access_key=<?php echo htmlspecialchars($_GET['access_key'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-secondary">Adatbázis</a>
    <?php if ($current_round % 2 == 0): ?>
      <a href="/ensz?access_key=<?php echo htmlspecialchars($_GET['access_key'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-info">ENSZ</a>
      <a href="/szovetsegek?access_key=<?php echo htmlspecialchars($_GET['access_key'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-info">Szövetségek</a>
      <a href="/haboruk?access_key=<?php echo htmlspecialchars($_GET['access_key'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-info">Háborúk</a>
    <?php endif; ?>
    <form action="/round?access_key=<?php echo htmlspecialchars($_GET['access_key'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" method="post" onsubmit="return confirm('Biztosan el akarod indítani a következő kört?');">
      <input type="hidden" name="next_round" value="1">
      <button type="submit" class="btn btn-danger">Következő kör</button>
    </form>
  </div>

  <div class="d-flex flex-wrap gap-2 mb-4">
    <?php
    foreach ($teams as $nav_team) {
        $team_id_html = htmlspecialchars($nav_team['id'], ENT_QUOTES, 'UTF-8');
        $team_name_html = htmlspecialchars($nav_team['nev'], ENT_QUOTES, 'UTF-8');
        $active_class = (isset($_GET['uuid']) && $_GET['uuid'] == $nav_team['id']) ? 'btn-primary' : 'btn-outline-primary';
        echo "<a href='/round?uuid=" . urlencode($team_id_html) . "&access_key=" . htmlspecialchars($_GET['access_key'] ?? '', ENT_QUOTES, 'UTF-8') . "' class='btn {$active_class}'>{$team_name_html}</a>";
    }
    ?>
  </div>

  <?php if (isset($team)): ?>
    <?php if ($current_round % 2 == 1): ?>
      <div class="mb-4">
        <h2>Tudományos felfedezések</h2>
        <p class="mb-3">
          Kutatási pontok: 
          <strong style="font-size:1.3rem;">
            <?php echo htmlspecialchars($team['kutatasi_pontok'], ENT_QUOTES, 'UTF-8'); ?>
          </strong>
        </p>
      </div>

      <form class="init-form" action="/round?uuid=<?php echo urlencode($team['id']); ?>&access_key=<?php echo htmlspecialchars($_GET['access_key'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" method="post">
        <input type="hidden" name="team_id" value="<?php echo htmlspecialchars($team['id'], ENT_QUOTES, 'UTF-8'); ?>">
        <?php
          $era_requirements = [
              1 => 11,
              2 => 8,
              3 => 7,
              4 => 9,
              5 => 8,
              6 => 7
          ];
          $current_team_era   = isset($team['research_era'])   ? (int)$team['research_era']   : 1;
          $current_team_found = isset($team['research_found']) ? (int)$team['research_found'] : 0;
          $final_stage_reached = 0;
        ?>

        <?php if ($current_team_era == 6 && $current_team_found >= $era_requirements[6]): ?>
          <?php
            $final_stage_reached = 1;
            $final_cost = 50;
            $winner = isset($team['winner']) ? (int)$team['winner'] : 0;
          ?>
          <div class="card p-4 mb-3 text-center">
            <?php if ($winner): ?>
              <h3>Nyertél!</h3>
            <?php else: ?>
              <h4>Juss el a Holdra!</h4>
              <p>Költség: <?php echo htmlspecialchars($final_cost, ENT_QUOTES, 'UTF-8'); ?> pont</p>
              <button type="submit" name="discovery_action" value="final_plus" class="btn btn-success">
                OK
              </button>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <?php if (!$final_stage_reached): ?>
          <div class="row row-cols-1 row-cols-md-3 g-3">
            <?php for ($era = 1; $era <= 6; $era++): ?>
              <div class="col">
                <div class="card text-center p-3 h-100">
                  <?php if ($era < $current_team_era): ?>
                    <h5 class="mb-2">
                      <?php echo $era_requirements[$era]; ?>/<?php echo $era_requirements[$era]; ?>
                    </h5>
                    <p class="text-success fw-bold">Feloldva</p>
                  <?php elseif ($era == $current_team_era): ?>
                    <?php $cost = $era * 5; ?>
                    <h5 class="mb-2">
                      <?php echo htmlspecialchars($current_team_found, ENT_QUOTES, 'UTF-8'); ?> /
                      <?php echo htmlspecialchars($era_requirements[$era], ENT_QUOTES, 'UTF-8'); ?>
                    </h5>
                    <p class="mb-2">Költség: <?php echo htmlspecialchars($cost, ENT_QUOTES, 'UTF-8'); ?> pont</p>
                    <div class="d-flex justify-content-center gap-2">
                      <button type="submit" name="discovery_action" value="minus" class="btn btn-outline-secondary">−</button>
                      <button type="submit" name="discovery_action" value="plus" class="btn btn-outline-secondary">+</button>
                    </div>
                  <?php else: ?>
                    <p class="text-muted">Nincs feloldva</p>
                  <?php endif; ?>
                </div>
              </div>
            <?php endfor; ?>
          </div>
        <?php endif; ?>
      </form>

    <?php else: ?>
      <div class="mb-4">
        <h2>Tőzsde</h2>
        <p class="mb-0">
          <strong>Fizetőeszköz:</strong>
          Bevétel (peták): 
          <span class="text-primary fw-semibold">
            <?php echo htmlspecialchars($team['bevetel'], ENT_QUOTES, 'UTF-8'); ?>
          </span>
          , 
          Termelés: 
          <span class="text-warning fw-semibold">
            <?php echo htmlspecialchars($team['termeles'], ENT_QUOTES, 'UTF-8'); ?>
          </span>
        </p>
      </div>

      <?php
        $building_prices = [
            'bank' => [
                2=>10, 4=>15, 6=>20, 8=>30,10=>30,
                12=>10,14=>20,16=>10,18=>30,20=>40,
                22=>50,24=>25,26=>30,28=>50,30=>10,
            ],
            'gyar' => [
                2=>10, 4=>10, 6=>15, 8=>30,10=>30,
                12=>10,14=>20,16=>10,18=>20,20=>40,
                22=>50,24=>25,26=>30,28=>50,30=>10,
            ],
            'egyetem' => [
                2=>10, 4=>15, 6=>10, 8=>30,10=>10,
                12=>30,14=>20,16=>20,18=>10,20=>40,
                22=>50,24=>30,26=>25,28=>50,30=>10,
            ],
            'laktanya' => [
                2=>10, 4=>10, 6=>20, 8=>30,10=>10,
                12=>30,14=>20,16=>10,18=>30,20=>40,
                22=>50,24=>25,26=>30,28=>50,30=>10,
            ]
        ];
        $display_costs = [
            'bank'     => $building_prices['bank'][$current_round]     ?? $current_round,
            'gyar'     => $building_prices['gyar'][$current_round]     ?? $current_round,
            'egyetem'  => $building_prices['egyetem'][$current_round]  ?? $current_round,
            'laktanya' => $building_prices['laktanya'][$current_round] ?? $current_round,
        ];
        
        $counts = [
            'bank'    => $team['bankok'],
            'gyar'    => $team['gyarak'],
            'egyetem' => $team['egyetemek'],
            'laktanya'=> $team['laktanyak']
        ];

      ?>
      <div class="row row-cols-1 row-cols-md-4 g-3">
        <?php foreach (['bank','gyar','egyetem','laktanya'] as $btype): ?>
          <div class="col">
            <div class="card h-100 p-3 d-flex flex-column justify-content-between">
              <h5 class="mb-2">
                <?php 
                  echo ucfirst($btype) . " (<span class='fw-bold'>" 
                       . htmlspecialchars($counts[$btype], ENT_QUOTES, 'UTF-8') 
                       . "</span>)";
                ?>
              </h5>
              <p class="mb-2">Ár: <strong><?php echo $display_costs[$btype]; ?></strong>/épület</p>
              
              <form action="/round?uuid=<?php echo urlencode($team['id']); ?>&access_key=<?php echo htmlspecialchars($_GET['access_key'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" method="post" class="mt-auto">
                <input type="hidden" name="team_id" value="<?php echo htmlspecialchars($team['id'], ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="purchase_building" value="<?php echo $btype; ?>">

                <div class="mb-2">
                  <?php 
                    $defaultCurrency = $_SESSION['purchase_currency'][$btype] ?? '';
                    $checkedBevetel  = ($defaultCurrency === 'bevetel') ? 'checked' : '';
                    $checkedTermeles = ($defaultCurrency === 'termeles') ? 'checked' : '';
                  ?>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="purchase_currency" value="bevetel" id="r_<?php echo $btype; ?>_bevetel" <?php echo $checkedBevetel; ?> required>
                    <label class="form-check-label" for="r_<?php echo $btype; ?>_bevetel">Bevételből</label>
                  </div>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="purchase_currency" value="termeles" id="r_<?php echo $btype; ?>_termeles" <?php echo $checkedTermeles; ?> required>
                    <label class="form-check-label" for="r_<?php echo $btype; ?>_termeles">Termelésből</label>
                  </div>
                </div>

                <div class="d-flex gap-2">
                  <button type="submit" name="purchase_action" value="minus" class="btn btn-sm btn-outline-secondary">−</button>
                  <button type="submit" name="purchase_action" value="plus" class="btn btn-sm btn-outline-secondary">+</button>
                </div>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  <?php else: ?>
    <div class="text-center mt-5">
      <p class="fs-5">Válassz ki egy csapatot a fenti gombokkal.</p>
    </div>
  <?php endif; ?>

</div>

<?php include(__DIR__ . '/../components/footer.html'); ?>