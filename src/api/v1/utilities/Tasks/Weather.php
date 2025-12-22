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
}
