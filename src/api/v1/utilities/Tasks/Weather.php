<?php
require __DIR__ . '/../../../vendor/autoload.php';



date_default_timezone_set('Europe/London');

$dotenv = \Dotenv\Dotenv::createImmutable(dirname(__FILE__). '/../../');
$dotenv->load();

define('OWM_KEY', $_ENV["OWM_KEY"]);

$timestamp = date("Y-m-d H:i:s", time());

$sql = new \Dependency\Databases\Rainglow();
echo 'running';

const OWM_API_KEY = OWM_KEY;

const DEFAULT_HOURS = 48;          // you can keep 24 if you prefer
const DEFAULT_TIMEFORMAT = 'unixtime';
const REFRESH_THRESHOLD_SECONDS = 15 * 60; // 15 minutes

// If you fetch one fixed location, set these constants and ignore CLI args.
const DEFAULT_LAT = 51.42;
const DEFAULT_LON = -1.73;

//
// Allow overriding via CLI:
// php cron_fetch_weather.php 51.42 -1.73
//
$lat = DEFAULT_LAT;
$lon = DEFAULT_LON;

if (PHP_SAPI === 'cli' && isset($argv) && count($argv) >= 3) {
  $lat = (float)$argv[1];
  $lon = (float)$argv[2];
}


// Decide whether to refresh
$latest = getLatestWeatherRow($sql, $lat, $lon);
$shouldFetch = true;

if ($latest && isset($latest['fetched_at_unix'])) {
  $age = time() - (int)$latest['fetched_at_unix'];
  if ($age < REFRESH_THRESHOLD_SECONDS) {
    $shouldFetch = false;
  }
}

if (!$shouldFetch) {
  // Nothing to do.
  echo 'nothing to do';
  exit(0);
}

// Fetch and save
try {
  $result = fetchWeatherOpenMeteoFirstThenOWM($lat, $lon, DEFAULT_HOURS, DEFAULT_TIMEFORMAT);
  saveWeatherRow($sql, $result['source'], $lat, $lon, $result['open_meteo_json'], $result['upstream_json']);
  echo 'saved';
  writeExpectedStateToRainglowData($sql, $lat, $lon);
  echo 'state saved';
  exit(0);
} catch (Throwable $e) {
  fwrite(STDERR, "Weather fetch failed: " . $e->getMessage() . PHP_EOL);
  exit(1);
}

//
// FUNCTIONS (reused from your earlier endpoint)
//

function getLatestWeatherRow($sql, float $lat, float $lon): ?array
{
  $data = $sql->query(
    "SELECT id,
            fetched_at,
            UNIX_TIMESTAMP(fetched_at) AS fetched_at_unix,
            source
     FROM rainglow_weather
     WHERE latitude = ? AND longitude = ?
     ORDER BY fetched_at DESC
     LIMIT 1",
     [$lat, $lon]
  );
  if (!isset($data[0]))return null;
  return $data[0] ?: null;
}

function fetchWeatherOpenMeteoFirstThenOWM(float $lat, float $lon, int $hours, string $timeformat): array
{
  $omUrl = buildOpenMeteoUrl($lat, $lon, $hours, $timeformat);
  $omResp = httpGetJson($omUrl, 8);

  if (is_array($omResp) && isset($omResp['hourly'], $omResp['hourly']['temperature_2m'])) {
    return [
      'source' => 'open-meteo',
      'open_meteo_json' => $omResp,
      'upstream_json' => null
    ];
  }

  $owmUrl = buildOWMOneCallUrl($lat, $lon);
  $owmResp = httpGetJson($owmUrl, 8);

  if (!is_array($owmResp) || !isset($owmResp['hourly']) || !is_array($owmResp['hourly'])) {
    throw new RuntimeException('Fallback OpenWeatherMap response missing hourly data');
  }

  $openMeteoShaped = transformOWMToOpenMeteo($lat, $lon, $owmResp, $hours, $timeformat);

  return [
    'source' => 'openweathermap',
    'open_meteo_json' => $openMeteoShaped,
    'upstream_json' => $owmResp
  ];
}

