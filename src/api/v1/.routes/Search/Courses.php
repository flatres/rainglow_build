<?php
namespace Search;
use Psr\Container\ContainerInterface as Container;
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

if (!defined('FILESTORE_URL')) define('FILESTORE_URL', $_ENV["FILESTORE_URL"]);
if (!defined('SITE_URL')) define('SITE_URL', $_ENV["SITE_URL"]);

define('STRIPE_SECRET_KEY', $_ENV["STRIPE_SECRET_KEY"]);
define('STRIPE_ENDPOINT_SECRET', $_ENV["STRIPE_ENDPOINT_SECRET"]);

\Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

class Courses
{
    /** @var \Slim\Container
     */
    protected $container;

    public function __construct(Container $container)
    {
       $this->sql= $container->get('mysql');
       $this->container = $container;
    }

    public function courseGet($request, $response, $args)
    {
      $flagRoot = SITE_URL . '/api/v1/public/flags/w80/';

      $courseId = $args['id'];

      $course = $this->sql->single(
        'courses',
        'id, providerId, isOnline, DATE_FORMAT(startDate, "%m-%d-%Y") startDateFormatted, duration, durationUnitId, location, countryId, cost',
        'id=?',
        [$courseId]);

      $providerId = $course['providerId'];

      $course['durationName'] = $this->sql->single('courses_durations', 'name', 'id=?', [$course['durationUnitId']])['name'] ?? '';

      $course['categories'] = $this->sql->query(
        'SELECT cc.categoryId AS id, c.name AS name
        FROM categories_courses cc
        JOIN categories c ON cc.categoryId = c.id
        WHERE cc.courseId = ?',
        [$courseId]
      );

      $course['profiles'] = $this->sql->select('courses_profiles', '*', 'courseId=?', [$courseId]);

      $profile = $this->sql->single('providers', '*', 'id=?', [$providerId]);

      $profile['logoImg'] = FILESTORE_URL . 'images/' . $profile['logoImg'];
      $profile['bannerImg'] = $profile['bannerImg'] ? FILESTORE_URL . 'images/' . $profile['bannerImg'] : null;
      $profile['languages'] = [];

      $courses = new \Provider\Courses($this->container);
      $profile['courses'] = $courses->coursesByProvider($providerId, true);
      foreach($profile['courses'] as &$c) $c['provider'] = $profile['name'];

      $langs = $this->sql->select('providers_profiles', '*', 'providerId=?', [$providerId]);

      foreach ($langs as $l) {
        $locale = $this->sql->single('locales', '*', 'id=?', [$l['localeId']]);
        if (!$locale) continue;
        $locale['tagline'] = $l['tagline'];
        $locale['description'] = $l['description'];
        $profile['languages'][] = $locale;
      }

      $profile['countries'] = $this->sql->query(
        'SELECT cp.countryId AS id, c.name AS name, c.isoCode as code
        FROM countries_providers cp
        JOIN countries c ON cp.countryId = c.id
        WHERE cp.providerId = ?',
        [$providerId]
      );

      foreach($profile['countries'] as &$c) $c['flagUrl'] = $flagRoot . strtolower($c['code']) . '.png';

      $countryId = $profile['countryId'];
      $country = $this->sql->single('countries', 'name, isoCode', 'id=?', [$countryId]);
      $profile['countryName'] = $country['name'] ?? null;
      $profile['countryCode'] = $country['isoCode'] ?? null;
      $profile['flagUrl'] = isset($country['isoCode']) ? $flagRoot . strtolower($country['isoCode']) . '.png' : null;

      $profile['photos'] = $this->getProviderPhotos($providerId);
      $profile['video'] = $this->getVideo($providerId);

      $course['provider'] = $profile;

      $course['video'] = $this->sql->single(
          'providers_media',
          'filename',
          'providerId=? AND isVideo=1 AND courseId=?',
          [$providerId, $courseId]
        )['filename'] ??  null;

        if ($course['video']) $course['video'] = FILESTORE_URL . 'videos/' . $course['video'];

      return emit($response, $course);

    }

    public function courseDescriptionGet($request, $response, $args)
    {
      $courseId = $args['id'];
      $desc = $this->sql->single('courses_profiles', 'description', 'courseId=?', [$courseId]) ?? '';
      return emit($response, $desc);

    }

    public function photosGet($request, $response, $args) {
      return emit($response, $this->getProviderPhotos());
    }

