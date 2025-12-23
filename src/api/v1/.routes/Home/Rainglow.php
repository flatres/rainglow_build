<?php
namespace Home;
use Psr\Container\ContainerInterface as Container;
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

class Rainglow
{
    /** @var \Slim\Container
     */

    protected $container;

    public function __construct(Container $container)
    {
       $this->sql= $container->get('mysql');
    }

    public function weatherGet($request, $response, $args)
    {

      // $lat = isset($_GET['lat']) ? (float)$_GET['lat'] : 0.0;
      // $lon = isset($_GET['lon']) ? (float)$_GET['lon'] : 0.0;

      if (isset($args['id'])) {}

      $lat = 51.42;
      $lon = -1.73;

      $maxAge = null;
      $includeMeta = 0;

      if ($lat === 0.0 && $lon === 0.0) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing or invalid lat/lon'], JSON_UNESCAPED_SLASHES);
        exit;
      }

      $data = $this->sql->query(
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
        http_response_code(404);
        echo json_encode(['error' => 'No cached weather found'], JSON_UNESCAPED_SLASHES);
        exit;
      }
      $row = $data[0];

      if ($maxAge !== null) {
        $age = time() - (int)$row['fetched_at_unix'];
        if ($age > $maxAge) {
          http_response_code(404);
          echo json_encode([
            'error' => 'Cached weather too old',
            'age_seconds' => $age
          ], JSON_UNESCAPED_SLASHES);
          exit;
        }
      }

      // om_json is returned by MySQL as a string in many PDO configs; decode/echo cleanly.
      $om = $row['om_json'];
      if (is_string($om)) {
        $decoded = json_decode($om, true);
        $om = is_array($decoded) ? $decoded : $om;
      }

      if ($includeMeta) {
        $d = json_encode([
          'id' => (int)$row['id'],
          'fetched_at' => $row['fetched_at'],
          'fetched_at_unix' => (int)$row['fetched_at_unix'],
          'source' => $row['source'],
          'data' => $om
        ], JSON_UNESCAPED_SLASHES);
        return emit($response, $d);
      } else {
        // Serve exactly the Open-Meteo-shaped JSON to the device:
        if (is_array($om)) {
          return emit($response, $om);
          // echo json_encode($om, JSON_UNESCAPED_SLASHES);
        } else {
          // If something odd happened, still return raw
          return emit($response, $om);
        }
      }
    }

    public function stateGet($request, $response, $args)
    {
      $data = $this->sql->query('Select * from rainglow_data ORDER BY created_at DESC LIMIT 1', [])[0] ?? [];
      return emit($response, $data);
    }


    // call made by the curtain closer. Include current state.
    public function statePost($request, $response, $args)
    {

      $data = $request->getParsedBody();

        // Basic fields
      $lat = isset($data['lat']) ? (float)$data['lat'] : 0.0;
      $lng = isset($data['lon']) ? (float)$data['lon'] : 0.0; // maps to column `lng`

      $tempC = isset($data['center']['tempC']) ? (float)$data['center']['tempC'] : 0.0;
      $r_c   = isset($data['center']['color']['r']) ? (int)$data['center']['color']['r'] : 0;
      $g_c   = isset($data['center']['color']['g']) ? (int)$data['center']['color']['g'] : 0;
      $b_c   = isset($data['center']['color']['b']) ? (int)$data['center']['color']['b'] : 0;

      $segments = isset($data['segments']) && is_array($data['segments'])
          ? $data['segments']
          : [];

      // Column list in order
      $fields = [
          'lat', 'lng', 'temp_c', 'r_c', 'g_c', 'b_c'
      ];

      // For each of 6 segments, append the group of columns
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

      // Values in the same order
      $values = [
          $lat,
          $lng,
          $tempC,
          $r_c,
          $g_c,
          $b_c
      ];

      // Helper: segment by index
      $segmentByIndex = [];
      foreach ($segments as $seg) {
          if (isset($seg['index'])) {
              $idx = (int)$seg['index'];
              if ($idx >= 0 && $idx < 6) {
                  $segmentByIndex[$idx] = $seg;
              }
          }
      }

      // Fill segment data
      for ($i = 0; $i < 6; $i++) {
          $seg = isset($segmentByIndex[$i]) ? $segmentByIndex[$i] : [];

          $dayOffset = isset($seg['dayOffset']) ? (int)$seg['dayOffset'] : 0;
          $probRain  = isset($seg['probRain'])  ? (float)$seg['probRain'] : 0.0;
          $rainMM    = isset($seg['rainMm'])    ? (float)$seg['rainMm']   : 0.0;

          // Now stored as varchar(11) – keep the original strings
          $timeStart = isset($seg['timeStart']) ? (string)$seg['timeStart'] : '';
          $timeEnd   = isset($seg['timeEnd'])   ? (string)$seg['timeEnd']   : '';

          $r = isset($seg['color']['r']) ? (int)$seg['color']['r'] : 0;
          $g = isset($seg['color']['g']) ? (int)$seg['color']['g'] : 0;
          $b = isset($seg['color']['b']) ? (int)$seg['color']['b'] : 0;

          $values[] = $dayOffset;
          $values[] = $probRain;
          $values[] = $rainMM;
          $values[] = $timeStart;
          $values[] = $timeEnd;
          $values[] = $r;
          $values[] = $g;
          $values[] = $b;
      }

      // Build SQL
      $columnsSql   = '`' . implode('`, `', $fields) . '`';
      $placeholders = implode(', ', array_fill(0, count($fields), '?'));

      $this->sql->insert('rainglow_data', $columnsSql, $values);
      return emit($response, true);
      // $sql = "INSERT INTO `rainglow_data` ($columnsSql) VALUES ($placeholders)";

      }

}

 ?>
