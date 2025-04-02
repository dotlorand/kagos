<?php
include(__DIR__ . '/../components/head.php');
include_once(__DIR__ . '/../logic/game_backend.php');

if (get_game_phase($connection) !== 'active') {
    header("Location: /init");
    exit;
}

// next round
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['next_round'])) {
    if (process_game_round($connection)) {
        header("Location: /manage-game");
        exit;
    } else {
        $error = "Hiba a kör lezárása során!";
    }
}

// ==========================================
//                  TUDOMÁNY
// ==========================================

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
        header("Location: /manage-game?uuid=" . urlencode($team_id));
        exit;
    }
}

$current_round = get_current_round($connection) + 1;
$final_stage_reached = 0;
?>
<link rel="stylesheet" href="/public/static/css/pages/manage.css">

<div class="container">
    <h1>Játék Menedzsment</h1>
    <p>Jelenlegi kör: <?php echo htmlspecialchars($current_round, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php if (isset($error)) : ?>
        <div class="toast error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
</div>

<section class="round-actions">
    <form action="/manage-game" method="post" style="display:inline;">
        <input type="hidden" name="next_round" value="1">
        <button type="submit">Következő kör</button>
    </form>
</section>

<nav class="teams-nav">
    <ul>
        <?php
        $query = "SELECT id, nev FROM csapatok ORDER BY letrehozva";
        $result = mysqli_query($connection, $query);
        if (!$result) {
            error_log("Database query failed: " . mysqli_error($connection));
            echo "<li>Hiba a csapatok lekérdezésével.</li>";
        } else {
            while ($nav_team = mysqli_fetch_assoc($result)) {
                $team_id_html = htmlspecialchars($nav_team['id'], ENT_QUOTES, 'UTF-8');
                $team_name_html = htmlspecialchars($nav_team['nev'], ENT_QUOTES, 'UTF-8');
                $active_class = (isset($_GET['uuid']) && $_GET['uuid'] == $nav_team['id']) ? 'active' : '';
                echo "<li><a href='/manage-game?uuid=" . urlencode($team_id_html) . "' class='{$active_class}'>{$team_name_html}</a></li>";
            }
            mysqli_free_result($result);
        }
        ?>
    </ul>
</nav>

<?php
// get team data
if (isset($_GET['uuid']) && $_GET['uuid'] !== '') {
    $uuid = trim($_GET['uuid']);
    if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid)) {
        $stmt = mysqli_prepare($connection, "SELECT * FROM csapatok WHERE id = ?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $uuid);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            if ($result && mysqli_num_rows($result) === 1) {
                $team = mysqli_fetch_assoc($result);
            }
            mysqli_stmt_close($stmt);
        }
    }
}
if (isset($team)) :
?>
    <div class="container">
        <div class="container-header">
            <h1 style="font-size:20px;">
                <span><?php echo htmlspecialchars($team['nev'], ENT_QUOTES, 'UTF-8'); ?></span>
                <?php
                if ($current_round % 2 == 1) {
                    echo " tudományos felfedezései";
                    echo "<p style='margin-top:6px;font-size:18px;'>Kutatási pontok: <b style='font-weight:bold;font-size:24px;'>" . htmlspecialchars($team['kutatasi_pontok'], ENT_QUOTES, 'UTF-8') . "</b></p>";
                } else {
                    echo "<b style='color:#1c85db'>Tőzsde, ENSZ, Szövetség, Háború</b>";
                }
                ?>
            </h1>
        </div>
        <form class="init-form" action="/manage-game?uuid=<?php echo urlencode($team['id']); ?>" method="post">
            <input type="hidden" name="team_id" value="<?php echo htmlspecialchars($team['id'], ENT_QUOTES, 'UTF-8'); ?>">
            <?php if ($current_round % 2 == 1) : ?>
                <div class="felfedezesek-container">
                    <?php
                    $era_requirements = [
                        1 => 11,
                        2 => 8,
                        3 => 7,
                        4 => 9,
                        5 => 8,
                        6 => 7
                    ];
                    $current_team_era = isset($team['research_era']) ? (int)$team['research_era'] : 1;
                    $current_team_found = isset($team['research_found']) ? (int)$team['research_found'] : 0;
                    ?>
                    <?php

                    // végső felfedezés - holdra jutás
                    if ($current_team_era == 6 && $current_team_found >= $era_requirements[6]) {
                        $final_stage_reached = 1;
                        $final_cost = 50;
                        $winner = isset($team['winner']) ? (int)$team['winner'] : 0;
                        ?>
                        <div class="korszak final-stage">
                            <?php if ($winner): ?>
                                <h1>Nyertél!</h1>
                            <?php else: ?>
                                <b>Juss el a Holdra!</b>
                                <p>Költség: <?php echo htmlspecialchars($final_cost, ENT_QUOTES, 'UTF-8'); ?> pont</p>
                                <div class="korszak-btns">
                                    <button type="submit" name="discovery_action" value="final_plus">OK</button>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php
                    }
                    ?>
                    <?php if (!$final_stage_reached): ?>
                        <?php for ($era = 1; $era <= 6; $era++): ?>
                            <div class="korszak">
                                <?php if ($era < $current_team_era): ?>
                                    <!-- ami fel van oldva -->
                                    <h1><?php echo htmlspecialchars($era_requirements[$era], ENT_QUOTES, 'UTF-8'); ?>/<?php echo htmlspecialchars($era_requirements[$era], ENT_QUOTES, 'UTF-8'); ?></h1>
                                    <p>Feloldva</p>
                                <?php elseif ($era == $current_team_era): ?>
                                    <!-- jelenlegi korszak -->
                                    <?php $cost = $era * 5; ?>
                                    <h1><?php echo htmlspecialchars($current_team_found, ENT_QUOTES, 'UTF-8'); ?>/<?php echo htmlspecialchars($era_requirements[$era], ENT_QUOTES, 'UTF-8'); ?></h1>
                                    <p>Költség: <?php echo htmlspecialchars($cost, ENT_QUOTES, 'UTF-8'); ?> pont</p>
                                    <div class="korszak-btns">
                                        <button type="submit" name="discovery_action" value="minus">-</button>
                                        <button type="submit" name="discovery_action" value="plus">+</button>
                                    </div>
                                <?php else: ?>
                                    <p>Nincs feloldva</p>
                                <?php endif; ?>
                            </div>
                        <?php endfor; ?>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <!-- paros -->
                <div>meg nincs kesz</div>
                <input type="submit" class="submit-btn" name="megerosites" value="Megerősítés">
            <?php endif; ?>
        </form>
    </div>
<?php endif; ?>

<script src="/public/static/js/manage.js"></script>
<?php include(__DIR__ . '/../components/footer.html'); ?>