    private function getVideo($providerId)
    {
      $video = $this->sql->single(
        'providers_media',
        'filename',
        'providerId=? AND isVideo=1 AND isnull(courseId)',
        [$providerId]
      );

      $filename = $video ? FILESTORE_URL . 'videos/' . $video['filename'] : null;

      return $filename;
    }
     public function providerContactPost($request, $response, $args) {

        global $userId;

        $message = $request->getParsedBody();

        $providerId = $message['providerId'];
        $providerUserId = $this->sql->single('providers', 'userId', 'id=?', [$providerId])['userId'] ?? null;
        $providerEmail = '';
        if ($providerUserId) $providerEmail = $this->sql->single('usr_details', 'email', 'id=?', [$providerUserId])['email'] ?? '';

        $userEmail = $this->sql->single('usr_details', 'email', 'id=?', [$userId])['email'] ?? '';

        $subject = $message['subject'];
        $body = $message['body'];

        $email = new \Utilities\Postmark\Emails\Search\ProviderEmail($providerEmail, $userEmail, $subject, $body);

        return emit($response, [
          'to' => $providerEmail,
          'cc' => $userEmail,
          'subject' => $subject,
          'body' => $body
        ]);
     }

     private function courseSearch($userId, $search, $limit = null, $offset = null) {
      $pageString = '';
      if (!is_null($limit) && !is_null($offset)) $pageString = " LIMIT $limit OFFSET $offset";

      $orderString = '';
      switch ($search['sortField']) {
        case 'duration':
          $orderString = "ORDER BY c.durationUnitId {$search['sortDirection']}, c.duration {$search['sortDirection']}";
          break;
        case 'name':
          $orderString = "ORDER BY provider {$search['sortDirection']}";
          break;
        case 'cost':
        if (strtoupper($search['sortDirection']) === 'ASC') {
          // Treat -1 as "very high" when ascending
          $orderString = "ORDER BY CASE WHEN c.cost = -1 THEN 999999999 ELSE c.cost END ASC";
        } else {
          // Treat -1 as "very low" when descending
          $orderString = "ORDER BY CASE WHEN c.cost = -1 THEN -999999999 ELSE c.cost END DESC";
        }
        break;

        default:
          $orderString = "ORDER BY c.{$search['sortField']} {$search['sortDirection']}";
      }

      if (is_null($offset) || $offset == 0) {
        $this->sql->insert(
          'analytics_searches_courses',
          'countryId, categoryId, userId, search',
          [$search['countryId'], $search['categoryId'], $userId, $search['text']]
          );
      }

      // if set to 0, this means select for any
      $cntComp = $search['countryId'] == 0 ? ">" : "=";
      $catComp = $search['categoryId'] == 0 ? ">" : "=";

      $textLike = "%{$search['text']}%";
      $text = $search['text'];
      $categoryId = $search['categoryId'];
      $countryId = $search['countryId'];

      if (strlen($text)>1) {
        // $whereString = "MATCH(cp.title, cp.description, cp.outcomes) AGAINST (? IN NATURAL LANGUAGE MODE) AND ";
        $textLike = "%{$search['text']}%";
        $whereString = ' AND (
          cp.title LIKE ?
          OR cp.description LIKE ?
          OR cp.outcomes LIKE ?
        )';
        $queryData = [$textLike, $textLike, $textLike, $categoryId, $countryId];
        // $queryData = [$categoryId, $countryId];
      } else {
        $whereString = '';
        $queryData = [$categoryId, $countryId];
      }

      $queryString =
          " SELECT DISTINCT c.id, c.providerId, c.isOnline, c.cost, c.currencyId, DATE_FORMAT(c.startDate, '%d-%m-%y') startDateFormatted, c.startDate, c.duration, c.durationUnitId, c.location, c.countryId, p.logoImg, p.name as provider
            FROM courses c
            LEFT JOIN courses_profiles cp ON c.id = cp.courseId
            LEFT JOIN categories_courses cc ON c.id = cc.courseId
            LEFT JOIN providers p ON c.providerId = p.id
            LEFT JOIN providers_account ap ON c.providerId = ap.providerId
            WHERE cc.categoryId $catComp ?
            AND c.countryId $cntComp ?
            AND c.isPublished = 1
            AND c.startDate > CURDATE()
            AND ap.membershipExpires >= CURDATE() $whereString";

      // print_r($queryString); exit();
      $results = $this->sql->query($queryString . $orderString . $pageString, $queryData);
      $resultsCount = count($this->sql->query($queryString . $orderString, $queryData));

