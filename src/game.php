<?php
require __DIR__ . '/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$isTranslate = (($_GET['action'] ?? '') === 'translate');
if ($isTranslate) {
    header('Content-Type: application/json; charset=utf-8');
    $in  = json_decode(file_get_contents('php://input'), true);
    $src = trim((string)($in['source'] ?? 'deu_Latn'));
    $tgt = trim((string)($in['target'] ?? ''));
    $text = $in['text'] ?? '';

    if ($tgt === '' || $text === '') {
        http_response_code(400);
        echo json_encode(['error' => 'missing target or text'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $payload = json_encode([
        'text'   => $text,
        'source' => $src,
        'target' => $tgt,
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init('http://localhost:8000/translate');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 60,
    ]);
    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp === false || $code !== 200) {
        http_response_code(502);
        echo json_encode(['error' => 'translate service unavailable', 'detail' => $err], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo $resp;
    exit;
}

$rows = db()->query('SELECT id, kategorie, deutsch, tuerkisch, satz_deutsch, satz_tuerkisch FROM words ORDER BY id')->fetchAll();

$words = [];
$categories = [];
foreach ($rows as $row) {
    $cat = $row['kategorie'];
    $categories[$cat] = $cat;
    $words[] = [
        'de' => $row['deutsch'],
        'tr' => $row['tuerkisch'],
        'cat' => $cat,
        'sd' => $row['satz_deutsch'],
        'st' => $row['satz_tuerkisch'],
    ];
}

shuffle($words);

$langs = [
    'eng_Latn' => 'English',
    'fra_Latn' => 'French',
    'spa_Latn' => 'Spanish',
    'deu_Latn' => 'German',
    'ita_Latn' => 'Italian',
    'por_Latn' => 'Portuguese',
    'nld_Latn' => 'Dutch',
    'pol_Latn' => 'Polish',
    'ces_Latn' => 'Czech',
    'rus_Cyrl' => 'Russian',
    'ukr_Cyrl' => 'Ukrainian',
    'arb_Arab' => 'Arabic',
    'pes_Arab' => 'Persian',
    'hin_Deva' => 'Hindi',
    'tur_Latn' => 'Turkish',
    'ell_Grek' => 'Greek',
    'swe_Latn' => 'Swedish',
    'nor_Latn' => 'Norwegian',
    'jpn_Jpan' => 'Japanese',
    'zho_Hans' => 'Chinese (Simplified)',
];

$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
$username  = htmlspecialchars($_SESSION['username']);
$pageTitle = 'Word Trainer';
require __DIR__ . '/header.php'; ?>

<main class="game">
  <div class="game-head">
    <h1>Word Trainer</h1>
    <p class="sub">Click the card to reveal the translation. Pick a language to translate on the fly.</p>
  </div>

  <div class="game-bar">
    <label for="category">Category</label>
    <select id="category">
      <option value="">All categories</option>
      <?php foreach ($categories as $key => $label): ?>
        <option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($label) ?></option>
      <?php endforeach; ?>
    </select>
    <label for="lang">Lang</label>
    <select id="lang">
      <option value="">Turkish (default)</option>
      <?php foreach ($langs as $code => $label): ?>
        <option value="<?= htmlspecialchars($code) ?>"><?= htmlspecialchars($label) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="button" id="shuffle" class="btn ghost">Sh</button>
    
  </div>

  <div class="counter"><span id="pos">0</span> / <span id="total">0</span></div>

  <div class="flip-card" id="card" role="button" tabindex="0" aria-label="Flashcard, click to flip">
    <div class="flip-inner">
      <div class="face front">
        <span class="badge" id="frontCat"></span>
        <p class="word" id="frontWord"></p>
        <p class="sentence" id="frontSatz"></p>
        <span class="tap-hint">click to reveal</span>
      </div>
      <div class="face back">
        <span class="badge" id="backCat"></span>
        <p class="word tr" id="backWord"></p>
        <p class="sentence" id="backSatz"></p>
        <span class="tap-hint">click to flip back</span>
      </div>
    </div>
  </div>

  <div class="nav-buttons">
    <button type="button" id="prev" class="btn ghost">&larr; Previous</button>
    <button type="button" id="toggleSatz" class="btn ghost" aria-pressed="false">S</button>
    <button type="button" id="next" class="btn">Next &rarr;</button>
  </div>
</main>

<script>
const WORDS = <?= json_encode($words, $jsonFlags) ?>;
const CAT_LABELS = <?= json_encode($categories, $jsonFlags) ?>;
const SRC_LANG = 'deu_Latn';

let deck = WORDS.slice();
let idx = 0;

const card = document.getElementById('card');
const posEl = document.getElementById('pos');
const totalEl = document.getElementById('total');
const frontWord = document.getElementById('frontWord');
const frontCat = document.getElementById('frontCat');
const backWord = document.getElementById('backWord');
const backCat = document.getElementById('backCat');
const frontSatz = document.getElementById('frontSatz');
const backSatz = document.getElementById('backSatz');

let sentencesOn = localStorage.getItem('satz') !== 'off';
const satzBtn = document.getElementById('toggleSatz');

let targetLang = '';
const langSel = document.getElementById('lang');
const cache = new Map();
let translateSeq = 0;

async function translateOne(text, target) {
    if (!text || !target) return null;
    const key = target + '|' + text;
    if (cache.has(key)) return cache.get(key);
    try {
        const res = await fetch('game.php?action=translate', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ source: SRC_LANG, target: target, text: text })
        });
        if (!res.ok) return null;
        const data = await res.json();
        const out = Array.isArray(data.translations) && data.translations.length
            ? data.translations[0] : null;
        cache.set(key, out);
        return out;
    } catch (e) {
        return null;
    }
}

function setBackWord(w) {
    if (targetLang) {
        backWord.textContent = 'Translating…';
        backWord.classList.add('translating');
    } else {
        backWord.textContent = w.tr;
        backWord.classList.remove('translating');
    }
}

function applySatz(w, tr) {
    const show = sentencesOn && w && w.sd;
    frontSatz.textContent = show ? w.sd : '';
    if (show && targetLang) {
        backSatz.textContent = tr || 'Translating…';
    } else {
        backSatz.textContent = show ? w.st : '';
    }
    frontSatz.classList.toggle('hidden', !show);
    backSatz.classList.toggle('hidden', !show);
}

satzBtn.classList.toggle('active', sentencesOn);
satzBtn.setAttribute('aria-pressed', String(sentencesOn));

function render() {
    if (deck.length === 0) {
        frontWord.textContent = 'No words';
        backWord.textContent = '-';
        frontCat.textContent = '';
        backCat.textContent = '';
        posEl.textContent = '0';
        totalEl.textContent = '0';
        applySatz(null, null);
        return;
    }
    const w = deck[idx];
    frontWord.textContent = w.de;
    frontCat.textContent = w.cat;
    backCat.textContent = w.cat;
    posEl.textContent = idx + 1;
    totalEl.textContent = deck.length;
    card.classList.remove('flipped');

    if (!targetLang) {
        backWord.textContent = w.tr;
        backWord.classList.remove('translating');
        applySatz(w, null);
        return;
    }

    setBackWord(w);
    const seq = ++translateSeq;
    const tgt = targetLang;
    translateOne(w.de, tgt).then(out => {
        if (seq !== translateSeq) return;
        if (out) {
            backWord.textContent = out;
            backWord.classList.remove('translating');
        } else {
            backWord.textContent = w.tr;
            backWord.classList.remove('translating');
        }
    });
    applySatz(w, null);
    if (sentencesOn && w.sd) {
        translateOne(w.sd, tgt).then(out => {
            if (seq !== translateSeq) return;
            applySatz(w, out);
        });
    }
}

function go(step) {
    if (deck.length === 0) return;
    idx = (idx + step + deck.length) % deck.length;
    render();
}

function shuffleDeck(arr) {
    for (let i = arr.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [arr[i], arr[j]] = [arr[j], arr[i]];
    }
    return arr;
}

card.addEventListener('click', () => {
    if (deck.length > 0) card.classList.toggle('flipped');
});
card.addEventListener('keydown', e => {
    if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        if (deck.length > 0) card.classList.toggle('flipped');
    }
});

document.getElementById('prev').addEventListener('click', () => go(-1));
document.getElementById('next').addEventListener('click', () => go(1));

document.getElementById('shuffle').addEventListener('click', () => {
    shuffleDeck(deck);
    idx = 0;
    render();
});

satzBtn.addEventListener('click', () => {
    sentencesOn = !sentencesOn;
    localStorage.setItem('satz', sentencesOn ? 'on' : 'off');
    satzBtn.classList.toggle('active', sentencesOn);
    satzBtn.setAttribute('aria-pressed', String(sentencesOn));
    if (deck.length > 0) applySatz(deck[idx]);
});

document.getElementById('category').addEventListener('change', e => {
    const key = e.target.value;
    deck = key === '' ? WORDS.slice() : WORDS.filter(w => w.cat === CAT_LABELS[key]);
    idx = 0;
    render();
});

langSel.addEventListener('change', e => {
    targetLang = e.target.value;
    if (deck.length > 0) render();
});

document.addEventListener('keydown', e => {
    if (e.key === 'ArrowLeft') go(-1);
    if (e.key === 'ArrowRight') go(1);
});

render();
</script>
</body>
</html>
