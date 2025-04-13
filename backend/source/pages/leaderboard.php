<?php
include(__DIR__ . '/../../database/connect.php');
include_once(__DIR__ . '/../logic/game_backend.php');

if (get_game_phase($connection) !== 'active') {
    if (isset($_GET['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode(['history' => [], 'winners' => []]);
        exit;
    }

    echo "<p>A játék még nem indult el.</p>";
    include(__DIR__ . '/../components/footer.html');
    exit;
}

$winners = [];
$winRes = mysqli_query($connection, "SELECT nev FROM csapatok WHERE winner=1");
if ($winRes) {
    while ($row = mysqli_fetch_assoc($winRes)) {
        $winners[] = $row['nev'];
    }
    mysqli_free_result($winRes);
}

$query = "SELECT * FROM jatekok_history ORDER BY round ASC, id ASC";
$result = mysqli_query($connection, $query);
if (!$result) {
    if (isset($_GET['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode(['error' => mysqli_error($connection), 'history' => [], 'winners' => $winners]);
        exit;
    }
    echo "Hiba a játéktörténet lekérdezésével: " . mysqli_error($connection);
    exit;
}
$history = mysqli_fetch_all($result, MYSQLI_ASSOC);
mysqli_free_result($result);

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
    $first_round_records = reset($grouped_history);
    $number_of_teams = count($first_round_records);
}


if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    $structured = [];
    foreach ($grouped_history as $round => $records) {
        $structured[] = [
            'round' => $round,
            'records' => $records, 
        ];
    }
    echo json_encode([
        'number_of_teams' => $number_of_teams,
        'history' => $structured,
        'winners' => $winners
    ], JSON_PRETTY_PRINT);
    exit;
}


?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>TARS - history</title>
  <link rel="stylesheet" href="/public/static/css/leaderboard.css">
</head>
<body>

<main>
<style>
  tbody tr:nth-child(<?php echo $number_of_teams; ?>n) > * {
    border-bottom: 3px solid black;
  }

  .winner-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background:rgba(100, 224, 106, 0.56);
    backdrop-filter: blur(5px);
    width: 100%;
    height: 100%;
    padding: 2rem;
    z-index: 9999;
    justify-content: center;
    align-items: center;
    flex-direction: column;
    gap: 20px;
  }

  .winner-modal h1 {
    font-size: 3rem;
    margin: 0;
    color: black;
  }
  
  .winner-modal button {
    background-color: #4CAF50;
    border: none;
    border-radius: 100px;
    padding: 10px 20px;
  }
</style>

<table id="historyTable">
  <colgroup span="1" class="cols-round"></colgroup>
  <colgroup span="3" class="cols-start"></colgroup>
  <colgroup span="5" class="cols-eroforrasok"></colgroup>
  <colgroup span="4" class="cols-epuletek"></colgroup>

  <thead id="main-thead">
    <tr>
      <th rowspan="2" style="background-color:#fff">Kör</th>
      <th rowspan="2" style="background-color:rgb(223, 223, 223)">Állam</th>
      <th rowspan="2" style="background-color:rgb(214, 214, 214)">Államforma</th>
      <th rowspan="2" style="background-color:rgb(201, 201, 201)">Kontinens</th>

      <th colspan="5" style="background-color:rgb(210, 222, 240)">Erőforrások</th>
      <th colspan="4" style="background-color:rgb(213, 243, 213)">Épületek</th>
    </tr>
    <tr class="subheaders">
      <th style="background-color:rgb(184, 206, 240)">Bevétel</th>
      <th style="background-color:rgb(184, 206, 240)">Termelés</th>
      <th style="background-color:rgb(184, 206, 240)">Kutatási pont</th>
      <th style="background-color:rgb(184, 206, 240)">Diplomáciai pont</th>
      <th style="background-color:rgb(184, 206, 240)">Katonai pont</th>

      <th style="background-color:rgb(177, 238, 177)">Bankok</th>
      <th style="background-color:rgb(177, 238, 177)">Gyárak</th>
      <th style="background-color:rgb(177, 238, 177)">Egyetemek</th>
      <th style="background-color:rgb(177, 238, 177)">Laktanyák</th>
    </tr>
  </thead>
  <tbody id="historyTbody"><!-- dynamic --></tbody>
</table>

<div style="height: 100px;"></div>

<div id="winnerModal" class="winner-modal">
  <h1 id="winnerMessage"></h1>
  <button onclick="document.getElementById('winnerModal').style.display='none'">OK</button>
</div>

<script>
function mapAllamforma(value) {
  const map = {
    'törzsi': 'Törzsi falu',
    'arisztokratikus': 'Arisztokratikus köztársaság',
    'türannisz': 'Türannisz',
    'kalmár': 'Kalmár köztársaság',
    'modern': 'Modern demokrácia',
    'kommunista': 'Kommunista diktatúra'
  };
  return map[value] || value;
}

function addCell(tr, txt) {
  let td = document.createElement('td');
  td.textContent = txt == null ? '' : txt;
  tr.appendChild(td);
}

function buildRowCells(tr, record) {
  addCell(tr, record.nev);
  addCell(tr, mapAllamforma(record.allamforma));
  addCell(tr, record.kontinens);
  addCell(tr, record.bevetel);
  addCell(tr, record.termeles);
  addCell(tr, record.kutatasi_pontok);
  addCell(tr, record.diplomaciai_pontok);
  addCell(tr, record.katonai_pontok);
  addCell(tr, record.bankok);
  addCell(tr, record.gyarak);
  addCell(tr, record.egyetemek);
  addCell(tr, record.laktanyak);
}

document.addEventListener('DOMContentLoaded', function() {
    const thead = document.getElementById('main-thead');
    if (!thead) return;

    window.addEventListener('scroll', function() {
        // check pos based on viewport
        const rect = thead.getBoundingClientRect();
        if (rect.top <= 0) {
            thead.classList.add('stuck');
        } else {
            thead.classList.remove('stuck');
        }
    });
});

function buildAjaxUrl() {
  const loc = window.location;
  let base = loc.protocol + '//' + loc.host + loc.pathname;
  let urlParams = new URLSearchParams(loc.search);
  urlParams.delete('ajax');
  urlParams.set('ajax','1');
  return base + '?' + urlParams.toString();
}

var lastRowCount = 0;
var winnerShown = false;

async function fetchAndUpdate() {
  try {
    let fetchUrl = buildAjaxUrl();
    let resp = await fetch(fetchUrl);
    if (!resp.ok) {
      console.error('Failed to fetch leaderboard via AJAX:', resp.status);
      return;
    }
    let data = await resp.json();
    renderHistory(data);
  } catch (err) {
    console.error('Error in fetchAndUpdate:', err);
  }
}

function renderHistory(data) {
  const tbody = document.getElementById('historyTbody');
  tbody.innerHTML = ''; // clear

  let totalRows = 0;
  const baseline = data.number_of_teams || 0;

  (data.history || []).forEach(block => {
    const round = block.round;
    const records = block.records || [];
    const countThisRound = records.length;
    const missing = Math.max(baseline - countThisRound, 0);
    const blockRows = countThisRound + missing;

    if (records.length > 0) {
      let first = records.shift();
      let tr = document.createElement('tr');

      let roundCell = document.createElement('th');
      roundCell.className = 'round';
      roundCell.rowSpan = blockRows;
      roundCell.textContent = round;
      tr.appendChild(roundCell);

      buildRowCells(tr, first);
      tbody.appendChild(tr);
      totalRows++;
    }

    records.forEach(r => {
      let tr = document.createElement('tr');
      buildRowCells(tr, r);
      tbody.appendChild(tr);
      totalRows++;
    });

    for (let m = 0; m < missing; m++) {
      let tr = document.createElement('tr');
      // first cell => '---'
      let td1 = document.createElement('td');
      td1.textContent = '---';
      tr.appendChild(td1);
      // 11 more columns
      for (let c = 0; c < 11; c++) {
        let ctd = document.createElement('td');
        tr.appendChild(ctd);
      }
      tbody.appendChild(tr);
      totalRows++;
    }
  });

  if (totalRows > lastRowCount) {
    window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
  }
  lastRowCount = totalRows;

  if (!winnerShown && data.winners && data.winners.length > 0) {
    winnerShown = true;
    let msg = 'Nyertes: ' + data.winners.join(', ');
    showWinner(msg);
  }
}

function showWinner(message) {
  let modal = document.getElementById('winnerModal');
  let msgEl = document.getElementById('winnerMessage');
  msgEl.textContent = message;
  modal.style.display = 'flex';
}

fetchAndUpdate();
setInterval(fetchAndUpdate, 2000);
</script>

</main>
</body>
</html>
