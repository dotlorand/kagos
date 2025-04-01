<?php
include(__DIR__ . '/../components/head.php');
include_once(__DIR__ . '/../logic/game_backend.php');

if (get_game_phase($connection) !== 'active') {
    header("Location: /init");
    exit;
}

// end round (nevcsere kell > next_round)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['end_round'])) {
    if (process_game_round($connection)) {
        header("Location: /manage-game");
        exit;
    } else {
        $error = "Hiba a kör lezárása során!";
    }
}

$current_round = get_current_round($connection);

$actions = [];
if ($current_round % 2 === 1) {
    $actions[] = ['label' => 'Politika tervezése', 'link' => '/actions/plan_policy.php'];
    $actions[] = ['label' => 'Tudományos kutatás vásárlása', 'link' => '/actions/buy_research.php'];
} else {
    $actions[] = ['label' => 'Épület vásárlás', 'link' => '/actions/buy_building.php'];
    $actions[] = ['label' => 'Világkongresszus', 'link' => '/actions/un.php'];
    $actions[] = ['label' => 'Háború indítása', 'link' => '/actions/declare_war.php'];
}
?>
<link rel="stylesheet" href="/public/static/css/pages/manage.css">

<div class="gameplay-header">
    <h1>Játék Menedzsment</h1>
    <p>Jelenlegi kör: <?php echo htmlspecialchars($current_round + 1, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php if (isset($error)) : ?>
        <div class="toast error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
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
                echo "<li><a href='/manage-game?uuid=" . urlencode($team_id_html) . "' class='{$active_class}'>{$team_name_html}</a></li>";
            }
            mysqli_free_result($result);
        }
        ?>
        <button onclick="popup('add-team')"><img class="icon" src="/public/static/icons/plus.svg">Új csapat</button>
    </ul>
</nav>

<section class="gameplay-actions">
    <?php foreach ($actions as $action): ?>
        <button onclick="location.href='<?php echo htmlspecialchars($action['link'], ENT_QUOTES, 'UTF-8'); ?>'">
            <?php echo htmlspecialchars($action['label'], ENT_QUOTES, 'UTF-8'); ?>
        </button>
    <?php endforeach; ?>
    <form action="/manage-game" method="post" style="display:inline;">
        <input type="hidden" name="end_round" value="1">
        <button type="submit">Kör lezárása</button>
    </form>
</section>

<section class="teams-status">
    <h2>Csapatok állapota</h2>
    <?php
    $query = "SELECT * FROM csapatok ORDER BY letrehozva";
    $result = mysqli_query($connection, $query);
    if (!$result) {
        echo "<p>Hiba a csapatok lekérdezésével: " . mysqli_error($connection) . "</p>";
    } else {
        echo "<ul>";
        while ($team = mysqli_fetch_assoc($result)) {
            echo "<li>" . htmlspecialchars($team['nev'], ENT_QUOTES, 'UTF-8') .
                 " - Bevétel: " . htmlspecialchars($team['bevetel'], ENT_QUOTES, 'UTF-8') .
                 " - Termelés: " . htmlspecialchars($team['termeles'], ENT_QUOTES, 'UTF-8') .
                 " - Kutatási pontok: " . htmlspecialchars($team['kutatasi_pontok'], ENT_QUOTES, 'UTF-8') .
                 "</li>";
        }
        echo "</ul>";
    }
    ?>
</section>

<?php
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


