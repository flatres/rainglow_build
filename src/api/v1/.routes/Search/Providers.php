<?php
namespace Search;
use Psr\Container\ContainerInterface as Container;
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

if (!defined('FILESTORE_URL')) define('FILESTORE_URL', $_ENV["FILESTORE_URL"]);

if (!defined('SITE_URL')) define('SITE_URL', $_ENV["SITE_URL"]);



class Providers
{
    /** @var \Slim\Container
     */
    protected $container;

    public function __construct(Container $container)
    {
       $this->sql= $container->get('mysql');
       $this->container = $container;
    }

    public function profileGet($request, $response, $args)
    {
      $flagRoot = SITE_URL . '/api/v1/public/flags/w80/';

      $providerId = $args['id'];
      $profile = $this->sql->single('providers', '*', 'id=?', [$providerId]);

      $profile['logoImg'] = $profile['logoImg'] ? FILESTORE_URL . 'images/' . $profile['logoImg'] : null;
      $profile['bannerImg'] = $profile['bannerImg'] ? FILESTORE_URL . 'images/' . $profile['bannerImg'] : null;
      $profile['languages'] = [];
      $langs = $this->sql->select('providers_profiles', '*', 'providerId=?', [$providerId]);
      foreach ($langs as $l) {
        $locale = $this->sql->single('locales', '*', 'id=?', [$l['localeId']]);
        if (!$locale) continue;
        $locale['tagline'] = $l['tagline'];
        $locale['description'] = $l['description'];
        $locale['testimonials'] = $l['testimonials'];
        $profile['languages'][] = $locale;
      }

      // make phone number
      $profile['phoneFull'] = null;
      if ($profile['phonePrefixId'] && strlen($profile['phone'])) {
        $prefix = $this->sql->single('countries', 'phonePrefix', 'id=?', [$profile['phonePrefixId']])['prefix'] ?? null;
        $profile['phoneFull'] = $prefix . $profile['phone'];
      }

      $profile['categories'] = $this->sql->query(
        'SELECT cp.categoryId AS value, c.name AS label
        FROM categories_providers cp
        JOIN categories c ON cp.categoryId = c.id
        WHERE cp.providerId = ? AND c.isPending = 0 AND c.isActive = 1',
        [$providerId]
      );

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
      $profile['countryName'] = $country['name'] ?? '';
      $profile['countryCode'] = $country['isoCode'] ?? null;
      $isoCode = $country['isoCode'] ?? '';
      $profile['flagUrl'] = $flagRoot . strtolower($isoCode) . '.png';

      $profile['photos'] = $this->getProviderPhotos($providerId);
      $profile['video'] = $this->getVideo($providerId);

      $courses = new \Provider\Courses($this->container);
      $profile['courses'] = $courses->coursesByProvider($providerId, true);

      $data = $request->getParsedBody();

      return emit($response, $profile);

    }

    public function photosGet($request, $response, $args) {
      return emit($response, $this->getProviderPhotos());
    }

