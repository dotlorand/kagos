<?php
include(__DIR__ . '/../components/head.php');
include_once(__DIR__ . '/../logic/game_backend.php');

if (get_game_phase($connection) !== 'active') {
    header("Location: /init". "?access_key=" . htmlspecialchars($_GET['access_key'] ?? '', ENT_QUOTES, 'UTF-8'));
    exit;
}

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
        $politikak   = trim($_POST['politikak'] ?? '');

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
            $valid_allamforma = ['törzsi', 'arisztokratikus', 'türannisz', 'kalmár', 'modern', 'kommunista'];
            if (!in_array($allamforma, $valid_allamforma)) {
                $error = "Érvénytelen államforma.";
            } else {
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
                $decoded_politikak = json_decode($politikak, true);
                if (!is_array($decoded_politikak)) {
                    $error = "Érvénytelen politikák formátum.";
                } else {
                    foreach ($decoded_politikak as $item) {
                        if (!isset($item['value']) || !in_array($item['value'], $allowed_politics)) {
                            $error = "Érvénytelen politika lett kiválasztva a megadott államforma számára.";
                            break;
                        }
                    }
                }
                if (!isset($error)) {
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
                            header("Location: /management?uuid=" . urlencode($team_id). "&access_key=" . htmlspecialchars($_GET['access_key'] ?? '', ENT_QUOTES, 'UTF-8'));
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


if (!isset($_GET['uuid']) && count($teams) > 0) {
    $first_team_id = $teams[0]['id'];
    header("Location: /management?uuid=" . urlencode($first_team_id). "&access_key=" . htmlspecialchars($_GET['access_key'] ?? '', ENT_QUOTES, 'UTF-8'));
    exit;
}

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

<nav class="navbar navbar-expand-lg mb-3">
  <div class="container-fluid">
    <a href="/round?access_key=<?php echo htmlspecialchars($_GET['access_key'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-secondary m-3">Vissza a játék menedzsmenthez</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#teamsNavbar" aria-controls="teamsNavbar" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="teamsNavbar">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0 d-flex flex-wrap">
        <?php
        foreach ($teams as $nav_team) {
            $team_id_html = htmlspecialchars($nav_team['id'], ENT_QUOTES, 'UTF-8');
            $team_name_html = htmlspecialchars($nav_team['nev'], ENT_QUOTES, 'UTF-8');
            $active_class = (isset($team) && $team['id'] === $nav_team['id']) ? 'btn-primary' : 'btn-outline-primary';
            echo "<li class='nav-item me-2 mb-2'><a href='/management?uuid=" . urlencode($team_id_html) . "&access_key=" . htmlspecialchars($_GET['access_key'] ?? '', ENT_QUOTES, 'UTF-8') . "' class='btn {$active_class}'>{$team_name_html}</a></li>";
        }
        ?>
      </ul>
    </div>
  </div>
</nav>

<div class="container mt-3">
    <?php if (isset($error)) : ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($success)) : ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($error_custom)) : ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($error_custom, ENT_QUOTES, 'UTF-8'); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($success_custom)) : ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($success_custom, ENT_QUOTES, 'UTF-8'); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
</div>

