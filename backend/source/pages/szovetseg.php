<?php
session_start();
include(__DIR__ . '/../components/head.php');
include_once(__DIR__ . '/../logic/game_backend.php');

// Ensure the game is active.
if (get_game_phase($connection) !== 'active') {
    header("Location: /init");
    exit;
}

// Process alliance join/leave requests.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['join_alliance'])) {
        $team_id = trim($_POST['team_id']);
        $alliance_name = trim($_POST['alliance_name']);
        if ($team_id && $alliance_name) {
            $stmt = mysqli_prepare($connection, "UPDATE csapatok SET alliance = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "ss", $alliance_name, $team_id);
            if (mysqli_stmt_execute($stmt)) {
                $success = "Csapat csatlakozott a szövetséghez!";
            } else {
                $error = "Hiba a csatlakozás során.";
            }
            mysqli_stmt_close($stmt);
        } else {
            $error = "Hiányzó adatok!";
        }
    } elseif (isset($_POST['leave_alliance'])) {
        $team_id = trim($_POST['team_id']);
        if ($team_id) {
            $stmt = mysqli_prepare($connection, "UPDATE csapatok SET alliance = '' WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "s", $team_id);
            if (mysqli_stmt_execute($stmt)) {
                $success = "Csapat kilépett a szövetségből!";
            } else {
                $error = "Hiba a kilépés során.";
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// Retrieve teams along with their alliance and diplomacy info.
$teams = [];
$query = "SELECT id, nev, alliance, diplomaciai_pontok FROM csapatok ORDER BY nev";
$result = mysqli_query($connection, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $teams[] = $row;
    }
    mysqli_free_result($result);
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title>Szövetségek</title>
    <link rel="stylesheet" href="/public/static/css/pages/szovetseg.css">
</head>
<body>
    <nav>
        <a href="/round">Vissza a játék menedzsmenthez</a>
    </nav>
    <div class="container">
        <h1>Szövetségek</h1>
        <?php if (isset($error)): ?>
            <div class="toast error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <?php if (isset($success)): ?>
            <div class="toast success"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <h2>Csapatok és szövetségi státusz</h2>
        <table>
            <thead>
                <tr>
                    <th>Csapat</th>
                    <th>Szövetség</th>
                    <th>Diplomáciai pontok</th>
                    <th>Művelet</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($teams as $team): ?>
                <tr>
                    <td><?php echo htmlspecialchars($team['nev'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($team['alliance'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($team['diplomaciai_pontok'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td>
                        <?php if (empty($team['alliance'])): ?>
                        <form method="post" action="/szovetsegek">
                            <input type="hidden" name="team_id" value="<?php echo htmlspecialchars($team['id'], ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="text" name="alliance_name" placeholder="Szövetség neve" required>
                            <input type="submit" name="join_alliance" value="Csatlakozás">
                        </form>
                        <?php else: ?>
                        <form method="post" action="/szovetsegek">
                            <input type="hidden" name="team_id" value="<?php echo htmlspecialchars($team['id'], ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="submit" name="leave_alliance" value="Kilépés">
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p>Minden szövetség tagja minden körben <strong>+1 diplomáciai pontot</strong> kap.</p>
    </div>
</body>
</html>