<!-- NEM MUKODIK MEG !!!! -->
    <div class="container">
        <div class="container-header">
            <h1><span><?php echo htmlspecialchars($team['nev'], ENT_QUOTES, 'UTF-8'); ?></span> részletes adatai</h1>
            <?php
                $onclick = "removePopup(" . json_encode($team['id'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                         . ", " . json_encode($team['nev'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ")";
            ?>
            <button onclick="<?php echo htmlspecialchars($onclick, ENT_QUOTES, 'UTF-8'); ?>">Csapat törlése</button>
        </div>
        <form class="init-form" action="/manage-game?uuid=<?php echo urlencode($team['id']); ?>" method="post">
            <input type="hidden" name="team_id" value="<?php echo htmlspecialchars($team['id'], ENT_QUOTES, 'UTF-8'); ?>">
            <div class="card-container">
                <div class="card team-info-card">
                    <h2>Csapat Infók</h2>
                    <div class="field-group">
                        <label>Csapatnév</label>
                        <div class="static-field"><?php echo htmlspecialchars($team['nev'], ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                    <div class="field-group">
                        <label for="allamforma">Államforma</label>
                        <select id="allamforma" name="allamforma">
                            <option value="demokratikus" <?php echo ($team['allamforma'] === 'demokratikus') ? 'selected' : ''; ?>>Demokratikus</option>
                            <option value="test" <?php echo ($team['allamforma'] === 'test') ? 'selected' : ''; ?>>test</option>
                        </select>
                    </div>
                    <div class="field-group">
                        <label for="kontinens">Kontinens</label>
                        <input type="text" id="kontinens" name="kontinens" value="<?php echo htmlspecialchars($team['kontinens'], ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                </div>
                <div class="card stats-card">
                    <h2>Erőforrások</h2>
                    <div class="field-row">
                        <div class="field-group">
                            <label for="bevetel">Bevétel</label>
                            <input type="number" id="bevetel" name="bevetel" value="<?php echo htmlspecialchars($team['bevetel'], ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="field-group">
                            <label for="termeles">Termelés</label>
                            <input type="number" id="termeles" name="termeles" value="<?php echo htmlspecialchars($team['termeles'], ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                    </div>
                    <div class="field-row">
                        <div class="field-group">
                            <label for="kutatasi_pontok">Kutatási pontok</label>
                            <input type="number" id="kutatasi_pontok" name="kutatasi_pontok" value="<?php echo htmlspecialchars($team['kutatasi_pontok'], ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="field-group">
                            <label for="diplomaciai_pontok">Diplomáciai pontok</label>
                            <input type="number" id="diplomaciai_pontok" name="diplomaciai_pontok" value="<?php echo htmlspecialchars($team['diplomaciai_pontok'], ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                    </div>
                    <div class="field-row">
                        <div class="field-group">
                            <label for="katonai_pontok">Katonai pontok</label>
                            <input type="number" id="katonai_pontok" name="katonai_pontok" value="<?php echo htmlspecialchars($team['katonai_pontok'], ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                    </div>
                </div>
                <div class="card institutions-card">
                    <h2>Épületek</h2>
                    <div class="field-row">
                        <div class="field-group">
                            <label for="bankok">Bankok</label>
                            <input type="number" id="bankok" name="bankok" value="<?php echo htmlspecialchars($team['bankok'], ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="field-group">
                            <label for="gyarak">Gyárak</label>
                            <input type="number" id="gyarak" name="gyarak" value="<?php echo htmlspecialchars($team['gyarak'], ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                    </div>
                    <div class="field-row">
                        <div class="field-group">
                            <label for="egyetemek">Egyetemek</label>
                            <input type="number" id="egyetemek" name="egyetemek" value="<?php echo htmlspecialchars($team['egyetemek'], ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="field-group">
                            <label for="laktanyak">Laktanyak</label>
                            <input type="number" id="laktanyak" name="laktanyak" value="<?php echo htmlspecialchars($team['laktanyak'], ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                    </div>
                    <hr style="border:none; border-top:1px solid black;">
                    <div class="field-group">
                        <label for="politikak-select">Politikák</label>
                        <div class="chips-container" id="chips-container"></div>
                        <select id="politikak-select">
                            <option value="" disabled selected>Válassz politikát</option>
                            <option value="option1">Option 1</option>
                            <option value="option2">Option 2</option>
                            <option value="option3">Option 3</option>
                            <option value="option4">Option 4</option>
                        </select>
                        <input type="hidden" name="politikak" id="politikak-hidden" value="<?php echo htmlspecialchars($team['politikak'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                </div>
            </div>
            <input type="submit" class="submit-btn" name="kezdo_megerosites" value="Kezdő adatok megerősítése">
        </form>
    </div>
<?php endif; ?>

<script src="/public/static/js/manage.js"></script>
<?php include(__DIR__ . '/../components/footer.html'); ?>