    private function getVideo($providerId) {

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

     private function providerSearch($userId, $search, $limit = null, $offset = null) {

      $pageString = '';
      if (!is_null($limit) && !is_null($offset)) $pageString = " LIMIT $limit OFFSET $offset";

      switch ($search['sortField']) {
        case 'name':
          $orderString = "ORDER BY name {$search['sortDirection']}";
          break;
        break;

        default:
          $orderString = "ORDER BY c.{$search['sortField']} {$search['sortDirection']}";
      }

      if (is_null($offset) || $offset == 0) {
          $this->sql->insert(
            'analytics_searches_providers',
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

        if (strlen($search['text'])>1) {

          $queryString = "SELECT DISTINCT p.*, DATE_FORMAT(createdAt, '%b %Y') AS memberSince, ap.membershipTypeId
              FROM providers p
              LEFT JOIN providers_profiles pp ON p.id = pp.providerId
              LEFT JOIN categories_providers cp ON p.id = cp.providerId
              LEFT JOIN countries_providers ctp ON p.id = ctp.providerId
              LEFT JOIN providers_account ap ON p.id = ap.providerId
              WHERE (p.name LIKE ?
              OR MATCH(pp.tagline, pp.description) AGAINST (? IN NATURAL LANGUAGE MODE))
              AND cp.categoryId $catComp ?
              -- AND ap.membershipExpires >= CURDATE()
              AND (ctp.countryId $cntComp ? OR p.countryId $cntComp ?)";

          $queryData = [$textLike, $text, $categoryId, $countryId, $countryId];

        } else {
          $queryString = " SELECT DISTINCT p.*, DATE_FORMAT(createdAt, '%b %Y') AS memberSince, ap.membershipTypeId
              FROM providers p
              LEFT JOIN providers_profiles pp ON p.id = pp.providerId
              LEFT JOIN categories_providers cp ON p.id = cp.providerId
              LEFT JOIN countries_providers ctp ON p.id = ctp.providerId
              LEFT JOIN providers_account ap ON p.id = ap.providerId
              WHERE cp.categoryId $catComp ?
              -- AND ap.membershipExpires >= CURDATE()
              AND (ctp.countryId $cntComp ? OR p.countryId $cntComp ?)";

          $queryData = [$categoryId, $countryId, $countryId];
        }
          // echo 'ijijij' . $categoryId .' '. $countryId;
          // $results = $this->sql->query(
          //   'SELECT DISTINCT p.*, COALESCE(pp_with_locale.tagline, pp_with_default_locale.tagline) AS tagline
          //   FROM providers p
          //   JOIN categories_providers cp ON p.id = cp.providerId
          //   JOIN countries_providers co ON p.id = co.providerId
          //   JOIN providers_profiles pp ON p.id = pp.providerId
          //   LEFT JOIN providers_profiles pp_with_locale ON p.id = pp_with_locale.providerId AND pp_with_locale.localeId = ?
          //   LEFT JOIN providers_profiles pp_with_default_locale ON p.id = pp_with_default_locale.providerId AND pp_with_default_locale.localeId = 1
          //   JOIN categories c ON cp.categoryId = c.id
          //   WHERE (cp.categoryId ' . $catComp . ' ?)
          //     AND (co.countryId ' . $cntComp . ' ? OR p.countryId ' . $cntComp . ' ?)',
          //   [
          //     $localeId,
          //     $search['categoryId'],
          //     $search['countryId'],
          //     $search['countryId']
          //   ]
          // );

        // foreach ($results as &$r) {
        //   $r['logoImg'] =  FILESTORE_URL . 'images/' . $r['logoImg'];
        //   $profile = $this->sql->single('courses_profiles', 'title, description', 'courseId=?', [$r['id']]);
        //   $r['title'] = $profile['title'];
        //   $r['description'] = $profile['description'];
        //   $r['mainImg'] = null;
        //   $media = $this->sql->single('providers_media', 'filename', 'providerId=? AND isMain=1', [$r['providerId']]);
        //   if ($media) $r['mainImg'] = FILESTORE_URL . 'images/' . $media['filename'];

        // }
        $results = $this->sql->query("$queryString $orderString $pageString", $queryData);
        $count = count($this->sql->query("$queryString $orderString", $queryData)) ?? 0;

        foreach ($results as &$r) $this->processProvider($r);

        return ['results' => $results, 'count' => $count];
     }

     public function providerSearchPost($request, $response, $args) {

        $search = $request->getParsedBody();
        $localeId = 1;

        $userId = null;
        $auth =  $request->getHeader('Authorization')[0];
        $auth = str_replace('Bearer ', '', $auth);
        $d= $this->sql->single('usr_sessions', 'user_id', 'token=? AND expired=0', [$auth]);
        if ($d) $userId = $d['user_id'];

        $results = $this->providerSearch($userId, $search);
        return emit($response, $results);
     }

     public function providerSearchPagePost($request, $response, $args) {

        $search = $request->getParsedBody();
        $localeId = 1;

        $userId = null;
        $auth =  $request->getHeader('Authorization')[0];
        $auth = str_replace('Bearer ', '', $auth);
        $d= $this->sql->single('usr_sessions', 'user_id', 'token=? AND expired=0', [$auth]);
        if ($d) $userId = $d['user_id'];

        $offset = ($args['page'] - 1) * $args['limit'];

        $results = $this->providerSearch($userId, $search, $args['limit'], $offset);
        return emit($response, $results);
     }

    private function processProvider(&$r) {
      $r['logoImg'] =  FILESTORE_URL . 'images/' . $r['logoImg'];
      $r['categories'] = $this->sql->select('categories_providers', 'categoryId as id', 'providerId=?', [$r['id']]);
      $r['photos'] = $this->getProviderPhotos($r['id']);
      $r['profiles'] = $this->sql->select('providers_profiles', '*', 'providerId=?', [$r['id']]);
      $r['countries'] = $this->sql->select('countries_providers', 'countryId as id', 'providerId=?', [$r['id']]);
      $r['mainImg'] = $this->sql->single('providers_media', 'filename', 'providerId=? AND isMain=1', [$r['id']])['filename'] ?? '';
      $r['mainImg'] = FILESTORE_URL . 'images/' . $r['mainImg'];
    }

    public function getProviderById($id) {
      $provider = $this->sql->query(
        " SELECT p.*
          FROM providers p
          LEFT JOIN providers_account ap ON p.id = ap.providerId
          WHERE p.id=?",
          [$id]);
      // return "x";
      if (isset($provider[0])) {
        $p = $provider[0];
        $this->processProvider($p);
        $p['title'] = $p['name']; //for searching bookmarks
        return $p;

      } else {
        return null;
      }
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