<?php if (isset($team)) : ?>
<div class="container">
    <div class="mb-3">
        <h1><?php echo htmlspecialchars($team['nev'], ENT_QUOTES, 'UTF-8'); ?></h1>
    </div>
    <form class="init-form" action="/management?uuid=<?php echo urlencode($team['id']); ?>&access_key=<?php echo htmlspecialchars($_GET['access_key'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" method="post">
        <input type="hidden" name="team_id" value="<?php echo htmlspecialchars($team['id'], ENT_QUOTES, 'UTF-8'); ?>">
        <div class="row">
            <div class="col-md-4 mb-3">
                <div class="card">
                    <div class="card-header">Csapat Infók</div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Csapatnév</label>
                            <p class="form-control-plaintext"><?php echo htmlspecialchars($team['nev'], ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                        <div class="mb-3">
                            <label for="allamforma" class="form-label">Államforma</label>
                            <select id="allamforma" name="allamforma" class="form-select">
                                <option value="törzsi" <?php echo (isset($team['allamforma']) && $team['allamforma'] === 'törzsi') ? 'selected' : ''; ?>>Törzsi falu</option>
                                <option value="arisztokratikus" <?php echo (isset($team['allamforma']) && $team['allamforma'] === 'arisztokratikus') ? 'selected' : ''; ?>>Arisztokratikus köztársaság</option>
                                <option value="türannisz" <?php echo (isset($team['allamforma']) && $team['allamforma'] === 'türannisz') ? 'selected' : ''; ?>>Türannisz</option>
                                <option value="kalmár" <?php echo (isset($team['allamforma']) && $team['allamforma'] === 'kalmár') ? 'selected' : ''; ?>>Kalmár köztársaság</option>
                                <option value="modern" <?php echo (isset($team['allamforma']) && $team['allamforma'] === 'modern') ? 'selected' : ''; ?>>Modern demokrácia</option>
                                <option value="kommunista" <?php echo (isset($team['allamforma']) && $team['allamforma'] === 'kommunista') ? 'selected' : ''; ?>>Kommunista diktatúra</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="kontinens" class="form-label">Kontinens</label>
                            <input type="text" id="kontinens" name="kontinens" class="form-control" value="<?php echo htmlspecialchars($team['kontinens'], ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="mb-3">
                            <label for="politikak-select" class="form-label">Politikák</label>
                            <div id="chips-container" class="mb-2"></div>
                            <select id="politikak-select" name="politikak" class="form-select">
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
                            <input type="hidden" name="politikak" id="politikak-hidden" value="<?php echo htmlspecialchars($team['politikak'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card">
                    <div class="card-header">Erőforrások</div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col">
                                <label for="bevetel" class="form-label">Bevétel</label>
                                <input type="number" class="form-control" id="bevetel" name="bevetel" value="<?php echo htmlspecialchars($team['bevetel'] ?? 0, ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="col">
                                <label for="termeles" class="form-label">Termelés</label>
                                <input type="number" class="form-control" id="termeles" name="termeles" value="<?php echo htmlspecialchars($team['termeles'] ?? 0, ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="kutatasi_pontok" class="form-label">Kutatási pontok</label>
                            <input type="number" class="form-control" id="kutatasi_pontok" name="kutatasi_pontok" value="<?php echo htmlspecialchars($team['kutatasi_pontok'] ?? 0, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="mb-3">
                            <label for="diplomaciai_pontok" class="form-label">Diplomáciai pontok</label>
                            <input type="number" class="form-control" id="diplomaciai_pontok" name="diplomaciai_pontok" value="<?php echo htmlspecialchars($team['diplomaciai_pontok'] ?? 0, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="mb-3">
                            <label for="katonai_pontok" class="form-label">Katonai pontok</label>
                            <input type="number" class="form-control" id="katonai_pontok" name="katonai_pontok" value="<?php echo htmlspecialchars($team['katonai_pontok'] ?? 0, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card">
                    <div class="card-header">Épületek</div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="bankok" class="form-label">Bankok</label>
                            <input type="number" class="form-control" id="bankok" name="bankok" value="<?php echo htmlspecialchars($team['bankok'] ?? 0, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="mb-3">
                            <label for="gyarak" class="form-label">Gyárak</label>
                            <input type="number" class="form-control" id="gyarak" name="gyarak" value="<?php echo htmlspecialchars($team['gyarak'] ?? 0, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="mb-3">
                            <label for="egyetemek" class="form-label">Egyetemek</label>
                            <input type="number" class="form-control" id="egyetemek" name="egyetemek" value="<?php echo htmlspecialchars($team['egyetemek'] ?? 0, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="mb-3">
                            <label for="laktanyak" class="form-label">Laktanyak</label>
                            <input type="number" class="form-control" id="laktanyak" name="laktanyak" value="<?php echo htmlspecialchars($team['laktanyak'] ?? 0, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="d-grid mb-4">
            <input type="submit" class="btn btn-primary" name="kezdo_megerosites" value="Kezdő adatok megerősítése">
        </div>
    </form>
</div>
<?php else: ?>
    <div class="container my-4">
        <p class="text-center">Válassz ki csapatot</p>
    </div>
<?php endif; ?>

<div class="container my-4">
    <h2>Recurring Custom Rules</h2>
    <form action="/management?access_key=<?php echo htmlspecialchars($_GET['access_key'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" method="post" class="mb-3">
        <div class="mb-3">
            <label for="rule_team" class="form-label">Csapat:</label>
            <select id="rule_team" name="rule_team" class="form-select" required>
                <option value="">-- Válassz --</option>
                <?php foreach ($teams as $t): ?>
                    <option value="<?php echo htmlspecialchars($t['id'], ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo htmlspecialchars($t['nev'], ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3">
            <label for="rule_field" class="form-label">Mező:</label>
            <select id="rule_field" name="rule_field" class="form-select" required>
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
        </div>
        <div class="mb-3">
            <label for="rule_amount" class="form-label">Összeg (per round):</label>
            <input type="number" id="rule_amount" name="rule_amount" class="form-control" value="0" required>
        </div>
        <div class="d-grid">
            <input type="submit" name="save_recurring_rule" value="Szabály mentése" class="btn btn-primary">
        </div>
    </form>
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
    ?>
    <?php if (!empty($custom_rules)): ?>
    <table class="table table-striped">
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
                    <form action="/management?access_key=<?php echo htmlspecialchars($_GET['access_key'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" method="post" onsubmit="return confirm('Biztosan törlöd ezt a szabályt?');" style="display:inline;">
                        <input type="hidden" name="remove_rule" value="1">
                        <input type="hidden" name="rule_id" value="<?php echo htmlspecialchars($rule['id'], ENT_QUOTES, 'UTF-8'); ?>">
                        <button type="submit" class="btn btn-danger btn-sm">Törlés</button>
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

<?php include(__DIR__ . '/../components/footer.html'); ?>
