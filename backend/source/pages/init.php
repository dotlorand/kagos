<?php
include(__DIR__ . '/../components/head.php');
include_once(__DIR__ . '/../logic/game_backend.php');

if (get_game_phase($connection) === 'active') {
    header("Location: /manage-game");
    exit;
}

// add team
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['add_team'])) {
    $nev        = trim($_POST['nev'] ?? '');
    $allamforma = trim($_POST['allamforma'] ?? '');
    $kontinens  = trim($_POST['kontinens'] ?? '');
    $valid_allamforma = ['demokratikus', 'test'];
    if ($nev === '' || $allamforma === '' || $kontinens === '') {
        $error = "Minden mezőt ki kell tölteni!";
    } elseif (!in_array($allamforma, $valid_allamforma)) {
        $error = "Érvénytelen államforma.";
    } else {
        $stmt = mysqli_prepare($connection, "INSERT INTO csapatok (nev, allamforma, kontinens) VALUES (?, ?, ?)");
        if (!$stmt) {
            $error = "Hiba a lekérdezéssel: " . mysqli_error($connection);
        } else {
            mysqli_stmt_bind_param($stmt, "sss", $nev, $allamforma, $kontinens);
            if (mysqli_stmt_execute($stmt)) {
                $success = "Csapat hozzáadva!";
            } else {
                $error = "Hiba: " . mysqli_stmt_error($stmt);
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// del team
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['remove_team'])) {
    $team_id = trim($_POST['team_id'] ?? '');
    if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $team_id)) {
        $error = "Érvénytelen csapat azonosító.";
    } else {
        $stmt = mysqli_prepare($connection, "DELETE FROM csapatok WHERE id = ?");
        if (!$stmt) {
            $error = "Hiba a törlési lekérdezés előkészítésénél: " . mysqli_error($connection);
        } else {
            mysqli_stmt_bind_param($stmt, "s", $team_id);
            if (mysqli_stmt_execute($stmt)) {
                $success = "Csapat törölve!";
            } else {
                $error = "Hiba a csapat törlésekor: " . mysqli_stmt_error($stmt);
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// update team
if (isset($_POST['kezdo_megerosites']) && $_POST['kezdo_megerosites'] !== "") {
    $team_id = trim($_POST['team_id'] ?? '');
    if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $team_id)) {
        $error = "Érvénytelen csapat azonosító.";
    } else {
        $allamforma = trim($_POST['allamforma'] ?? '');
        $kontinens = trim($_POST['kontinens'] ?? '');
        $bevetel = (int) ($_POST['bevetel'] ?? 0);
        $termeles = (int) ($_POST['termeles'] ?? 0);
        $kutatasi_pontok = (int) ($_POST['kutatasi_pontok'] ?? 0);
        $diplomaciai_pontok = (int) ($_POST['diplomaciai_pontok'] ?? 0);
        $katonai_pontok = (int) ($_POST['katonai_pontok'] ?? 0);
        $bankok = (int) ($_POST['bankok'] ?? 0);
        $gyarak = (int) ($_POST['gyarak'] ?? 0);
        $egyetemek = (int) ($_POST['egyetemek'] ?? 0);
        $laktanyak = (int) ($_POST['laktanyak'] ?? 0);
        $politikak = trim($_POST['politikak'] ?? '');
        if ($allamforma === '' || $kontinens === '') {
            $error = "Hiányzó adatok!";
        } elseif (json_decode($politikak) === null && $politikak !== "") {
            $error = "Érvénytelen JSON formátum a politikákhoz.";
        } else if (
            $bevetel > 99999 ||
            $termeles > 99999 ||
            $kutatasi_pontok > 99999 ||
            $diplomaciai_pontok > 99999 ||
            $katonai_pontok > 99999 ||
            $bankok > 99999 ||
            $gyarak > 99999 ||
            $egyetemek > 99999 ||
            $laktanyak > 99999
        ) {
            $error = "Egy vagy több érték túl magas (Max: 99999)";
        } else {
            $valid_allamforma = ['demokratikus', 'test'];
            if (!in_array($allamforma, $valid_allamforma)) {
                $error = "Érvénytelen államforma.";
            } else {
                $stmt = mysqli_prepare($connection, "UPDATE csapatok SET allamforma = ?, kontinens = ?, bevetel = ?, termeles = ?, kutatasi_pontok = ?, diplomaciai_pontok = ?, katonai_pontok = ?, bankok = ?, gyarak = ?, egyetemek = ?, laktanyak = ?, politikak = ? WHERE id = ?");
                if (!$stmt) {
                    $error = "Hiba a lekérdezés előkészítésénél: " . mysqli_error($connection);
                } else {
                    mysqli_stmt_bind_param(
                        $stmt,
                        "ssiiiiiiiiiss",
                        $allamforma,
                        $kontinens,
                        $bevetel,
                        $termeles,
                        $kutatasi_pontok,
                        $diplomaciai_pontok,
                        $katonai_pontok,
                        $bankok,
                        $gyarak,
                        $egyetemek,
                        $laktanyak,
                        $politikak,
                        $team_id
                    );
                    if (mysqli_stmt_execute($stmt)) {
                        header("Location: /init?uuid=" . urlencode($team_id));
                        exit;
                    } else {
                        $error = "Hiba az adatok frissítésekor: " . mysqli_stmt_error($stmt);
                    }
                    mysqli_stmt_close($stmt);
                }
            }
        }
    }
}

// start game
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['start_game'])) {
    $query = "SELECT COUNT(*) AS team_count FROM csapatok";
    $result = mysqli_query($connection, $query);
    $row = mysqli_fetch_assoc($result);
    if ((int)$row['team_count'] === 0) {
        $error = "Nincs csapat hozzáadva!";
    } else {
        if (start_game_now($connection)) {
            header("Location: /manage-game");
            exit;
        } else {
            $error = "Hiba a játék elindításakor!";
        }
    }
}

