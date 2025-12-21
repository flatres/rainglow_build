<?php
namespace Provider;
use Psr\Container\ContainerInterface as Container;
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

if (!defined('FILESTORE_URL')) define('FILESTORE_URL', $_ENV["FILESTORE_URL"]);
if (!defined('SITE_URL')) define('SITE_URL', $_ENV["SITE_URL"]);

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

     public function courseNewPost($request, $response, $args)
    {
      $data = (object)$request->getParsedBody();
      $title = $data->title;
      $providerId = $this->provider();

      //get provider email
      $userId = $this->sql->single('providers', 'userId', 'id=?', [$providerId])['userId'];
      $email = $this->sql->single('usr_details', 'email', 'id=?', [$userId])['email'];

      $today = new \DateTime();
      // Add one month to the current date
      $today->modify('+1 month');

      // Format the date as YYYY-DD-MM
      $startDate = $today->format('Y-m-d');
      $currencyId = $this->sql->single('currencies', 'id', 'isDefault=?', [1])['id'] ?? '1';
      $cId = $this->sql->insert('courses', 'providerId, isNew, email, duration, durationUnitId, cost, startDate, currencyId', [$providerId, 1, $email, 1, 2, 0, $startDate, $currencyId]);

      $data->id = (int)$cId;
      $this->sql->insert('courses_profiles', 'providerId, courseId, title, localeId, description, outcomes', [$providerId, $cId, $title, 1, '', '']);

      $data->courses = $this->coursesByProvider($providerId);

      return emit($response, $data);

    }


    public function coursePost($request, $response, $args)
    {
      $course = (object)$request->getParsedBody();
      $sql = $this->sql;
      $providerId = $this->provider();

      $cId = $course->id ? $course->id : $sql->insert('courses', 'providerId', [$providerId]);

      $sql->update(
        'courses',
        'providerId=?, isOnline=?, startDate=?, duration=?, durationUnitId=?, location=?, countryId=?, cost=?, currencyId=?, email=?, isPublished=?',
        'id=?',
        [
          $providerId,
          $course->isOnline,
          mysqlDate($course->startDate),
          $course->duration,
          $course->durationUnitId,
          $course->location,
          $course->countryId,
          $course->cost,
          $course->currencyId,
          $course->email,
          $course->isPublished,
          $cId
        ]
      );

      //languages
      $current = $this->sql->select('courses_profiles', 'localeId', 'courseId=?', [$cId]);
      $languages = [];
      foreach ($current as $c) $languages['l' . $c['localeId']] = $c;
      foreach ($course->languages as $l) {
        $l = (object)$l;
        $languages['l' . $l->id]['exits'] = true;
        $languages['l' . $l->id]['localeId'] = $l->id;

        $check = $sql->single('courses_profiles', 'id', 'courseId=? AND localeId=?', [$cId, $l->id]);
        if ($check) {
          $sql->update(
            'courses_profiles',
            'title=?, description=?, outcomes=?',
            'courseId=? AND localeId=?',
            [$l->title, $l->description, $l->outcomes, $cId, $l->id]
          );
        } else {
          $sql->insert(
            'courses_profiles',
            'title, description, courseId, localeId, providerId, outcomes',
            [$l->title, $l->description, $cId, $l->id, $providerId, $l->outcomes]
          );
        }
      }

      //categories
      $current = $this->sql->select('categories_courses', 'categoryId', 'courseId=?', [$cId]);
      $categories = [];
      foreach ($current as $c) $categories['c' . $c['categoryId']] = $c;
      foreach ($course->categories as $c) {
        $c = (object)$c;
        $categories['c' . $c->value]['exists'] = true;
        $categories['c' . $c->value]['categoryId'] = $c->value;

        $check = $sql->single('categories_courses', 'id', 'courseId=? AND categoryId=?', [$cId, $c->value]);
        if (!$check) {
          $sql->insert(
            'categories_courses',
            'courseId, categoryId',
            [$cId, $c->value]
          );
        }
      }
      //delete those that no longer exists
      foreach ($categories as $c) {
        if (!isset($c['exists'])) {
          $this->sql->delete('categories_courses', 'courseId=? AND categoryId=?', [$cId, $c['categoryId']]);
        }
      }

      return emit($response, (int)$cId);
    }

    public function coursePublishPost($request, $response, $args)
    {
      $providerId = $this->provider();

      $cId = $args['id'];

      // spend a token
      $account = $this->sql->single('providers_account', 'tokens', 'providerId=?', [$providerId]);
      if ($account) {
        $this->sql->update('courses', 'isPublished=?, isNew=?', 'id=?', [1, 0, $cId]);
        $newTokens = $account['tokens'] - 1;
        $this->sql->update('providers_account', 'tokens=?', 'providerId=?', [$newTokens, $providerId]);
        return emit($response, (int)$cId);
      }
      return emit($response, false);

    }

    public function durationsGet($request, $response, $args)
    {
      $durations = $this->sql->select('courses_durations', 'id as value, name as label', 'id>0', []);
      return emit($response, $durations);
    }

    public function coursesGet($request, $response, $args)
    {
      $providerId = $this->provider();
      $courses= $this->coursesByProvider($providerId);
      return emit($response, $courses);
    }

    public function coursesByProvider($providerId, $currentOnly = false) {

      if ($currentOnly) {
        $courses = $this->sql->select(
          'courses',
          'id, isOnline, startDate, duration, durationUnitId, location, countryId, isNew, isPublished, email, cost, currencyId, DATE_FORMAT(startDate, "%m-%d-%y") startDateFormatted',
          'providerId=? AND startDate > CURDATE() AND isPublished=1 ORDER BY startDate ASC',
          [$providerId]
        );
      } else {
        $courses = $this->sql->select(
        'courses',
        'id, isOnline, startDate, duration, durationUnitId, location, countryId, isNew, isPublished, email, cost, currencyId, DATE_FORMAT(startDate, "%m-%d-%y") startDateFormatted',
        'providerId=? ORDER BY startDate ASC',
        [$providerId]
      );
      }
      $coursesObj = new \Search\Courses($this->container);
      foreach($courses as &$c) {
        $c = $coursesObj->getCourseById($c['id']);
        // $c['durationUnit'] = $this->sql->single('courses_durations', 'id as value, name as label', 'id=?', [$c['durationUnitId']]);

        // // $date = $c['startDate'];
        // // $dt = new \DateTime($date);

        // // // Get day with ordinal suffix (18th, 1st, 2nd, etc.)
        // // $day = $dt->format('j');
        // // $suffix = date('S', mktime(0, 0, 0, 1, $day)); // st, nd, rd, th

        // // // Format as "18th June, 2024"
        // // $c['startDateFormatted'] = $day . $suffix . " " . $dt->format("F, Y");

        // // toDate($c['startDate']);

        // $c['languages'] = [];
        // $c['isActive'] = strtotime($c['startDate']) > time() || !$c['startDate'] ? true : false;
        // $langs = $this->sql->select('courses_profiles', '*', 'courseId=?', [$c['id']]);
        // foreach ($langs as $l) {
        //   $locale = $this->sql->single('locales', '*', 'id=?', [$l['localeId']]);
        //   if (!$locale) continue;
        //   $locale['title'] = $l['title'];
        //   $locale['description'] = $l['description'];
        //   $c['languages'][] = $locale;
        // }
        // $c['categories'] = $this->sql->query(
        //   'SELECT cc.categoryId AS value, c.name AS label
        //   FROM categories_courses cc
        //   JOIN categories c ON cc.categoryId = c.id
        //   WHERE cc.courseId = ?',
        //   [$c['id']]
        // );

        // $c['video'] = $this->sql->single(
        //   'providers_media',
        //   'filename',
        //   'providerId=? AND isVideo=1 AND courseId=?',
        //   [$providerId, $c['id']]
        // )['filename'] ??  null;

        // if ($c['video']) $c['video'] = FILESTORE_URL . 'videos/' . $c['video'];

        // $profile = $this->sql->single('courses_profiles', 'title, description, outcomes', 'courseId=?', [$c['id']]);
        // $c['title'] = $profile['title'] ?? '';
        // $c['description'] = $profile['description'] ?? '';
      }
      return $courses;
    }

    public function courseGet($request, $response, $args)
    {
      $providerId = $this->provider();
      $courseId = $args['id'];
      // $coursesObj = new \Search\Courses($this->container);
      // $c = $coursesObj->getCourseById($courseId);
      $c = $this->sql->query(
        'SELECT
        id, isOnline, startDate, duration, durationUnitId, location, countryId, cost, currencyId, email, isPublished, isNew,
        CASE
            WHEN startDate > CURDATE() THEN 1
            ELSE 0
        END AS isActive
        FROM courses
        WHERE providerId=? AND id = ?
        ORDER BY startDate ASC',
        [$providerId, $courseId]
      );
      if (!$c) return emit($response, false);
      $c = $c[0];

      $c['durationUnit'] = $this->sql->single('courses_durations', 'id as value, name as label', 'id=?', [$c['durationUnitId']]);
      // toDate($c['startDate']);
      $c['languages'] = [];
      $langs = $this->sql->select('courses_profiles', '*', 'courseId=?', [$c['id']]);
      foreach ($langs as $l) {
        $locale = $this->sql->single('locales', '*', 'id=?', [$l['localeId']]);
        if (!$locale) continue;
        $locale['title'] = $l['title'];
        $locale['description'] = $l['description'];
        $locale['outcomes'] = $l['outcomes'];
        $c['languages'][] = $locale;
      }
      $c['categories'] = $this->sql->query(
        'SELECT cc.categoryId AS value, c.name AS label
        FROM categories_courses cc
        JOIN categories c ON cc.categoryId = c.id
        WHERE cc.courseId = ?',
        [$c['id']]
      );

      $c['video'] = $this->sql->single(
        'providers_media',
        'filename',
        'providerId=? AND isVideo=1 AND courseId=?',
        [$providerId, $c['id']]
      )['filename'] ??  null;

      if ($c['video']) $c['video'] = FILESTORE_URL . 'videos/' . $c['video'];

      return emit($response, $c);
    }

    public function currenciesGet($request, $response, $args)
    {
      $c = $this->sql->select(
        'currencies',
        '*',
        'id>0',
        []
      );
      return emit($response, $c);
    }

    public function courseDelete($request, $response, $args)
    {
      $providerId = $this->provider();
      $id = $args['id'];
      $this->sql->delete(
        'courses',
        'providerId=? AND id=?',
        [$providerId, $id]
      );
      return emit($response, $id);
    }

    public function courseCopy($request, $response, $args)
    {
      $providerId = $this->provider();
      $id = $args['id'];

      $c = $this->sql->single(
        'courses',
        'providerId, isOnline, duration, durationUnitId, location, countryId, cost, currencyId, email',
        'providerId=? AND id=?',
        [$providerId, $id]
      );

      if ($c) {
        $values = array_values($c);
        $values[] = 1;
        $newId = $this->sql->insert(
          'courses',
          'providerId, isOnline, duration, durationUnitId, location, countryId, cost, currencyId, email, isNew',
          $values
        );
        $profiles = $this->sql->select(
          'courses_profiles',
          'providerId, localeId, title, description, outcomes',
          'providerId=? AND courseId=?',
          [$providerId, $id]
        );

        foreach($profiles as $p) {
          $values = array_values($p);
          $values[] = $newId;
          $this->sql->insert(
            'courses_profiles',
            'providerId, localeId, title, description, outcomes, courseId',
            $values
          );
        }

        $categories = $this->sql->select('categories_courses', 'categoryId', 'courseId=?', [$id]);
        foreach ($categories as $cat) {
          $this->sql->insert('categories_courses', 'categoryId, courseId', [$cat['categoryId'], $newId]);
        }
      }
      $c['id'] = $newId;
      return emit($response, $c);
    }

    private function provider()
    {
      global $userId;
      $provider = $this->sql->single('providers', 'id', 'userId=?', [$userId]);
      if (!$provider) exit();
      return $provider['id'];
    }

    public function videoPost($request, $response, $args)
    {
      $uploadedFile = $request->getUploadedFiles();
      $providerId = $this->provider();
      $courseId = $args['courseId'];

      $video = $this->sql->single(
        'providers_media',
        'filename',
        'providerId=? AND isVideo=1 AND courseId=?',
        [$providerId, $courseId]
      );

      if ($video) {
        $filename = $video['filename'];

        //see if there are other courses using the same video. Could happen if a course if copied.
        $others = $this->sql->select(
          'providers_media',
          'id',
          'filename = ?',
          [$filename]
        );
        $isOthers = count($others) > 1;
        if (!$isOthers) {
          $directory = FILESTORE_PATH . "videos/";
          $filePath = $directory . $filename;
          if (file_exists($filePath)) {
            if (unlink($filePath)) {
              $this->sql->delete(
                  'providers_media',
                  'providerId=? AND filename = ? AND courseId=?',
                  [$providerId, $filename, $courseId]
              );
            };
          }
        } else {
          //just unlink this course
          $this->sql->delete(
            'providers_media',
            'providerId=? AND filename = ? AND courseId=?',
            [$providerId, $filename, $courseId]
          );
        }
      }
      $directory = FILESTORE_PATH . "videos/";
      $filename = moveUploadedFile($directory, $uploadedFile['file'], $providerId);

      $this->sql->insert(
        'providers_media',
        'providerId, isVideo, filename, isMain, courseId',
        [$providerId, 1, $filename, 1, $courseId]
      );

      return emit($response, FILESTORE_URL . 'videos/' . $filename);
    }

}

 ?>
