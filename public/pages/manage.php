<?php

include(__DIR__ . '/../components/head.php');

// csapat hozzáadása

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

// csapat törlése

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['remove_team'])) {
    $team_id = trim($_POST['team_id'] ?? '');

    if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $team_id)) {
        $error = "Érvénytelen csapat azonosító.";
    }
    else {

        $stmt = mysqli_prepare($connection, "DELETE FROM csapatok WHERE id = ?");

        if (!$stmt) {
            $error = "Hiba a törlési lekérdezés előkészítésekor: " . mysqli_error($connection);
        }
        else {
            mysqli_stmt_bind_param($stmt, "s", $team_id);

            if (mysqli_stmt_execute($stmt)) {
                $success = "Csapat törölve!";
            }
            else {
                $error = "Hiba a csapat törlésekor: " . mysqli_stmt_error($stmt);
            }

            mysqli_stmt_close($stmt);
        }
    }
}

// csapat adatok, uuid

if (isset($_GET['uuid']) && $_GET['uuid'] !== '') {
    $uuid = trim($_GET['uuid']);

    // regex validalas
    if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid)) {
        $error = "Érvénytelen azonosító formátum.";
    } else {
        // sql elokeszites, error check majd lekerdezes
        $stmt = mysqli_prepare($connection, "SELECT * FROM csapatok WHERE id = ?");
        if (!$stmt) {
            $error = "Hiba a lekérdezés előkészítésekor: " . htmlspecialchars(mysqli_error($connection), ENT_QUOTES, 'UTF-8');
        } else {
            mysqli_stmt_bind_param($stmt, "s", $uuid);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
    
            if ($result && mysqli_num_rows($result) === 1) {
                $team = mysqli_fetch_assoc($result);
            } else {
                $error = "A megadott csapat nem létezik.";
            }
            mysqli_stmt_close($stmt);
        }
    }
}

?>

<style>
    .popup-container > div {
        display: none;
    }
</style>

<link rel="stylesheet" href="/public/static/css/pages/manage.css">

<nav class="teams-nav">
    <ul>
        <?php
        $query = "SELECT id, nev FROM csapatok ORDER BY letrehozva";
        $result = mysqli_query($connection, $query);

        if (!$result) {
            error_log("Database query failed: " . mysqli_error($connection));
            $error = "Hiba a csapatok lekérdezésével.";
            exit;
        }

        echo "<ul>";
        while ($nav_team = mysqli_fetch_assoc($result)) {
            $team_id_raw   = $nav_team['id'];
            $team_name_raw = $nav_team['nev'];

            // html escape
            $team_id_html   = htmlspecialchars($team_id_raw, ENT_QUOTES, 'UTF-8');
            $team_name_html = htmlspecialchars($team_name_raw, ENT_QUOTES, 'UTF-8');

            echo "<li>";
            echo "<a href='/manage?uuid=" . urlencode($team_id_html) . "'>{$team_name_html}</a> [Még nincs kész] ";
            
            // encoding ami megy js-be
            $onclick = "removePopup(" 
                . json_encode($team_id_raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                . ", " 
                . json_encode($team_name_raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                . ")";

            // beillesztes, "escape"
            echo "<button onclick=\"" . htmlspecialchars($onclick, ENT_QUOTES, 'UTF-8') . "\">Törlés</button>";
            echo "</li>";
            
        }
        echo "</ul>";

        mysqli_free_result($result);
        ?>
    </ul>
    <button onclick="popup('add-team')">Új csapat</button>
</nav>

<?php if (isset($error)) : ?>
    <div class="toast error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>

<?php if (isset($success)) : ?>
    <div class="toast success"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>

<div class="popup-container">
    <div id="add-team">
        <div class="popup-header">
            <h2>Csapat hozzáadása</h2>
            <button onclick="closePopup(this.closest('div[id]').id)">Mégse</button>
        </div>
        <form action="/manage" method="post">
            <input type="text" name="nev" autocomplete="off" placeholder="Csapat név" maxlength="30">
            <select name="allamforma">
                <option value="" disabled selected>Válassz államformát</option>
                <option value="demokratikus">Demokratikus</option>
                <option value="test">test</option>
            </select>
            <input type="text" name="kontinens" autocomplete="off" placeholder="Kontinens" maxlength="30">
            <input type="submit" name="add_team" value="Hozzáadás">
        </form>
    </div>

    <div id="remove-team">
        <div class="popup-header">
            <h2>Csapat törlése</h2>
            <button onclick="closePopup('remove-team')">Mégse</button>
        </div>
        <form action="/manage" method="post">
            <p>Biztosan törlöd a <strong id="team-name-confirm"></strong> csapatot?</p>
            <input type="hidden" name="team_id" value="">
            <input type="submit" name="remove_team" value="Törlés">
        </form>
    </div>
</div>

<!--

<section class="team-dashboard">
    <h2><?php echo htmlspecialchars($team['nev'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h2>
    <div class="team-data-container">
        <p>Államforma: <?php echo htmlspecialchars($team['allamforma'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
        <p>Kontinens: <?php echo htmlspecialchars($team['kontinens'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
        <p>Bevétel: <?php echo htmlspecialchars($team['bevetel'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
        <p>Termelés: <?php echo htmlspecialchars($team['termeles'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
        <p>Kutatási pontok: <?php echo htmlspecialchars($team['kutatasi_pontok'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
        <p>Diplomáciai pontok: <?php echo htmlspecialchars($team['diplomaciai_pontok'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
        <p>Katonai pontok: <?php echo htmlspecialchars($team['katonai_pontok'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
        <p>Bankok: <?php echo htmlspecialchars($team['bankok'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
        <p>Gyárak: <?php echo htmlspecialchars($team['gyarak'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
        <p>Egyetemek: <?php echo htmlspecialchars($team['egyetemek'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
        <p>Laktanyak: <?php echo htmlspecialchars($team['laktanyak'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
        <p>Politikák: <?php echo htmlspecialchars($team['politikak'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
    </div>
</section>
    
-->

<?php if (isset($team)) : ?>
    <div class="container">
        <h1>Kezdő adatok:</h1>
        <form action="process_form.php" method="post">
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
            <input type="submit" class="submit-btn" value="Kezdő adatok megerősítése">
        </form>
    </div>

<script>
    // Politikák chip selection script
    const select = document.getElementById('politikak-select');
    const chipsContainer = document.getElementById('chips-container');
    const hiddenInput = document.getElementById('politikak-hidden');

    let selectedOptions = [];

    function updateHiddenInput() {
        hiddenInput.value = JSON.stringify(selectedOptions);
    }

    select.addEventListener('change', function() {
        const value = select.value;
        const text = select.options[select.selectedIndex].text;
        if (value && !selectedOptions.some(opt => opt.value === value)) {
            selectedOptions.push({ value, text });
            const chip = document.createElement('span');
            chip.className = 'chip';
            chip.textContent = text;
            chip.dataset.value = value;
            chip.addEventListener('click', function() {
                chipsContainer.removeChild(chip);
                selectedOptions = selectedOptions.filter(opt => opt.value !== value);
                const option = document.createElement('option');
                option.value = value;
                option.text = text;
                select.appendChild(option);
                updateHiddenInput();
            });
            chipsContainer.appendChild(chip);
            const optionToRemove = select.querySelector(`option[value="${value}"]`);
            if (optionToRemove) {
                optionToRemove.remove();
            }
            updateHiddenInput();
            select.value = "";
        }
    });
</script>
<?php else: ?>
    Válassz ki csapatot
<?php endif; ?>


<?php include(__DIR__ . '/../components/footer.php'); ?>