// get team data
if (isset($_GET['uuid']) && $_GET['uuid'] !== '') {
    $uuid = trim($_GET['uuid']);
    if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid)) {
        $error = "Érvénytelen azonosító formátum.";
    } else {
        $stmt = mysqli_prepare($connection, "SELECT * FROM csapatok WHERE id = ?");
        if (!$stmt) {
            $error = "Hiba a lekérdezés előkészítésénél: " . htmlspecialchars(mysqli_error($connection), ENT_QUOTES, 'UTF-8');
        } else {
            mysqli_stmt_bind_param($stmt, "s", $uuid);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            if ($result && mysqli_num_rows($result) === 1) {
                $team = mysqli_fetch_assoc($result);
            } else {
                $error = "A csapat nem létezik.";
            }
            mysqli_stmt_close($stmt);
        }
    }
}
?>

<style>
    .popup-container > div { display: none; }
</style>
<link rel="stylesheet" href="/public/static/css/pages/manage.css">

<nav class="teams-nav">
    <ul>
        <?php
        $query = "SELECT id, nev FROM csapatok ORDER BY letrehozva";
        $result = mysqli_query($connection, $query);
        if (!$result) {
            error_log("db query failed: " . mysqli_error($connection));
            $error = "Hiba a csapatok lekérdezésével.";
            exit;
        }
        while ($nav_team = mysqli_fetch_assoc($result)) {
            $team_id_html = htmlspecialchars($nav_team['id'], ENT_QUOTES, 'UTF-8');
            $team_name_html = htmlspecialchars($nav_team['nev'], ENT_QUOTES, 'UTF-8');
            $active_class = (isset($team) && $team['id'] === $nav_team['id']) ? 'active' : '';
            echo "<li><a href='/init?uuid=" . urlencode($team_id_html) . "' class='{$active_class}'>{$team_name_html}</a></li>";
        }
        mysqli_free_result($result);
        ?>
        <button onclick="popup('add-team')"><img class="icon" src="/public/static/icons/plus.svg">Új csapat</button>
    </ul>
    <form action="" method="post">
        <input type="hidden" name="start_game" value="1">
        <button type="submit"><img class="icon" src="/public/static/icons/play.svg">Játék indítása</button>
    </form>
</nav>

<?php if (isset($error)) : ?>
    <div class="toast error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>
<?php if (isset($success)) : ?>
    <div class="toast success"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>

<div id="popup-container">
    <div class="popup-bg" onclick="closePopup()"></div>
    <div class="popup-block" id="add-team">
        <div class="popup-header">
            <h2>Csapat hozzáadása</h2>
            <button onclick="closePopup()"><img class="icon" src="/public/static/icons/close.svg" alt="Mégse" title="Mégse"></button>
        </div>
        <form action="/init" method="post">
            <input required type="text" name="nev" autocomplete="off" placeholder="Csapat név" maxlength="30">
            <select required name="allamforma">
                <option value="" disabled selected>Válassz államformát</option>
                <option value="demokratikus">Demokratikus</option>
                <option value="test">test</option>
            </select>
            <input required type="text" name="kontinens" autocomplete="off" placeholder="Kontinens" maxlength="30">
            <input type="submit" name="add_team" value="Hozzáadás">
        </form>
    </div>
    <div class="popup-block" id="remove-team">
        <div class="popup-header">
            <h2>Csapat törlése</h2>
            <button onclick="closePopup()"><img class="icon" src="/public/static/icons/close.svg" alt="Mégse" title="Mégse"></button>
        </div>
        <form action="/init" method="post">
            <p>Biztosan törlöd a <strong id="team-name-confirm"></strong> csapatot?</p>
            <input type="hidden" name="team_id" value="">
            <input type="submit" name="remove_team" value="Törlés">
        </form>
    </div>
