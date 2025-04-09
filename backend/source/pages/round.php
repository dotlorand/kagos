<?php
session_start();
include(__DIR__ . '/../components/head.php');
include_once(__DIR__ . '/../logic/game_backend.php');

// Check if a flash error message exists.
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

if (get_game_phase($connection) !== 'active') {
    header("Location: /init");
    exit;
}

// next round
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['next_round'])) {
    if (process_game_round($connection)) {
        header("Location: /round");
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
?>
<link rel="stylesheet" href="/public/static/css/pages/manage.css">

<div class="container">
    <h1>Játék Menedzsment</h1>
    <?php if (isset($error)) : ?>
        <div class="toast error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <section class="round-actions">
        <ul>
            <a href='/management'>Adatbázis</a>
            <?php
                if ($current_round % 2 == 0) {
                    echo "<a href='/ensz'>ENSZ</a>";
                    echo "<a href='/szovetsegek'>Szövetségek</a>";
                    echo "<a href='/haboruk'>Háborúk</a>";
                }
            ?>
        </ul>
        <form action="/round" method="post" style="display:inline;">
            <input type="hidden" name="next_round" value="1">
            <button type="submit" class="next-round">Következő kör</button>
        </form>
    </section>
</div>

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
                echo "<li><a href='/round?uuid=" . urlencode($team_id_html) . "' class='{$active_class}'>{$team_name_html}</a></li>";
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
            <h1>
                <?php
                if ($current_round % 2 == 1) {
                    echo "Tudományos felfedezések";
                    echo "<p style='margin-top:6px;font-size:18px;'>Kutatási pontok: <b style='font-weight:bold;font-size:24px;'>" . htmlspecialchars($team['kutatasi_pontok'], ENT_QUOTES, 'UTF-8') . "</b></p>";
                } else {
                    echo "Tőzsde";
                }
                ?>
            </h1>
        </div>
        <?php if ($current_round % 2 == 1) : ?>
            <!-- Odd round UI (e.g. research actions) -->
            <form class="init-form" action="/round?uuid=<?php echo urlencode($team['id']); ?>" method="post">
                <input type="hidden" name="team_id" value="<?php echo htmlspecialchars($team['id'], ENT_QUOTES, 'UTF-8'); ?>">
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
                                <div class="btns">
                                    <button type="submit" name="discovery_action" value="final_plus">OK</button>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php
                    }
                    ?>
                    <?php if ($final_stage_reached == 0): ?>
                        <?php for ($era = 1; $era <= 6; $era++): ?>
                            <div class="korszak">
                                <?php if ($era < $current_team_era): ?>
                                    <!-- már feloldva -->
                                    <h1><?php echo htmlspecialchars($era_requirements[$era], ENT_QUOTES, 'UTF-8'); ?>/<?php echo htmlspecialchars($era_requirements[$era], ENT_QUOTES, 'UTF-8'); ?></h1>
                                    <p>Feloldva</p>
                                <?php elseif ($era == $current_team_era): ?>
                                    <!-- jelenlegi korszak -->
                                    <?php $cost = $era * 5; ?>
                                    <h1 style="margin-top:20px;"><?php echo htmlspecialchars($current_team_found, ENT_QUOTES, 'UTF-8'); ?>/<?php echo htmlspecialchars($era_requirements[$era], ENT_QUOTES, 'UTF-8'); ?></h1>
                                    <p>Ár: <?php echo htmlspecialchars($cost, ENT_QUOTES, 'UTF-8'); ?> pont</p>
                                    <div class="btns">
                                        <button type="submit" name="discovery_action" value="minus">−</button>
                                        <button type="submit" name="discovery_action" value="plus">+</button>
                                    </div>
                                <?php else: ?>
                                    <p>Nincs feloldva</p>
                                <?php endif; ?>
                            </div>
                        <?php endfor; ?>
                    <?php endif; ?>
                </div>
            </form>
        <?php else: ?>
            <!-- Even round UI: each building gets its own form -->
            <p style="margin-block: 10px; font-size:20px;">
                <span>Fizetőeszköz:</span>
                <b>
                    Bevétel (peták): <span style="color:blue"><?php echo htmlspecialchars($team['bevetel'], ENT_QUOTES, 'UTF-8'); ?></span>, 
                    Termelés: <span style="color:darkorange"><?php echo htmlspecialchars($team['termeles'], ENT_QUOTES, 'UTF-8'); ?></span>
                </b>
            </p>
            <?php
            // Building prices for display (same structure used in even.php)
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
            $display_costs = [
                'bank'    => $building_prices['bank'][$current_round]    ?? ($current_round),
                'gyar'    => $building_prices['gyar'][$current_round]    ?? ($current_round),
                'egyetem' => $building_prices['egyetem'][$current_round] ?? ($current_round),
                'laktanya'=> $building_prices['laktanya'][$current_round]?? ($current_round),
            ];
            ?>
            <div class="tozsde-container">
                <!-- For each building, a separate form -->
                <?php foreach (['bank', 'gyar', 'egyetem', 'laktanya'] as $btype): ?>
                    <form class="tozsde-item <?php echo "epulet-". $btype?>" action="/round?uuid=<?php echo urlencode($team['id']); ?>" method="post">
                        <input type="hidden" name="team_id" value="<?php echo htmlspecialchars($team['id'], ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="purchase_building" value="<?php echo $btype; ?>">
                        <div>
                            <?php 
                                // Display current count based on type.
                                $counts = [
                                    'bank' => $team['bankok'],
                                    'gyar' => $team['gyarak'],
                                    'egyetem' => $team['egyetemek'],
                                    'laktanya' => $team['laktanyak']
                                ];
                                echo "<h2 style='font-weight:normal;'>" . ucfirst($btype) . " (<span style='font-weight:bold;'>" . htmlspecialchars($counts[$btype], ENT_QUOTES, 'UTF-8') . "</span>)</h2>";
                                echo "<p style='margin-block:10px;font-size:18px;'>Ár: <b>" . $display_costs[$btype] . "</b>/épület</p>"
                            ?>
                        </div>
                        <div class="tozsde-purchase-opt">
                        <?php $defaultCurrency = $_SESSION['purchase_currency'][$btype] ?? ''; ?>
                            <label>
                                <input type="radio" name="purchase_currency" value="bevetel" <?php echo ($defaultCurrency === 'bevetel') ? 'checked' : ''; ?> required>
                                Bevételből
                            </label>
                            <label style="margin-left:10px;">
                                <input type="radio" name="purchase_currency" value="termeles" <?php echo ($defaultCurrency === 'termeles') ? 'checked' : ''; ?> required>
                                Termelésből
                            </label>
                        </div>
                        <div class="btns">
                            <button type="submit" name="purchase_action" value="minus">−</button>
                            <button type="submit" name="purchase_action" value="plus">+</button>
                        </div>
                    </form>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<script src="/public/static/js/manage.js"></script>
<?php include(__DIR__ . '/../components/footer.html'); ?>
