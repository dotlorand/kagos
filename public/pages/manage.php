<?php

include(__DIR__ . '/../components/head.php');

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

?>
<link rel="stylesheet" href="/public/static/css/pages/manage.css">

<nav>
    <ul>
        <?php echo 'Csapat linkek'; ?>
    </ul>
    <button onclick="popup('add-team')">Új csapat</button>
</nav>

<?php if (isset($error)) : ?>
    <div class="error"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if (isset($success)) : ?>
    <div class="success"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>

<div class="popup-container">
    <div id="add-team">
        <div class="popup-header">
            <h2>Csapat hozzáadása</h2>
            <button onclick="closePopup(this.closest('div[id]').id)">Bezár</button>
        </div>
        <form action="" method="post">
            <input type="text" name="nev" autocomplete="off" placeholder="Csapat név" maxlength="30">
            <input type="text" name="allamforma" autocomplete="off" placeholder="Államforma" maxlength="30">
            <input type="text" name="kontinens" autocomplete="off" placeholder="Kontinens" maxlength="30">
            <input type="submit" name="add_team" value="Hozzáadás">
        </form>
    </div>
</div>

<?php include(__DIR__ . '/../components/footer.php'); ?>