<?php
session_start();
include(__DIR__ . '/../components/head.php');
include_once(__DIR__ . '/../logic/game_backend.php');

if (get_game_phase($connection) !== 'active') {
    header("Location: /init". "?access_key=" . htmlspecialchars($_GET['access_key'] ?? '', ENT_QUOTES, 'UTF-8'));
    exit;
}

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

<div class="m-3">
  <a href="/round?access_key=<?php echo htmlspecialchars($_GET['access_key'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-secondary">Vissza a játék menedzsmenthez</a>
</div>

<div class="container mb-4">
  <h1 class="mb-4">Szövetségek</h1>

  <?php if (isset($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>
  <?php if (isset($success)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      <?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <h2 class="mb-3">Csapatok és szövetségi státusz</h2>

  <div class="table-responsive mb-3">
    <table class="table table-striped align-middle">
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

              <form method="post" action="/szovetsegek?access_key=<?php echo htmlspecialchars($_GET['access_key'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" class="d-flex flex-wrap gap-2">
                <input type="hidden" name="team_id" value="<?php echo htmlspecialchars($team['id'], ENT_QUOTES, 'UTF-8'); ?>">
                <input 
                  type="text" 
                  name="alliance_name" 
                  placeholder="Szövetség neve" 
                  class="form-control w-auto" 
                  required
                >
                <button type="submit" name="join_alliance" class="btn btn-primary">Csatlakozás</button>
              </form>
            <?php else: ?>
              <form method="post" action="/szovetsegek?access_key=<?php echo htmlspecialchars($_GET['access_key'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" class="d-inline">
                <input type="hidden" name="team_id" value="<?php echo htmlspecialchars($team['id'], ENT_QUOTES, 'UTF-8'); ?>">
                <button type="submit" name="leave_alliance" class="btn btn-danger">Kilépés</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <p class="fst-italic">
    Minden szövetség tagja minden körben <strong>+1 diplomáciai pontot</strong> kap.
  </p>

</div>

<?php include(__DIR__ . '/../components/footer.html'); ?>