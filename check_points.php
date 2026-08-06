<?php
/**
 * שלוחת "בדיקת ניקוד" בימות המשיח (type=api) פונה לכתובת הזו.
 * הסקריפט מוריד בזמן אמת את דוח ה-time_keeper, מסכם את כל המשמרות
 * של הטלפון שמתקשר, ומחזיר נקודה אחת לכל שעת עבודה.
 *
 * אין צורך בקובץ ניקוד נפרד ואין צורך בטריגר מתוזמן -
 * הכל מחושב מחדש בכל שיחה ישירות מהדוח המקורי של ימות.
 *
 * ========= לפני שמעלים לשרת - למלא כאן =========
 */

// מספר המערכת שלכם בימות המשיח + סיסמת הניהול (אותה סיסמה שנכנסים איתה לאתר הניהול)
$YEMOT_TOKEN = '083137230:s927FGtvpLuR8KCgQG8mTw';

// הנתיב לקובץ הדוח האמיתי (מאומת מהמערכת שלכם)
$REPORT_PATH = 'ivr2:2/5/TimeKeeperTotalAll.ymgr';

// כמה נקודות לכל שעת עבודה במשמרת (מעוגל לשעה הקרובה, לכל משמרת בנפרד)
$POINTS_PER_HOUR = 1;

/* ================================================================= */

header('Content-Type: text/plain; charset=utf-8');

function downloadYemotFile($token, $path) {
    $url = 'https://www.call2all.co.il/ym/api/DownloadFile?token=' . urlencode($token) . '&path=' . urlencode($path);
    $context = stream_context_create(['http' => ['timeout' => 15]]);
    return @file_get_contents($url, false, $context);
}

// פענוח פורמט ymgr של ימות המשיח: כל שורה = רשומה אחת,
// שדות מופרדים ב-% ובכל שדה יש מפתח#ערך (למשל EnterId#0533137770)
function parseYmgr($text) {
    $rows = [];
    $lines = preg_split('/\r\n|\r|\n/', $text);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') continue;
        $row = [];
        foreach (explode('%', $line) as $pair) {
            $idx = strpos($pair, '#');
            if ($idx === false) continue;
            $row[substr($pair, 0, $idx)] = substr($pair, $idx + 1);
        }
        if (!empty($row)) $rows[] = $row;
    }
    return $rows;
}

$phone = isset($_GET['ApiPhone']) ? trim($_GET['ApiPhone']) : '';

if ($phone === '') {
    echo 'id_list_message=t-לא זוהה מספר טלפון&go_to_folder=hangup';
    exit;
}

$reportText = downloadYemotFile($YEMOT_TOKEN, $REPORT_PATH);

if ($reportText === false) {
    echo 'id_list_message=t-אירעה שגיאה בקריאת הדוח, נסו שוב מאוחר יותר&go_to_folder=hangup';
    exit;
}

$rows = parseYmgr($reportText);

$points = 0;
foreach ($rows as $row) {
    if (isset($row['EnterId']) && $row['EnterId'] === $phone && isset($row['TotalSeconds'])) {
        $hours = floatval($row['TotalSeconds']) / 3600;
        $points += round($hours) * $POINTS_PER_HOUR;
    }
}

$text = 'הניקוד המצטבר שלך הוא, ' . $points . ', נקודות';
echo 'id_list_message=t-' . $text . '&go_to_folder=hangup';