function buildOpenMeteoUrl(float $lat, float $lon, int $hours, string $timeformat): string
{
  $forecastDays = (int)ceil($hours / 24.0);
  $forecastDays = max(1, min(7, $forecastDays));

  $params = [
    'latitude' => $lat,
    'longitude' => $lon,
    'hourly' => 'temperature_2m,precipitation,precipitation_probability',
    'forecast_days' => $forecastDays,
    'timezone' => 'auto',
  ];

  if ($timeformat === 'unixtime') {
    $params['timeformat'] = 'unixtime';
  }

  return 'https://api.open-meteo.com/v1/forecast?' . http_build_query($params);
}

function buildOWMOneCallUrl(float $lat, float $lon): string
{
  $params = [
    'lat' => $lat,
    'lon' => $lon,
    'appid' => OWM_API_KEY,
    'units' => 'metric',
    'exclude' => 'minutely,alerts,daily'
  ];

  return 'https://api.openweathermap.org/data/3.0/onecall?' . http_build_query($params);
}

function httpGetJson(string $url, int $timeoutSeconds = 8): ?array
{
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_CONNECTTIMEOUT => $timeoutSeconds,
    CURLOPT_TIMEOUT => $timeoutSeconds,
    CURLOPT_HTTPHEADER => ['Accept: application/json'],
  ]);

  $body = curl_exec($ch);
  $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
  curl_close($ch);

  if ($body === false || $code < 200 || $code >= 300) {
    return null;
  }

  $json = json_decode($body, true);
  return is_array($json) ? $json : null;
}

function transformOWMToOpenMeteo(float $lat, float $lon, array $owm, int $hours, string $timeformat): array
{
  $tzOffset = isset($owm['timezone_offset']) ? (int)$owm['timezone_offset'] : 0;
  $tzName = isset($owm['timezone']) ? (string)$owm['timezone'] : 'UTC';

  $hourly = $owm['hourly'];
  $n = min($hours, count($hourly));

  $timeArr = [];
  $tempArr = [];
  $precipArr = [];
  $probArr = [];

  for ($i = 0; $i < $n; $i++) {
    $h = $hourly[$i];
    $dtUtc = isset($h['dt']) ? (int)$h['dt'] : 0;
    $dtLocal = $dtUtc + $tzOffset;

    if ($timeformat === 'unixtime') {
      $timeArr[] = $dtLocal;
    } else {
      $timeArr[] = gmdate('Y-m-d\TH:i:s', $dtLocal) . offsetToIsoSuffix($tzOffset);
    }

    $tempArr[] = isset($h['temp']) ? (float)$h['temp'] : 0.0;

    $rainMm = 0.0;
    if (isset($h['rain']['1h'])) {
      $rainMm = (float)$h['rain']['1h'];
    } elseif (isset($h['snow']['1h'])) {
      $rainMm = (float)$h['snow']['1h'];
    }
    $precipArr[] = $rainMm;

    $pop = isset($h['pop']) ? (float)$h['pop'] : 0.0;
    $probArr[] = (int)round(max(0.0, min(1.0, $pop)) * 100.0);
  }

  return [
    'latitude' => $lat,
    'longitude' => $lon,
    'generationtime_ms' => 0.0,
    'utc_offset_seconds' => $tzOffset,
    'timezone' => $tzName,
    'timezone_abbreviation' => '',
    'hourly_units' => [
      'time' => ($timeformat === 'unixtime') ? 'unixtime' : 'iso8601',
      'temperature_2m' => '°C',
      'precipitation' => 'mm',
      'precipitation_probability' => '%',
    ],
    'hourly' => [
      'time' => $timeArr,
      'temperature_2m' => $tempArr,
      'precipitation' => $precipArr,
      'precipitation_probability' => $probArr,
    ],
  ];
}

