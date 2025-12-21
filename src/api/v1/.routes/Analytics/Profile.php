<?php
namespace Search;
use Psr\Container\ContainerInterface as Container;
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

define('FILESTORE_URL', $_ENV["FILESTORE_URL"]);
define('SITE_URL', $_ENV["SITE_URL"]);

class Profile
{
    /** @var \Slim\Container
     */
    protected $container;

    public function __construct(Container $container)
    {
       $this->sql= $container->get('mysql');
    }

    public function profileGet($request, $response, $args)
    {
      $flagRoot = SITE_URL . '/api/v1/public/flags/w80/';

      $providerId = $args['id'];
      $profile = $this->sql->single('providers', '*', 'id=?', [$providerId]);

      $profile['logoImg'] = FILESTORE_URL . 'images/' . $profile['logoImg'];
      $profile['languages'] = [];
      $langs = $this->sql->select('providers_profiles', '*', 'providerId=?', [$providerId]);
      foreach ($langs as $l) {
        $locale = $this->sql->single('locales', '*', 'id=?', [$l['localeId']]);
        if (!$locale) continue;
        $locale['tagline'] = $l['tagline'];
        $locale['description'] = $l['description'];
        $profile['languages'][] = $locale;
      }

      $profile['categories'] = $this->sql->query(
        'SELECT cp.categoryId AS value, c.name AS label
        FROM categories_providers cp
        JOIN categories c ON cp.categoryId = c.id
        WHERE cp.providerId = ?',
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
      $profile['countryName'] = $country['name'];
      $profile['countryCode'] = $country['isoCode'];
      $profile['flagUrl'] = $flagRoot . strtolower($country['isoCode']) . '.png';

      $profile['photos'] = $this->getProviderPhotos($providerId);
      $profile['video'] = $this->getVideo($providerId);

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
     public function providerSearchPost($request, $response, $args) {

        $search = $request->getParsedBody();
        $localeId = 1;

        // if set to 0, this means select for any
        $cntComp = $search['countryId'] == 0 ? ">" : "=";
        $catComp = $search['categoryId'] == 0 ? ">" : "=";

        $text = "%{$search['text']}%";
        $categoryId = $search['categoryId'];
        $countryId = $search['countryId'];

        if (strlen($search['text'])>3) {
          $results = $this->sql->query(
            'SELECT DISTINCT p.*, COALESCE(pp_with_locale.tagline, pp_with_default_locale.tagline) AS tagline
              FROM providers p
              JOIN categories_providers cp ON p.id = cp.providerId
              JOIN countries_providers co ON p.id = co.providerId
              LEFT JOIN providers_profiles pp_with_locale ON p.id = pp_with_locale.providerId AND pp_with_locale.localeId = ?
              LEFT JOIN providers_profiles pp_with_default_locale ON p.id = pp_with_default_locale.providerId AND pp_with_default_locale.localeId = 1
              JOIN categories c ON cp.categoryId = c.id
              JOIN countries cc ON co.countryId = cc.id
              WHERE (cp.categoryId '.$catComp.' ? OR LOWER(c.name) LIKE LOWER(?))
                  AND (co.countryId '.$cntComp.' ? OR p.countryId '.$cntComp.' ? OR cc.name LIKE ? OR LOWER(cc.name) LIKE LOWER(?))
                  AND (LOWER(p.name) LIKE LOWER(?)
                      OR LOWER(pp_with_locale.tagline) LIKE LOWER(?)
                      OR LOWER(pp_with_default_locale.tagline) LIKE LOWER(?)
                      OR LOWER(CONCAT(c.name, pp_with_locale.tagline)) LIKE LOWER(?)
                      OR LOWER(CONCAT(pp_with_locale.tagline, c.name)) LIKE LOWER(?)
                      OR LOWER(CONCAT(c.name, pp_with_default_locale.tagline)) LIKE LOWER(?)
                      OR LOWER(CONCAT(pp_with_default_locale.tagline, c.name)) LIKE LOWER(?)
                      OR LOWER(CONCAT(c.name, p.name, pp_with_locale.tagline)) LIKE LOWER(?)
                      OR LOWER(CONCAT(pp_with_locale.tagline, p.name, c.name)) LIKE LOWER(?)
                      OR LOWER(CONCAT(c.name, p.name, pp_with_default_locale.tagline)) LIKE LOWER(?)
                      OR LOWER(CONCAT(pp_with_default_locale.tagline, p.name, c.name)) LIKE LOWER(?))',
              [
                $localeId,
                $categoryId,
                $text,
                $countryId,
                $countryId,
                $text,
                $text,
                $text,
                $text,
                $text,
                $text,
                $text,
                $text,
                $text,
                $text,
                $text,
                $text,
                $text]
          );
        } else {
          $results = $this->sql->query(
            'SELECT DISTINCT p.*, COALESCE(pp_with_locale.tagline, pp_with_default_locale.tagline) AS tagline
            FROM providers p
            JOIN categories_providers cp ON p.id = cp.providerId
            JOIN countries_providers co ON p.id = co.providerId
            JOIN providers_profiles pp ON p.id = pp.providerId
            LEFT JOIN providers_profiles pp_with_locale ON p.id = pp_with_locale.providerId AND pp_with_locale.localeId = ?
            LEFT JOIN providers_profiles pp_with_default_locale ON p.id = pp_with_default_locale.providerId AND pp_with_default_locale.localeId = 1
            JOIN categories c ON cp.categoryId = c.id
            WHERE (cp.categoryId ' . $catComp . ' ?)
              AND (co.countryId ' . $cntComp . ' ? OR p.countryId ' . $cntComp . ' ?)',
            [
              $localeId,
              $search['categoryId'],
              $search['countryId'],
              $search['countryId']
            ]
          );

        }
        foreach ($results as &$r) $r['logoImg'] =  FILESTORE_URL . 'images/' . $r['logoImg'];
        return emit($response, $results);
     }

     public function providerSearchPostSimp($request, $response, $args) {

        $search = $request->getParsedBody();
        $localeId = 1;

        // if set to 0, this means select for any
        $cntComp = $search['countryId'] == 0 ? ">" : "=";
        $catComp = $search['categoryId'] == 0 ? ">" : "=";

        $results = $this->sql->query(
          'SELECT DISTINCT p.*, COALESCE(pp_with_locale.tagline, pp_with_default_locale.tagline) AS tagline
          FROM providers p
          JOIN categories_providers cp ON p.id = cp.providerId
          JOIN countries_providers co ON p.id = co.providerId
          JOIN providers_profiles pp ON p.id = pp.providerId
          LEFT JOIN providers_profiles pp_with_locale ON p.id = pp_with_locale.providerId AND pp_with_locale.localeId = ?
          LEFT JOIN providers_profiles pp_with_default_locale ON p.id = pp_with_default_locale.providerId AND pp_with_default_locale.localeId = 1
          JOIN categories c ON cp.categoryId = c.id
          WHERE (cp.categoryId ' . $catComp . ' ? OR c.name LIKE ?)
            AND (co.countryId ' . $cntComp . ' ? OR p.countryId ' . $cntComp . ' ?)
            AND (LOWER(p.name) LIKE LOWER(?)
              OR LOWER(pp_with_locale.tagline) LIKE LOWER(?)
              OR LOWER(pp_with_default_locale.tagline) LIKE LOWER(?))',
          [
            $localeId,
            $search['categoryId'],
            "%{$search['text']}%",
            $search['countryId'],
            $search['countryId'],
            "%{$search['text']}%",
            "%{$search['text']}%",
            "%{$search['text']}%"
          ]
        );
        foreach ($results as &$r) $r['logoImg'] =  FILESTORE_URL . 'images/' . $r['logoImg'];
        return emit($response, $results);
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