</div>

<?php if (isset($team)) : ?>
    <div class="container">
        <div class="container-header">
            <h1><span><?php echo htmlspecialchars($team['nev'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span> kezdő tőkéje</h1>
            <?php
                $onclick = "removePopup(" . json_encode($team['id'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                         . ", " . json_encode($team['nev'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ")";
            ?>
            <button onclick="<?php echo htmlspecialchars($onclick, ENT_QUOTES, 'UTF-8'); ?>">Csapat törlése</button>
        </div>
        <form class="init-form" action="/init?uuid=<?php echo urlencode($team['id'] ?? ''); ?>" method="post">
            <input type="hidden" name="team_id" value="<?php echo htmlspecialchars($team['id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            <div class="card-container">
                <div class="card team-info-card">
                    <h2>Csapat Infók</h2>
                    <div class="field-group">
                        <label>Csapatnév</label>
                        <div class="static-field"><?php echo htmlspecialchars($team['nev'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                    <div class="field-group">
                        <label for="allamforma">Államforma</label>
                        <select id="allamforma" name="allamforma">
                            <option value="demokratikus" <?php echo (isset($team['allamforma']) && $team['allamforma'] === 'demokratikus') ? 'selected' : ''; ?>>Demokratikus</option>
                            <option value="test" <?php echo (isset($team['allamforma']) && $team['allamforma'] === 'test') ? 'selected' : ''; ?>>test</option>
                        </select>
                    </div>
                    <div class="field-group">
                        <label for="kontinens">Kontinens</label>
                        <input type="text" id="kontinens" name="kontinens" value="<?php echo htmlspecialchars($team['kontinens'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                </div>
                <div class="card stats-card">
                    <h2>Erőforrások</h2>
                    <div class="field-row">
                        <div class="field-group">
                            <label for="bevetel">Bevétel</label>
                            <input type="number" id="bevetel" name="bevetel" value="<?php echo htmlspecialchars($team['bevetel'] ?? 0, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="field-group">
                            <label for="termeles">Termelés</label>
                            <input type="number" id="termeles" name="termeles" value="<?php echo htmlspecialchars($team['termeles'] ?? 0, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                    </div>
                    <div class="field-row">
                        <div class="field-group">
                            <label for="kutatasi_pontok">Kutatási pontok</label>
                            <input type="number" id="kutatasi_pontok" name="kutatasi_pontok" value="<?php echo htmlspecialchars($team['kutatasi_pontok'] ?? 0, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="field-group">
                            <label for="diplomaciai_pontok">Diplomáciai pontok</label>
                            <input type="number" id="diplomaciai_pontok" name="diplomaciai_pontok" value="<?php echo htmlspecialchars($team['diplomaciai_pontok'] ?? 0, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                    </div>
                    <div class="field-row">
                        <div class="field-group">
                            <label for="katonai_pontok">Katonai pontok</label>
                            <input type="number" id="katonai_pontok" name="katonai_pontok" value="<?php echo htmlspecialchars($team['katonai_pontok'] ?? 0, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                    </div>
                </div>
                <div class="card institutions-card">
                    <h2>Épületek</h2>
                    <div class="field-row">
                        <div class="field-group">
                            <label for="bankok">Bankok</label>
                            <input type="number" id="bankok" name="bankok" value="<?php echo htmlspecialchars($team['bankok'] ?? 0, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="field-group">
                            <label for="gyarak">Gyárak</label>
                            <input type="number" id="gyarak" name="gyarak" value="<?php echo htmlspecialchars($team['gyarak'] ?? 0, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                    </div>
                    <div class="field-row">
                        <div class="field-group">
                            <label for="egyetemek">Egyetemek</label>
                            <input type="number" id="egyetemek" name="egyetemek" value="<?php echo htmlspecialchars($team['egyetemek'] ?? 0, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="field-group">
                            <label for="laktanyak">Laktanyak</label>
                            <input type="number" id="laktanyak" name="laktanyak" value="<?php echo htmlspecialchars($team['laktanyak'] ?? 0, ENT_QUOTES, 'UTF-8'); ?>">
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
<?php else: ?>
    <p class="notfound">Válassz ki csapatot</p>
<?php endif; ?>

<script src="/public/static/js/manage.js"></script>
<?php include(__DIR__ . '/../components/footer.html'); ?>