function offsetToIsoSuffix(int $offsetSeconds): string
{
  if ($offsetSeconds === 0) return 'Z';
  $sign = ($offsetSeconds >= 0) ? '+' : '-';
  $abs = abs($offsetSeconds);
  $hours = intdiv($abs, 3600);
  $mins  = intdiv($abs % 3600, 60);
  return sprintf('%s%02d:%02d', $sign, $hours, $mins);
}

function saveWeatherRow($sql, string $source, float $lat, float $lon, array $openMeteoJson, ?array $upstreamJson): void
{
  $tz = $openMeteoJson['timezone'] ?? null;
  $tzAbbr = $openMeteoJson['timezone_abbreviation'] ?? null;
  $utcOffset = $openMeteoJson['utc_offset_seconds'] ?? null;

  $up = is_null($upstreamJson) ? '{}' : json_encode($upstreamJson, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);


  $data = $sql->query(
    "INSERT INTO rainglow_weather
      (fetched_at, source, latitude, longitude, timezone, timezone_abbreviation, utc_offset_seconds, om_json, upstream_json)
     VALUES
      (NOW(6), ?, ?, ?, ?, ?, ?, ?, ?)",
  [
    $source,
    $lat,
    $lon,
    $tz,
    $tzAbbr,
    is_null($utcOffset) ? null : (int)$utcOffset,
    json_encode($openMeteoJson, JSON_UNESCAPED_SLASHES),
    $up
  ]);

  function writeExpectedStateToRainglowData($sql, float $lat = 51.42, float $lon = -1.73)
  {
    // 1) Load latest cached Open-Meteo JSON
    $data = $sql->query(
      "SELECT id,
              fetched_at,
              UNIX_TIMESTAMP(fetched_at) AS fetched_at_unix,
              source,
              om_json
      FROM rainglow_weather
      WHERE latitude = ? AND longitude = ?
      ORDER BY fetched_at DESC
      LIMIT 1",
      [$lat, $lon]
    );

    if (!isset($data[0])) {
      throw new \RuntimeException('No cached weather found in rainglow_weather.');
    }

    $row = $data[0];
    $om  = $row['om_json'];

    if (is_string($om)) {
      $decoded = json_decode($om, true);
      if (!is_array($decoded)) {
        throw new \RuntimeException('rainglow_weather.om_json is not valid JSON.');
      }
      $om = $decoded;
    }

    // 2) Validate required hourly fields
    if (
      !isset($om['hourly']['time'], $om['hourly']['temperature_2m'], $om['hourly']['precipitation'], $om['hourly']['precipitation_probability'])
      || !is_array($om['hourly']['time'])
    ) {
      throw new \RuntimeException('om_json missing required hourly arrays.');
    }

    $times = $om['hourly']['time']; // ideally local unixtime (Open-Meteo with timeformat=unixtime + timezone=auto)
    $temps = $om['hourly']['temperature_2m'];
    $prec  = $om['hourly']['precipitation'];
    $prob  = $om['hourly']['precipitation_probability'];

    $utcOffset = isset($om['utc_offset_seconds']) ? (int)$om['utc_offset_seconds'] : 0;

    // Build a timestamp->index map for fast lookup
    $timeToIndex = [];
    foreach ($times as $i => $t) {
      $timeToIndex[(string)(int)$t] = (int)$i;
    }

    // 3) Compute local "now" and today's local midnight
    $nowUtc   = time();
    $nowLocal = $nowUtc + $utcOffset;

    $localMidnight   = (int)(floor($nowLocal / 86400) * 86400);
    $secondsIntoDay  = $nowLocal - $localMidnight;
    $currentBlockIdx = (int)floor($secondsIntoDay / (4 * 3600)); // 0..5
    if ($currentBlockIdx < 0) $currentBlockIdx = 0;
    if ($currentBlockIdx > 5) $currentBlockIdx = 5;

    // 4) Center: choose temperature for current hour (nearest <= nowLocal)
    $centerTempC = nearestHourlyTemperatureLocal($times, $temps, $nowLocal);

    // Center colour: temp scale unless "definitely rainy now" (then white alert)
    $centerRgb = centerTempToRgb($centerTempC);

    // 5) Compute segments 0..5
    // Past blocks use tomorrow's equivalent block (dayOffset=1), future/current blocks dayOffset=0.
    $segments = [];
    for ($i = 0; $i < 6; $i++) {

      $dayOffset = ($i < $currentBlockIdx) ? 1 : 0;

      $startTs = $localMidnight + ($dayOffset * 86400) + ($i * 4 * 3600);
      $endTs   = $startTs + (4 * 3600);

      // Pull 4 hourly points for this block, if present
      $idxs = [];
      for ($t = $startTs; $t < $endTs; $t += 3600) {
        $key = (string)$t;
        if (isset($timeToIndex[$key])) {
          $idxs[] = $timeToIndex[$key];
        }
      }

      $maxRainMm = 0.0;
      $maxProb   = 0.0; // keep float as your schema uses float
      if (!empty($idxs)) {
        foreach ($idxs as $k) {
          $mm = isset($prec[$k]) ? (float)$prec[$k] : 0.0;
          $pp = isset($prob[$k]) ? (float)$prob[$k] : 0.0; // Open-Meteo gives % already
          if ($mm > $maxRainMm) $maxRainMm = $mm;
          if ($pp > $maxProb)   $maxProb   = $pp;
        }
      }

      $rgb = segmentToRgb($maxRainMm, $maxProb);

      // timeStart/timeEnd are varchar(11). Use "HH:MM-HH:MM" (11 chars).
      $timeStartStr = hhmmFromLocalTs($startTs) . '-' . hhmmFromLocalTs($endTs - 60);

      $segments[$i] = [
        'dayOffset' => $dayOffset,
        'probRain'  => $maxProb,    // percent 0..100
        'rainMm'    => $maxRainMm,
        'timeStart' => substr($timeStartStr, 0, 5),     // "HH:MM"
        'timeEnd'   => substr($timeStartStr, 6, 5),     // "HH:MM"
        'color'     => $rgb,
      ];
    }

    // "Definitely rainy now" rule (optional): current block maxProb>=80 AND rain>=0.5
    $cur = $segments[$currentBlockIdx] ?? null;
    if ($cur && ((float)$cur['probRain'] >= 80.0) && ((float)$cur['rainMm'] >= 0.5)) {
      $centerRgb = ['r' => 255, 'g' => 255, 'b' => 255];
    }

    // 6) Build column list exactly like your statePost()
    $fields = ['lat', 'lng', 'temp_c', 'r_c', 'g_c', 'b_c'];
    for ($i = 0; $i < 6; $i++) {
      $fields[] = "dayOffset_{$i}";
      $fields[] = "probRain_{$i}";
      $fields[] = "rainMM_{$i}";
      $fields[] = "timeStart_{$i}";
      $fields[] = "timeEnd_{$i}";
      $fields[] = "r_{$i}";
      $fields[] = "g_{$i}";
      $fields[] = "b_{$i}";
    }

    $values = [
      $lat,
      $lon,
      $centerTempC,
      (int)$centerRgb['r'],
      (int)$centerRgb['g'],
      (int)$centerRgb['b'],
    ];

    for ($i = 0; $i < 6; $i++) {
      $seg = $segments[$i] ?? [
        'dayOffset' => 0,
        'probRain'  => 0.0,
        'rainMm'    => 0.0,
        'timeStart' => '',
        'timeEnd'   => '',
        'color'     => ['r' => 0, 'g' => 0, 'b' => 0],
      ];

      $values[] = (int)$seg['dayOffset'];
      $values[] = (float)$seg['probRain'];
      $values[] = (float)$seg['rainMm'];
      $values[] = (string)$seg['timeStart']; // varchar(11) in your comment; these are "HH:MM"
      $values[] = (string)$seg['timeEnd'];   // "HH:MM"
      $values[] = (int)$seg['color']['r'];
      $values[] = (int)$seg['color']['g'];
      $values[] = (int)$seg['color']['b'];
    }

    $columnsSql = '`' . implode('`, `', $fields) . '`';

    // 7) Insert into rainglow_data (using your wrapper)
    $sql->insert('rainglow_data', $columnsSql, $values);

    return true;
  }

  /**
   * Convert local unix timestamp (already offset-adjusted) to "HH:MM" in 24h.
   */
  function hhmmFromLocalTs(int $localTs): string
  {
    $h = (int)gmdate('H', $localTs);
    $m = (int)gmdate('i', $localTs);
    return sprintf('%02d:%02d', $h, $m);
  }

  /**
   * Choose temperature at nearest hourly time <= nowLocal.
   * Assumes $times are local unixtime hours (Open-Meteo with timeformat=unixtime + timezone=auto).
   */
  function nearestHourlyTemperatureLocal(array $times, array $temps, int $nowLocal): float
  {
    $bestIdx = 0;
    foreach ($times as $i => $t) {
      $tt = (int)$t;
      if ($tt <= $nowLocal) $bestIdx = (int)$i;
      else break;
    }
    return isset($temps[$bestIdx]) ? (float)$temps[$bestIdx] : 0.0;
  }

  /**
   * Segment colour logic consistent with your RainGlow description:
   * - If rain < 0.5mm: green brightness based on dryness (100 - probRain)
   * - Else: blue->purple base based on rain intensity, brightness based on probRain
   */
  function segmentToRgb(float $rainMm, float $probRainPct): array
  {
    $probRainPct = max(0.0, min(100.0, $probRainPct));

    if ($rainMm < 0.5) {
      $dry = 100.0 - $probRainPct; // 0..100
      $g = (int)round(scale($dry, 0.0, 100.0, 40.0, 255.0)); // never off
      return ['r' => 0, 'g' => $g, 'b' => 0];
    }

    // intensity factor for colour blend (tune if desired)
    $t = 0.0;
    if ($rainMm <= 2.0)      $t = 0.0; // blue
    else if ($rainMm <= 5.0) $t = 0.5; // mid
    else                     $t = 1.0; // purple

    $blue   = ['r' => 0,   'g' => 80,  'b' => 255];
    $purple = ['r' => 180, 'g' => 0,   'b' => 255];

    $base = [
      'r' => $blue['r'] + ($purple['r'] - $blue['r']) * $t,
      'g' => $blue['g'] + ($purple['g'] - $blue['g']) * $t,
      'b' => $blue['b'] + ($purple['b'] - $blue['b']) * $t,
    ];

    $brightness = $scale($probRainPct, 0.0, 100.0, 60.0, 255.0);

    return [
      'r' => (int)round($base['r'] * ($brightness / 255.0)),
      'g' => (int)round($base['g'] * ($brightness / 255.0)),
      'b' => (int)round($base['b'] * ($brightness / 255.0)),
    ];
  }

  /**
   * Center temperature colour: simple cold->warm ramp.
   * (If you have an existing scale in firmware/UI, swap this to match exactly.)
   */
  function centerTempToRgb(float $tempC): array
  {
    $min = -5.0;
    $max = 30.0;
    $t = ($tempC - $min) / ($max - $min);
    $t = max(0.0, min(1.0, $t));

    $cold = ['r' => 0,   'g' => 120, 'b' => 255];
    $warm = ['r' => 255, 'g' => 60,  'b' => 0];

    return [
      'r' => (int)round($cold['r'] + ($warm['r'] - $cold['r']) * $t),
      'g' => (int)round($cold['g'] + ($warm['g'] - $cold['g']) * $t),
      'b' => (int)round($cold['b'] + ($warm['b'] - $cold['b']) * $t),
    ];
  }

 function scale(float $x, float $inMin, float $inMax, float $outMin, float $outMax): float
  {
    if ($inMax <= $inMin) return $outMin;
    $x = max($inMin, min($inMax, $x));
    $p = ($x - $inMin) / ($inMax - $inMin);
    return $outMin + ($outMax - $outMin) * $p;
  }
}
