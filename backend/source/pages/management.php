<?php
include(__DIR__ . '/../components/head.php');
include_once(__DIR__ . '/../logic/game_backend.php');

// Ensure the game is active.
if (get_game_phase($connection) !== 'active') {
    header("Location: /init");
    exit;
}

// ===========================
// Process Recurring Custom Rule Removal
// ===========================
if (isset($_POST['remove_rule'])) {
    $rule_id = trim($_POST['rule_id'] ?? '');
    if (!empty($rule_id)) {
        $stmt = mysqli_prepare($connection, "DELETE FROM custom_rules WHERE id = ?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $rule_id);
            if (mysqli_stmt_execute($stmt)) {
                $success_custom = "Szabály törölve.";
            } else {
                $error_custom = "Hiba a szabály törlésekor: " . mysqli_stmt_error($stmt);
            }
            mysqli_stmt_close($stmt);
        } else {
            $error_custom = "Hiba a törlési lekérdezés előkészítésénél: " . mysqli_error($connection);
        }
    }
}

// ===========================
// Process Recurring Custom Rule Submission
// ===========================
if (isset($_POST['save_recurring_rule'])) {
    $rule_team = trim($_POST['rule_team'] ?? '');
    $rule_field = trim($_POST['rule_field'] ?? '');
    $rule_amount = (int)($_POST['rule_amount'] ?? 0);

    if (empty($rule_team) || empty($rule_field)) {
        $error_custom = "Minden mezőt ki kell tölteni a szabály létrehozásához!";
    } else {
        $stmt = mysqli_prepare($connection, "INSERT INTO custom_rules (team_id, field, amount) VALUES (?, ?, ?)");
        if (!$stmt) {
            $error_custom = "Hiba a lekérdezés előkészítésénél: " . mysqli_error($connection);
        } else {
            mysqli_stmt_bind_param($stmt, "ssi", $rule_team, $rule_field, $rule_amount);
            if (mysqli_stmt_execute($stmt)) {
                $success_custom = "Recurring rule saved.";
            } else {
                $error_custom = "Hiba a szabály mentésekor: " . mysqli_stmt_error($stmt);
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// ===========================
// Process Team Update
// ===========================
// update team
if (isset($_POST['kezdo_megerosites']) && $_POST['kezdo_megerosites'] !== "") {
    $team_id = trim($_POST['team_id'] ?? '');
    if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $team_id)) {
        $error = "Érvénytelen csapat azonosító.";
    } else {
        $allamforma = trim($_POST['allamforma'] ?? '');
        $kontinens   = trim($_POST['kontinens'] ?? '');
        $bevetel     = (int) ($_POST['bevetel'] ?? 0);
        $termeles    = (int) ($_POST['termeles'] ?? 0);
        $kutatasi_pontok    = (int) ($_POST['kutatasi_pontok'] ?? 0);
        $diplomaciai_pontok = (int) ($_POST['diplomaciai_pontok'] ?? 0);
        $katonai_pontok     = (int) ($_POST['katonai_pontok'] ?? 0);
        $bankok      = (int) ($_POST['bankok'] ?? 0);
        $gyarak      = (int) ($_POST['gyarak'] ?? 0);
        $egyetemek   = (int) ($_POST['egyetemek'] ?? 0);
        $laktanyak   = (int) ($_POST['laktanyak'] ?? 0);
        $politikak   = trim($_POST['politikak'] ?? ''); // JSON string from chips

        if ($allamforma === '' || $kontinens === '') {
            $error = "Hiányzó adatok!";
        } elseif (
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
            // Updated valid államforma values
            $valid_allamforma = ['törzsi', 'arisztokratikus', 'türannisz', 'kalmár', 'modern', 'kommunista'];
            if (!in_array($allamforma, $valid_allamforma)) {
                $error = "Érvénytelen államforma.";
            } else {
                // Politics validation block
                $allowed_politics = [];
                switch ($allamforma) {
                    case 'törzsi':
                        $allowed_politics = ['totemizmus', 'zikkurat', 'nomad', 'torzsi_szovetseg'];
                        break;
                    case 'arisztokratikus':
                        $allowed_politics = ['monoteizmus', 'politeizmus', 'xii_tabla', 'pantheon', 'nepgyules', 'legio'];
                        break;
                    case 'türannisz':
                        $allowed_politics = ['monoteizmus', 'politeizmus', 'akropolisz', 'strategosz', 'deloszi_szovetseg', 'ezustbany'];
                        break;
                    case 'kalmár':
                        $allowed_politics = ['karavella', 'monopolium', 'keresztes', 'obszervatori', 'inkvizicio', 'gyarmatositas'];
                        break;
                    case 'modern':
                        $allowed_politics = ['kapitalizmus', 'vilagbank', 'erasmus', 'nemzeti_hadsereg', 'new_deal', 'schengeni', 'emberi_jogok', 'nato'];
                        break;
                    case 'kommunista':
                        $allowed_politics = ['munkaverseny', 'kgst', 'varsoi', 'komintern', 'gulag', 'allamrendor', 'atomfegyver', 'propaganda'];
                        break;
                }
                // Decode the JSON from the hidden input
                $decoded_politikak = json_decode($politikak, true);
                if (!is_array($decoded_politikak)) {
                    $error = "Érvénytelen politikák formátum.";
                } else {
                    foreach ($decoded_politikak as $item) {
                        // Expect each chip to be an object with a "value" property
                        if (!isset($item['value']) || !in_array($item['value'], $allowed_politics)) {
                            $error = "Érvénytelen politika lett kiválasztva a megadott államforma számára.";
                            break;
                        }
                    }
                }
                if (!isset($error)) {
                    // End Politics validation block

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
                            header("Location: /management?uuid=" . urlencode($team_id));
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
}


// ===========================
// Retrieve All Teams (by ID and Name)
// ===========================
$teams = [];
$team_names = [];
$query = "SELECT id, nev, diplomaciai_pontok FROM csapatok ORDER BY letrehozva";
$result = mysqli_query($connection, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $teams[] = $row;
        $team_names[$row['id']] = $row['nev'];
    }
    mysqli_free_result($result);
} else {
    $error = "Hiba a csapatok lekérdezésében: " . mysqli_error($connection);
}

// ===========================
// Retrieve Team Data if Selected via "uuid"
// ===========================
if (isset($_GET['uuid']) && !empty($_GET['uuid'])) {
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
<link rel="stylesheet" href="/public/static/css/pages/manage.css">

<!-- Navigation: List of Teams -->
<nav class="teams-nav">
    <ul>
        <?php
        foreach ($teams as $nav_team) {
            $team_id_html = htmlspecialchars($nav_team['id'], ENT_QUOTES, 'UTF-8');
            $team_name_html = htmlspecialchars($nav_team['nev'], ENT_QUOTES, 'UTF-8');
            $active_class = (isset($team) && $team['id'] === $nav_team['id']) ? 'active' : '';
            echo "<li><a href='/management?uuid=" . urlencode($team_id_html) . "' class='{$active_class}'>{$team_name_html}</a></li>";
        }
        ?>
    </ul>
</nav>

<?php if (isset($error)) : ?>
    <div class="toast error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>
<?php if (isset($success)) : ?>
    <div class="toast success"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>

<!-- Team Details Section -->
<?php if (isset($team)) : ?>
    <div class="container">
        <div class="container-header">
            <h1><?php echo htmlspecialchars($team['nev'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h1>
        </div>
        <form class="init-form" action="/management?uuid=<?php echo urlencode($team['id'] ?? ''); ?>" method="post">
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
                            <option value="törzsi" <?php echo (isset($team['allamforma']) && $team['allamforma'] === 'törzsi') ? 'selected' : ''; ?>>Törzsi falu</option>
                            <option value="arisztokratikus" <?php echo (isset($team['allamforma']) && $team['allamforma'] === 'arisztokratikus') ? 'selected' : ''; ?>>Arisztokratikus köztársaság</option>
                            <option value="türannisz" <?php echo (isset($team['allamforma']) && $team['allamforma'] === 'türannisz') ? 'selected' : ''; ?>>Türannisz</option>
                            <option value="kalmár" <?php echo (isset($team['allamforma']) && $team['allamforma'] === 'kalmár') ? 'selected' : ''; ?>>Kalmár köztársaság</option>
                            <option value="modern" <?php echo (isset($team['allamforma']) && $team['allamforma'] === 'modern') ? 'selected' : ''; ?>>Modern demokrácia</option>
                            <option value="kommunista" <?php echo (isset($team['allamforma']) && $team['allamforma'] === 'kommunista') ? 'selected' : ''; ?>>Kommunista diktatúra</option>
                        </select>
                    </div>
                    <div class="field-group">
                        <label for="kontinens">Kontinens</label>
                        <input type="text" id="kontinens" name="kontinens" value="<?php echo htmlspecialchars($team['kontinens'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="field-group">
                        <label for="politikak-select">Politikák</label>
                        <div class="chips-container" id="chips-container"></div>
                        <select id="politikak-select" name="politikak">
                            <option value="" disabled selected>Válassz politikát</option>
                            <?php
                            if (isset($team['allamforma'])) {
                                switch ($team['allamforma']) {
                                    case 'törzsi':
                                        echo '<option value="totemizmus">Totemizmus</option>';
                                        echo '<option value="zikkurat">Zikkurat</option>';
                                        echo '<option value="nomad">Nomád életmód</option>';
                                        echo '<option value="torzsi_szovetseg">Törzsi szövetség</option>';
                                        break;
                                    case 'arisztokratikus':
                                        echo '<option value="monoteizmus">Monoteizmus</option>';
                                        echo '<option value="politeizmus">Politeizmus</option>';
                                        echo '<option value="xii_tabla">XII. táblás törvények</option>';
                                        echo '<option value="pantheon">Pantheon</option>';
                                        echo '<option value="nepgyules">Népgyűlés</option>';
                                        echo '<option value="legio">Légió</option>';
                                        break;
                                    case 'türannisz':
                                        echo '<option value="monoteizmus">Monoteizmus</option>';
                                        echo '<option value="politeizmus">Politeizmus</option>';
                                        echo '<option value="akropolisz">Akropolisz</option>';
                                        echo '<option value="strategosz">Sztratégosz</option>';
                                        echo '<option value="deloszi_szovetseg">Déloszi szövetség</option>';
                                        echo '<option value="ezustbany">Ezüstbányák feltárása</option>';
                                        break;
                                    case 'kalmár':
                                        echo '<option value="karavella">Karavella</option>';
                                        echo '<option value="monopolium">Monopólium</option>';
                                        echo '<option value="keresztes">Keresztes hadjárat</option>';
                                        echo '<option value="obszervatori">Obszervatórium</option>';
                                        echo '<option value="inkvizicio">Inkvizíció</option>';
                                        echo '<option value="gyarmatositas">Gyarmatosítás</option>';
                                        break;
                                    case 'modern':
                                        echo '<option value="kapitalizmus">Kapitalizmus</option>';
                                        echo '<option value="vilagbank">Világbank</option>';
                                        echo '<option value="erasmus">Erasmus-projekt</option>';
                                        echo '<option value="nemzeti_hadsereg">Nemzeti hadsereg</option>';
                                        echo '<option value="new_deal">New deal</option>';
                                        echo '<option value="schengeni">Schengeni egyezmény</option>';
                                        echo '<option value="emberi_jogok">Emberi és polgári jogok</option>';
                                        echo '<option value="nato">NATO</option>';
                                        break;
                                    case 'kommunista':
                                        echo '<option value="munkaverseny">Munkaverseny</option>';
                                        echo '<option value="kgst">KGST</option>';
                                        echo '<option value="varsoi">Varsói szerződés</option>';
                                        echo '<option value="komintern">Komintern</option>';
                                        echo '<option value="gulag">GULÁG</option>';
                                        echo '<option value="allamrendor">Államrendőrség</option>';
                                        echo '<option value="atomfegyver">Atomfegyverek</option>';
                                        echo '<option value="propaganda">Propaganda sajtó</option>';
                                        break;
                                    default:
                                        echo '<option value="">Nincs elérhető politika</option>';
                                }
                            }
                            ?>
                        </select>
                        <!-- Retain hidden input for JS work -->
                        <input type="hidden" name="politikak" id="politikak-hidden" value="<?php echo htmlspecialchars($team['politikak'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
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
                    <div class="field-group">
                        <label for="kutatasi_pontok">Kutatási pontok</label>
                        <input type="number" id="kutatasi_pontok" name="kutatasi_pontok" value="<?php echo htmlspecialchars($team['kutatasi_pontok'] ?? 0, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="field-group">
                        <label for="diplomaciai_pontok">Diplomáciai pontok</label>
                        <input type="number" id="diplomaciai_pontok" name="diplomaciai_pontok" value="<?php echo htmlspecialchars($team['diplomaciai_pontok'] ?? 0, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="field-group">
                        <label for="katonai_pontok">Katonai pontok</label>
                        <input type="number" id="katonai_pontok" name="katonai_pontok" value="<?php echo htmlspecialchars($team['katonai_pontok'] ?? 0, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                </div>
                <div class="card institutions-card">
                    <h2>Épületek</h2>
                    <div class="field-group">
                        <label for="bankok">Bankok</label>
                        <input type="number" id="bankok" name="bankok" value="<?php echo htmlspecialchars($team['bankok'] ?? 0, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="field-group">
                        <label for="gyarak">Gyárak</label>
                        <input type="number" id="gyarak" name="gyarak" value="<?php echo htmlspecialchars($team['gyarak'] ?? 0, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="field-group">
                        <label for="egyetemek">Egyetemek</label>
                        <input type="number" id="egyetemek" name="egyetemek" value="<?php echo htmlspecialchars($team['egyetemek'] ?? 0, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="field-group">
                        <label for="laktanyak">Laktanyak</label>
                        <input type="number" id="laktanyak" name="laktanyak" value="<?php echo htmlspecialchars($team['laktanyak'] ?? 0, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                </div>
            </div>
            <input type="submit" class="submit-btn" name="kezdo_megerosites" value="Kezdő adatok megerősítése">
        </form>
    </div>
<?php else: ?>
    <p class="notfound">Válassz ki csapatot</p>
<?php endif; ?>

<!-- Recurring Custom Rules Section -->
<div class="custom-rules">
    <h2>Recurring Custom Rules</h2>
    <?php if (isset($error_custom)): ?>
        <div class="toast error"><?php echo htmlspecialchars($error_custom, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if (isset($success_custom)): ?>
        <div class="toast success"><?php echo htmlspecialchars($success_custom, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <form action="/management" method="post">
        <label for="rule_team">Csapat:</label>
        <select id="rule_team" name="rule_team" required>
            <option value="">-- Válassz --</option>
            <?php foreach ($teams as $t): ?>
                <option value="<?php echo htmlspecialchars($t['id'], ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo htmlspecialchars($t['nev'], ENT_QUOTES, 'UTF-8'); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <br>
        <label for="rule_field">Mező:</label>
        <select id="rule_field" name="rule_field" required>
            <option value="">-- Válassz --</option>
            <option value="bevetel">Bevétel</option>
            <option value="termeles">Termelés</option>
            <option value="kutatasi_pontok">Kutatási pontok</option>
            <option value="diplomaciai_pontok">Diplomáciai pontok</option>
            <option value="katonai_pontok">Katonai pontok</option>
            <option value="bankok">Bankok</option>
            <option value="gyarak">Gyárak</option>
            <option value="egyetemek">Egyetemek</option>
            <option value="laktanyak">Laktanyak</option>
        </select>
        <br>
        <label for="rule_amount">Összeg (per round):</label>
        <input type="number" id="rule_amount" name="rule_amount" value="0" required>
        <br>
        <input type="submit" name="save_recurring_rule" value="Szabály mentése">
    </form>
    
    <!-- List existing recurring custom rules without creation date and with a remove button -->
    <h3>Meglévő Recurring Rules</h3>
    <?php
    $custom_rules = [];
    $query = "SELECT id, team_id, field, amount FROM custom_rules ORDER BY id DESC";
    $result = mysqli_query($connection, $query);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $custom_rules[] = $row;
        }
        mysqli_free_result($result);
    }
    if (!empty($custom_rules)):
    ?>
        <table>
            <thead>
                <tr>
                    <th>Csapat</th>
                    <th>Mező</th>
                    <th>Összeg (per round)</th>
                    <th>Művelet</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($custom_rules as $rule): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($team_names[$rule['team_id']] ?? $rule['team_id'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($rule['field'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($rule['amount'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>
                            <form action="/management" method="post" onsubmit="return confirm('Biztosan törlöd ezt a szabályt?');" style="display:inline;">
                                <input type="hidden" name="remove_rule" value="1">
                                <input type="hidden" name="rule_id" value="<?php echo htmlspecialchars($rule['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                <button type="submit">Törlés</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>Nincs megadva recurring szabály.</p>
    <?php endif; ?>
</div>

<script src="/public/static/js/manage.js"></script>
<?php include(__DIR__ . '/../components/footer.html'); ?>
