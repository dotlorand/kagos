<?php
include(__DIR__ . '/../components/head.php');
include_once(__DIR__ . '/../logic/game_backend.php');

if (get_game_phase($connection) === 'active') {
    header("Location: /round". "?access_key=" . htmlspecialchars($_GET['access_key'] ?? '', ENT_QUOTES, 'UTF-8'));
    exit;
}

// add team
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['add_team'])) {
    $nev        = trim($_POST['nev'] ?? '');
    $allamforma = trim($_POST['allamforma'] ?? '');
    $kontinens  = trim($_POST['kontinens'] ?? '');
    $valid_allamforma = ['törzsi', 'arisztokratikus', 'türannisz', 'kalmár', 'modern', 'kommunista'];
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
                            header("Location: /init?uuid=" . urlencode($team_id). "&access_key=" . htmlspecialchars($_GET['access_key'] ?? '', ENT_QUOTES, 'UTF-8'));
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

// start game
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['start_game'])) {
    $query = "SELECT COUNT(*) AS team_count FROM csapatok";
    $result = mysqli_query($connection, $query);
    $row = mysqli_fetch_assoc($result);
    if ((int)$row['team_count'] === 0) {
        $error = "Nincs csapat hozzáadva!";
    } else {
        if (start_game_now($connection)) {
            header("Location: /round". "?access_key=" . htmlspecialchars($_GET['access_key'] ?? '', ENT_QUOTES, 'UTF-8'));
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

<nav class="navbar navbar-expand-lg">
  <div class="container-fluid">
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#teamsNavbar" aria-controls="teamsNavbar" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="teamsNavbar">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0 d-flex flex-wrap">
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
            $active_class = (isset($team) && $team['id'] === $nav_team['id']) ? 'btn-primary' : 'btn-outline-primary';
            echo "<li class='nav-item me-2 mb-2'><a href='/init?uuid=" . urlencode($team_id_html) . "&access_key=" . htmlspecialchars($_GET['access_key'] ?? '', ENT_QUOTES, 'UTF-8') . "' class='btn {$active_class}'>{$team_name_html}</a></li>";
        }
        mysqli_free_result($result);
        ?>
      </ul>
      <div class="d-flex">
        <button type="button" class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#addTeamModal">Új csapat</button>
        <form action="/init?access_key=<?php echo htmlspecialchars($_GET['access_key'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" method="post" class="d-flex" onsubmit="return confirm('Biztosan elindítod a játékot?');">
          <input type="hidden" name="start_game" value="1">
          <button type="submit" class="btn btn-success">Játék indítása</button>
        </form>
      </div>
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
</div>

<div class="modal fade" id="addTeamModal" tabindex="-1" aria-labelledby="addTeamModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form action="/init?access_key=<?php echo htmlspecialchars($_GET['access_key'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" method="post">
        <div class="modal-header">
          <h5 class="modal-title" id="addTeamModalLabel">Csapat hozzáadása</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Bezárás"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label for="team-nev" class="form-label">Csapat név</label>
            <input required type="text" class="form-control" id="team-nev" name="nev" autocomplete="off" placeholder="Csapat név" maxlength="30">
          </div>
          <div class="mb-3">
            <label for="team-allamforma" class="form-label">Államforma</label>
            <select required class="form-select" id="team-allamforma" name="allamforma">
              <option value="" disabled selected>Válassz államformát</option>
              <option value="törzsi">Törzsi falu</option>
              <option value="arisztokratikus">Arisztokratikus köztársaság</option>
              <option value="türannisz">Türannisz</option>
              <option value="kalmár">Kalmár köztársaság</option>
              <option value="modern">Modern demokrácia</option>
              <option value="kommunista">Kommunista diktatúra</option>
            </select>
          </div>
          <div class="mb-3">
            <label for="team-kontinens" class="form-label">Kontinens</label>
            <input required type="text" class="form-control" id="team-kontinens" name="kontinens" autocomplete="off" placeholder="Kontinens" maxlength="30">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Mégse</button>
          <button type="submit" name="add_team" value="Hozzáadás" class="btn btn-primary">Hozzáadás</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="removeTeamModal" tabindex="-1" aria-labelledby="removeTeamModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="removeTeamModalLabel">Csapat törlése</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Bezárás"></button>
      </div>
      <div class="modal-body">
        <p>Biztosan törlöd a <strong id="teamNameConfirm"></strong> csapatot?</p>
        <form action="/init?access_key=<?php echo htmlspecialchars($_GET['access_key'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" method="post" id="removeTeamForm">
          <input type="hidden" name="team_id" id="teamIdInput" value="">
          <input type="hidden" name="remove_team" value="1">
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Mégse</button>
        <button type="button" class="btn btn-danger" onclick="document.getElementById('removeTeamForm').submit();">Törlés</button>
      </div>
    </div>
  </div>
</div>

<?php if (isset($team)) : ?>
<div class="container my-4">
  <div class="row mb-3 align-items-center">
    <div class="col">
      <h1><?php echo htmlspecialchars($team['nev'], ENT_QUOTES, 'UTF-8'); ?> kezdő tőkéje</h1>
    </div>
    <div class="col-auto">
      <button type="button" class="btn btn-danger" onclick="openRemoveTeamModal('<?php echo $team['id']; ?>', '<?php echo htmlspecialchars($team['nev'], ENT_QUOTES, 'UTF-8'); ?>')">Csapat törlése</button>
    </div>
  </div>
  <form class="init-form" action="/init?uuid=<?php echo urlencode($team['id']); ?>&access_key=<?php echo htmlspecialchars($_GET['access_key'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" method="post">
    <input type="hidden" name="team_id" value="<?php echo htmlspecialchars($team['id'], ENT_QUOTES, 'UTF-8'); ?>">
    <div class="row">
      <div class="col-md-4">
        <div class="card mb-3">
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
              <input type="hidden" name="politikak" id="politikak-hidden" value="<?php echo htmlspecialchars($team['politikak'], ENT_QUOTES, 'UTF-8'); ?>">
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card mb-3">
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
      <div class="col-md-4">
        <div class="card mb-3">
          <div class="card-header">Épületek</div>
          <div class="card-body">
            <div class="row mb-3">
              <div class="col">
                <label for="bankok" class="form-label">Bankok</label>
                <input type="number" class="form-control" id="bankok" name="bankok" value="<?php echo htmlspecialchars($team['bankok'] ?? 0, ENT_QUOTES, 'UTF-8'); ?>">
              </div>
              <div class="col">
                <label for="gyarak" class="form-label">Gyárak</label>
                <input type="number" class="form-control" id="gyarak" name="gyarak" value="<?php echo htmlspecialchars($team['gyarak'] ?? 0, ENT_QUOTES, 'UTF-8'); ?>">
              </div>
            </div>
            <div class="row mb-3">
              <div class="col">
                <label for="egyetemek" class="form-label">Egyetemek</label>
                <input type="number" class="form-control" id="egyetemek" name="egyetemek" value="<?php echo htmlspecialchars($team['egyetemek'] ?? 0, ENT_QUOTES, 'UTF-8'); ?>">
              </div>
              <div class="col">
                <label for="laktanyak" class="form-label">Laktanyak</label>
                <input type="number" class="form-control" id="laktanyak" name="laktanyak" value="<?php echo htmlspecialchars($team['laktanyak'] ?? 0, ENT_QUOTES, 'UTF-8'); ?>">
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="d-grid">
      <input type="submit" class="btn btn-primary" name="kezdo_megerosites" value="Kezdő adatok megerősítése">
    </div>
  </form>
</div>
<?php else: ?>
<div class="container my-4">
  <p class="text-center">Válassz ki csapatot</p>
</div>
<?php endif; ?>

<?php include(__DIR__ . '/../components/footer.html'); ?>