      // fetch media etc
      foreach ($results as &$r) $this->processCourse($r);
      return ['results' => $results, 'count' => $resultsCount];
    }

    public function coursesSearchPost($request, $response, $args) {
      $search = $request->getParsedBody();
      $localeId = 1;

      $userId = null;
      $auth =  $request->getHeader('Authorization')[0] ?? null;
      $auth = str_replace('Bearer ', '', $auth);
      $d= $this->sql->single('usr_sessions', 'user_id', 'token=? AND expired=0', [$auth]);
      if ($d) $userId = $d['user_id'];

      $results = $this->courseSearch($userId, $search);
      return emit($response, $results);
     }

     public function coursesSearchPagePost($request, $response, $args) {

        $search = $request->getParsedBody();
        $localeId = 1;

        $userId = null;
        $auth =  $request->getHeader('Authorization')[0] ?? null;
        $auth = str_replace('Bearer ', '', $auth);
        $d= $this->sql->single('usr_sessions', 'user_id', 'token=? AND expired=0', [$auth]);
        if ($d) $userId = $d['user_id'];

        $offset = ($args['page'] - 1) * $args['limit'];
        $results = $this->courseSearch($userId, $search, $args['limit'], $offset);

        return emit($response, $results);
     }

     public function getCourseById($id) {
        $course = $this->sql->query(
            " SELECT DISTINCT
                c.id,
                c.providerId,
                c.isOnline,
                c.cost,
                c.isLive,
                c.isNew,
                c.isPublished,
                c.currencyId,
                DATE_FORMAT(c.startDate, '%d-%m-%Y') startDateFormatted,
                c.duration,
                c.durationUnitId,
                c.location,
                c.countryId,
                CASE
                    WHEN c.startDate > CURDATE() THEN 1
                    ELSE 0
                END AS isActive,
                p.logoImg,
                p.name as provider,
                ap.membershipExpires
              FROM courses c
              LEFT JOIN courses_profiles cp ON c.id = cp.courseId
              LEFT JOIN categories_courses cc ON c.id = cc.courseId
              LEFT JOIN providers p ON c.providerId = p.id
              LEFT JOIN providers_account ap ON c.providerId = ap.providerId
              WHERE c.id=?
              ",
              [$id]);

        if (isset($course[0])) {
          $c = $course[0];
          $this->processCourse($c);
          return $c;

        } else {
          return null;
        }
     }

     private function processCourse (&$r) {
        $r['logoImg'] =  FILESTORE_URL . 'images/' . $r['logoImg'];
        $profile = $this->sql->single('courses_profiles', 'title, description', 'courseId=?', [$r['id']]);

        $r['title'] = $profile['title'] ?? '';
        $r['description'] = $profile['description'] ?? '';
        $r['mainImg'] = null;
        $media = $this->sql->single('providers_media', 'filename', 'providerId=? AND isMain=1', [$r['providerId']]);
        if ($media) $r['mainImg'] = FILESTORE_URL . 'images/' . $media['filename'];
        $r['categories'] = $this->sql->select('categories_courses', 'categoryId as id', 'courseId=?', [$r['id']]);
     }

    public function durationsGet($request, $response) {
      $durations = $this->sql->select('courses_durations', 'id, name', 'id > ?', [0]);
      foreach ($durations as &$d) {
        $hours = 1;
        if ($d['name'] == 'day') $hours = 24;
        if ($d['name'] == 'week') $hours = 168;
        $d['hours'] = $hours;
      }
      return emit($response, $durations);
    }

    public function categoriesGet($request, $response) {
      $categories = $this->sql->select('categories', 'id, name', 'isActive=?', [1]);
      // $categories = $this->sql->query('
      //   SELECT DISTINCT c.id, c.name
      //   FROM categories c
      //   JOIN categories_courses cp ON c.id = cp.categoryId
      //   JOIN courses cr ON cp.courseId = cr.id
      //   WHERE cr.isLive = 1
      //     AND cr.startDate > CURDATE()
      //     AND c.isActive = 1
      // ', []);

      return emit($response, $categories);
    }

    public function categoriesCoursesCountryGet($request, $response, $args) {
      $countryId = $args['countryId'];
      $categories = $this->sql->select('categories', 'id, name', 'isActive=?', [1]);
      // $categories = $this->sql->query('
      //   SELECT DISTINCT c.id, c.name
      //   FROM categories c
      //   JOIN categories_courses cp ON c.id = cp.categoryId
      //   JOIN courses cr ON cp.courseId = cr.id
      //   WHERE cr.isLive = 1
      //     AND cr.startDate > CURDATE()
      //     AND c.isActive = 1
      //     AND countryId = ?
      // ', [$countryId]);
      $comp = $countryId > 0 ? '=' : '>';

      $categories = $this->sql->query('
        SELECT
            c.id,
            c.name,
            COUNT(cr.id) AS courseCount
        FROM categories c
        JOIN categories_courses cp ON c.id = cp.categoryId
        JOIN courses cr ON cp.courseId = cr.id
        WHERE cr.isPublished = 1
          AND cr.startDate > CURDATE()
          AND c.isActive = 1
          AND cr.countryId ' . $comp . ' ?
        GROUP BY c.id, c.name
      ', [$countryId]);

      foreach ($categories as &$c) $c['name'] = $c['name'] . " [{$c['courseCount']}]";

      return emit($response, $categories);
    }

    private function getProviderPhotos($providerId) {
      $existing = $this->sql->select(
        'providers_media',
        'filename',
        'providerId=? AND isVideo=0 AND isNull(courseId) ORDER BY isMain DESC',
        [$providerId]
      );

      $files = [];
      foreach ($existing as &$e) $files[] = FILESTORE_URL . 'images/' . $e['filename'];
      return $files;
    }

}


 ?>
