<?php
include(__DIR__ . '/../components/head.php');
include_once(__DIR__ . '/../logic/game_backend.php');

if (get_game_phase($connection) !== 'active') {
    echo "<p>A játék még nem indult el.</p>";
    include(__DIR__ . '/../components/footer.html');
    exit;
}

// get history
$query = "SELECT * FROM jatekok_history ORDER BY round ASC, id ASC";
$result = mysqli_query($connection, $query);
if (!$result) {
    echo "Hiba a játéktörténet lekérdezésével: " . mysqli_error($connection);
    exit;
}
$history = mysqli_fetch_all($result, MYSQLI_ASSOC);

// group by round
$grouped_history = [];
foreach ($history as $record) {
    $round = $record['round'];
    if (!isset($grouped_history[$round])) {
        $grouped_history[$round] = [];
    }
    $grouped_history[$round][] = $record;
}

$number_of_teams = 0;
if (!empty($grouped_history)) {
    $first_group = reset($grouped_history);
    $number_of_teams = count($first_group);
}
?>

<style>
    tbody tr:nth-child(<?php echo $number_of_teams . "n"; ?>) > * {
        border-bottom: 3px solid black;
    }
</style>

<table>
    <!-- column groups (altalanos, eroforrasok, epuletek, katonai) -->
    <colgroup span="4" class="cols-start"></colgroup>
    <colgroup span="5" class="cols-eroforrasok"></colgroup>
    <colgroup span="4" class="cols-epuletek"></colgroup>
    <colgroup span="2" class="cols-end"></colgroup>

    <thead>
        <tr>
            <th rowspan="2" style="background-color:#fff">Kör</th>
            <th rowspan="2" style="background-color:rgb(223, 223, 223)">Állam</th>
            <th rowspan="2" style="background-color:rgb(214, 214, 214)">Államforma</th>
            <th rowspan="2" style="background-color:rgb(201, 201, 201)">Kontinens</th>

            <!-- 5 subheaders -->
            <th colspan="5" style="background-color:rgb(210, 222, 240)">Erőforrások</th>

            <!-- 4 subheaders -->
            <th colspan="4" style="background-color:rgb(213, 243, 213)">Épületek</th>

            <th rowspan="2" style="background-color:rgb(255, 209, 244)">Szövetségek</th>
            <th rowspan="2" style="background-color:rgb(229, 209, 255)">Háborúk</th>
        </tr>
        <tr class="subheaders">
            <!-- Erőforrások -->
            <th style="background-color:rgb(184, 206, 240)">Bevétel</th>
            <th style="background-color:rgb(184, 206, 240)">Termelés</th>
            <th style="background-color:rgb(184, 206, 240)">Kutatási pont</th>
            <th style="background-color:rgb(184, 206, 240)">Diplomáciai pont</th>
            <th style="background-color:rgb(184, 206, 240)">Katonai pont</th>

            <!-- Épületek -->
            <th style="background-color:rgb(177, 238, 177)">Bankok</th>
            <th style="background-color:rgb(177, 238, 177)">Gyárak</th>
            <th style="background-color:rgb(177, 238, 177)">Egyetemek</th>
            <th style="background-color:rgb(177, 238, 177)">Laktanyák</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($grouped_history as $round => $records): ?>
            <?php $rowspan = count($records); ?>
            <?php foreach ($records as $index => $record): ?>
            <tr>
                <?php if ($index === 0): ?>
                    <th rowspan="<?php echo $rowspan; ?>" class="round"><?php echo htmlspecialchars($round, ENT_QUOTES, 'UTF-8'); ?></th>
                <?php endif; ?>
                <td><?php echo htmlspecialchars($record['nev'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($record['allamforma'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($record['kontinens'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($record['bevetel'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($record['termeles'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($record['kutatasi_pontok'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($record['diplomaciai_pontok'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($record['katonai_pontok'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($record['bankok'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($record['gyarak'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($record['egyetemek'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($record['laktanyak'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><!-- szovetsegek --></td>
                <td><!-- haboruk --></td>
            </tr>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </tbody>
</table>
<div class="scroll"></div>
<script src="/public/static/js/leaderboard.js"></script>
<?php include(__DIR__ . '/../components/footer.html'); ?>
