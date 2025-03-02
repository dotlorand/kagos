<?php

include(__DIR__ . '/../components/head.php');

// csapat hozzáadása

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['add_team'])) {

    // megadott adatok
    $nev        = trim($_POST['nev'] ?? '');
    $allamforma = trim($_POST['allamforma'] ?? '');
    $kontinens  = trim($_POST['kontinens'] ?? '');

    if ($nev === '' || $allamforma === '' || $kontinens === '') {
        $error = "Minden mezőt ki kell tölteni!";
    }
    else {
        // lekerdezes elokeszitese pl syntax check
        $stmt = mysqli_prepare($connection, "INSERT INTO csapatok (nev, allamforma, kontinens) VALUES (?, ?, ?)");

        if (!$stmt) {
            $error = "Hiba a lekérdezéssel: " . mysqli_error($connection);
        }
        else {
            // adatok beillesztese a meglevo lekerdezes-be
            mysqli_stmt_bind_param($stmt, "sss", $nev, $allamforma, $kontinens);

            // lekerdezes futtatasa
            if (mysqli_stmt_execute($stmt)) {
                $success = "Csapat hozzáadva!";
            } else {
                $error = "Hiba: " . mysqli_stmt_error($stmt);
            }

            // statement bezarasa (memoriafelszabaditas)
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

?>
<link rel="stylesheet" href="/public/static/css/pages/manage.css">

<nav>
    <ul>
        <?php
        $query = "SELECT id, nev FROM csapatok ORDER BY letrehozva";
        $result = mysqli_query($connection, $query);

        if (!$result) {
            error_log("Database query failed: " . mysqli_error($connection));
            echo "<div class='error'>Hiba a csapatok lekérdezésével.</div>";
            exit;
        }

        echo "<ul>";
        while ($team = mysqli_fetch_assoc($result)) {
            $team_id = htmlspecialchars($team['id'], ENT_QUOTES, 'UTF-8');
            $team_name = htmlspecialchars($team['nev'], ENT_QUOTES, 'UTF-8');
            
            echo "<li>";
            echo "<a href='/manage?uuid=" . urlencode($team_id) . "'>{$team_name}</a> ";
            // json_encode-al kuldi at a php ertekeket js-be
            echo "<button onclick=\"removePopup(" . htmlspecialchars(json_encode($team_id), ENT_QUOTES, 'UTF-8') . ", " . htmlspecialchars(json_encode($team_name), ENT_QUOTES, 'UTF-8') . ")\">Törlés</button>";
            echo "</li>";
        }
        echo "</ul>";

        mysqli_free_result($result);
        ?>
    </ul>
    <button onclick="popup('add-team')">Új csapat</button>
</nav>

<?php if (isset($error)) : ?>
    <div class="toast error"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if (isset($success)) : ?>
    <div class="toast success"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>

<div class="popup-container">
    <div id="add-team">
        <div class="popup-header">
            <h2>Csapat hozzáadása</h2>
            <button onclick="closePopup(this.closest('div[id]').id)">Mégse</button>
        </div>
        <form action="" method="post">
            <input type="text" name="nev" autocomplete="off" placeholder="Csapat név" maxlength="30">
            <input type="text" name="allamforma" autocomplete="off" placeholder="Államforma" maxlength="30">
            <input type="text" name="kontinens" autocomplete="off" placeholder="Kontinens" maxlength="30">
            <input type="submit" name="add_team" value="Hozzáadás">
        </form>
    </div>
    <div id="remove-team">
        <div class="popup-header">
            <h2>Csapat törlése</h2>
            <button onclick="closePopup('remove-team')">Mégse</button>
        </div>
        <form action="" method="post">
            <p>Biztosan törlöd a <strong id="team-name-confirm"></strong> csapatot?</p>
            <input type="hidden" name="team_id" value="">
            <input type="submit" name="remove_team" value="Törlés">
        </form>
    </div>
</div>

<?php include(__DIR__ . '/../components/footer.php'); ?>