<?php
namespace Provider;
use Psr\Container\ContainerInterface as Container;
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

define('FILESTORE_URL', $_ENV["FILESTORE_URL"]);

class Profile
{
    /** @var \Slim\Container
     */
    protected $container;

    public function __construct(Container $container)
    {
       $this->sql= $container->get('mysql');
       $this->container = $container;
    }


    public function idGet($request, $response, $args)
    {
      return emit($response, $this->provider());
    }
    public function profileGet($request, $response, $args)
    {
      global $userId;
      $profile = $this->sql->single('providers', '*', 'userId=?', [$userId]);
      if (!$profile) {
        $id = $this->sql->insert('providers', 'userId', [$userId]);
        $this->sql->insert('providers_profiles', 'providerId, localeId, description', [$id, 1, '']);
        $this->sql->insert('providers_account', 'providerId, membershipTypeId, tokens, membershipExpires', [$id, 1, 0, '2099-12-31']);
      }

      $profile = $this->sql->single('providers', '*', 'userId=?', [$userId]);
      $providerId = $profile['id'];
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

      $profile['categories'] = $this->sql->query(
        'SELECT cp.categoryId AS value, c.name AS label, c.isPending
        FROM categories_providers cp
        JOIN categories c ON cp.categoryId = c.id
        WHERE cp.providerId = ?',
        [$providerId]
      );

      foreach ($profile['categories'] as &$p) {
        if ($p['isPending'] === 1) $p['label'] = $p['label'] . ' (Pending)';
      }

      $profile['countries'] = $this->sql->query(
        'SELECT cp.countryId AS value, c.name AS label
        FROM countries_providers cp
        JOIN countries c ON cp.countryId = c.id
        WHERE cp.providerId = ?',
        [$providerId]
      );
      // $courses = new \Provider\Courses($this->container);
      // $profile['courses'] = $courses->coursesByProvider($providerId);


      $data = $request->getParsedBody();

      return emit($response, $profile);

    }

    public function profilePut($request, $response, $args)
    {
      $data = $request->getParsedBody();
      // var_dump($data); exit();

      $this->sql->update(
        'providers',
        'name=?, homepage=?, hasOnline=?, countryId=?, phonePrefixId=?, phone=?',
        'id=?',
        [$data['name'], $data['homepage'], $data['hasOnline'], $data['countryId'], $data['phonePrefixId'], $data['phone'], $data['id']]
      );

      return emit($response, $data);
    }

    public function categoryPost($request, $response, $args)
    {
      $catId = $args['id'];
      $providerId = $this->provider();
      $this->sql->insert('categories_providers', 'providerId, categoryId', [$providerId, $catId]);

      return emit($response, $catId);
    }

    public function newCategoryPost($request, $response)
    {
      global $userId;
      $data = $request->getParsedBody();
      $name = $data['name'];
      $catId = $this->sql->insert(
        'categories',
        'name, isActive, createdByUserId, isPending',
        [$name, 0, $userId, 1]
      );

      if (isset($data['context'])) {
        if (strtolower($data['context']) == 'providers') {
          $providerId = $this->provider();
          $this->sql->insert(
            'categories_providers',
            'providerId, categoryId',
            [$providerId, $catId]
          );
        }
        if (strtolower($data['context']) == 'courses') {
          if (isset($data['courseId'])) {
            $courseId = $data['courseId'];
            $this->sql->insert(
              'categories_courses',
              'courseId, categoryId',
              [$courseId, $catId]
            );
          }
        }
      }


      return emit($response, true);
    }

    public function categoryDelete($request, $response, $args)
    {
      $catId = $args['id'];
      $providerId = $this->provider();
      $this->sql->delete('categories_providers', 'providerId =? AND categoryId = ?', [$providerId, $catId]);

      //if it is pending, also delete from main table
      $cat = $this->sql->single('categories', 'isPending', 'id=?', [$catId]);
      if ($cat['isPending'] == 1 ) {
        $this->sql->delete('categories', 'id=?', [$catId]);
      }

      return emit($response, $catId);
    }

    public function countryPost($request, $response, $args)
    {

      $cntId = $args['id'];
      $providerId = $this->provider();
      $this->sql->insert('countries_providers', 'providerId, countryId', [$providerId, $cntId]);

      return emit($response, $cntId);

    }

    public function countryDelete($request, $response, $args)
    {
      $cntId = $args['id'];
      $providerId = $this->provider();
      $this->sql->delete('countries_providers', 'providerId =? AND countryId = ?', [$providerId, $cntId]);

      return emit($response, $catId);
    }

    private function provider()
    {
      global $userId;
      $provider = $this->sql->single('providers', 'id', 'userId=?', [$userId]);
      if (!$provider) exit();
      return $provider['id'];
    }

    public function languagePost($request, $response, $args)
    {

      $localeId = $args['id'];
      $providerId = $this->provider();
      $this->sql->insert('providers_profiles', 'providerId, localeId, description, testimonials, tagline', [$providerId, $localeId, '', '', '']);

      return emit($response, $localeId);

    }

    public function languageDelete($request, $response, $args)
    {
      $localeId = $args['id'];
      $providerId = $this->provider();
      $this->sql->delete('providers_profiles', 'providerId =? AND localeId = ?', [$providerId, $localeId]);

      return emit($response, $localeId);
    }

    public function languagePut($request, $response, $args)
    {
      $data = $request->getParsedBody();
      $localeId = $data['id'];
      $desc = $data['description'];
      $tagline = $data['tagline'];
      $testimonials = $data['testimonials'];
      $providerId = $this->provider();
      echo $localeId . ':' . $providerId . PHP_EOL;
      $this->sql->update('providers_profiles', 'description=?, tagline=?, testimonials=?', 'localeId=? AND providerId=?', [$desc, $tagline, $testimonials, $localeId, $providerId]);

      return emit($response, $localeId);

    }

    // https://stackoverflow.com/questions/11511511/how-to-save-a-png-image-server-side-from-a-base64-data-string
    public function logoPost($request, $response, $args) {
      global $userId;
      $image = $request->getParsedBody()['data'];

      if(strlen($image) == 0) {
        return emit($response, []);
      } else {
        $providerId = $this->provider();
        $hash = bin2hex(random_bytes(18));
        $fileName = "{$hash}{$providerId}.png";
        $path = FILESTORE_PATH . 'images/';
        $file = $path . $fileName;

        $data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $image));

        foreach (glob($path . "*" . $hash . "*.png") as $filename) {
          unlink($filename);
        }

        file_put_contents($file, $data);
      }

      $this->sql->update('providers', 'logoImg=?', 'id=?', [$fileName, $providerId]);

      return emit($response, [FILESTORE_URL . 'images/' . $fileName]);
    }

    // https://stackoverflow.com/questions/11511511/how-to-save-a-png-image-server-side-from-a-base64-data-string
    public function bannerPost($request, $response, $args) {
      global $userId;
      $image = $request->getParsedBody()['data'];

      if(strlen($image) == 0) {
        return emit($response, []);
      } else {
        $providerId = $this->provider();
        $hash = bin2hex(random_bytes(18));
        $fileName = "{$hash}{$providerId}.png";
        $path = FILESTORE_PATH . 'images/';
        $file = $path . $fileName;

        $data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $image));

        foreach (glob($path . "*" . $hash . "*.png") as $filename) {
          unlink($filename);
        }

        file_put_contents($file, $data);
      }

      $this->sql->update('providers', 'bannerImg=?', 'id=?', [$fileName, $providerId]);

      return emit($response, [FILESTORE_URL . 'images/' . $fileName]);
    }

    // https://stackoverflow.com/questions/11511511/how-to-save-a-png-image-server-side-from-a-base64-data-string
    public function photoPost($request, $response, $args) {
      $data = $request->getParsedBody();
      $image = $data['data'];

      if(strlen($image) == 0) {
        return emit($response, []);
      } else {
        $providerId = $this->provider();
        $hash = bin2hex(random_bytes(18));
        $fileName = "{$hash}{$providerId}.png";
        $path = FILESTORE_PATH . 'images/';
        $file = $path . $fileName;

        $data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $image));

        foreach (glob($path . "*" . $hash . "*.png") as $filename) {
          unlink($filename);
        }

        file_put_contents($file, $data);
      }

      $existing = $this->sql->select(
        'providers_media',
        'filename',
        'providerId=? AND isVideo=0 AND isNull(courseId) ORDER BY isMain DESC',
        [$providerId]
      );

      $isMain = count($existing) > 0 ? 0 : 1;

      $files = [];
      foreach ($existing as &$e) $files[] = FILESTORE_URL . 'images/' . $e['filename'];

      $this->sql->insert(
        'providers_media',
        'providerId, isVideo, filename, isMain',
        [$providerId, 0, $fileName, $isMain]
      );
      $files[] = FILESTORE_URL . 'images/' . $fileName;
      return emit($response, $files);
    }

    public function photosGet($request, $response, $args) {
      return emit($response, $this->getProviderPhotos());
    }

    public function videoGet($request, $response, $args) {
      $providerId = $this->provider();

      $video = $this->sql->single(
        'providers_media',
        'filename',
        'providerId=? AND isVideo=1 AND isnull(courseId)',
        [$providerId]
      );

      $filename = $video ? FILESTORE_URL . 'videos/' . $video['filename'] : null;

      return emit($response, $filename);
    }

    public function photoMainPut($request, $response, $args) {
      $url = $request->getParsedBody()['url'];
      $providerId = $this->provider();

      $url = explode('images/', $url)[1];

      $this->sql->update(
        'providers_media',
        'isMain=0',
        'providerId=? AND isVideo=0 AND isNull(courseId)',
        [$providerId]
      );

      $this->sql->update(
        'providers_media',
        'isMain=1',
        'providerId=? AND filename = ?',
        [$providerId, $url]
      );

      return emit($response, $this->getProviderPhotos());
    }

    public function photoDelete($request, $response, $args) {
      $url = $request->getParsedBody()['url'];
      $providerId = $this->provider();

      $filename = explode('images/', $url)[1];

      $directory = FILESTORE_PATH . "images/";
      $filePath = $directory . $filename;

      // Check if the file exists before attempting to delete
      if (file_exists($filePath)) {
          // Attempt to delete the file
          if (unlink($filePath)) {
              $this->sql->delete(
                'providers_media',
                'providerId=? AND filename = ?',
                [$providerId, $filename]
              );
          }
      }
      return emit($response, $this->getProviderPhotos());
    }

    public function videoPost($request, $response, $args)
    {

      $uploadedFile = $request->getUploadedFiles();
      $providerId = $this->provider();

      $video = $this->sql->single(
        'providers_media',
        'filename',
        'providerId=? AND isVideo=1 AND isnull(courseId)',
        [$providerId]
      );

      if ($video) {
        $directory = FILESTORE_PATH . "videos/";
        $filePath = $directory . $video['filename'];
        if (file_exists($filePath)) {
          if (unlink($filePath)) {
            $this->sql->delete(
                'providers_media',
                'providerId=? AND filename = ?',
                [$providerId, $video['filename']]
            );
          };
        }
      }
      $directory = FILESTORE_PATH . "videos/";
      $filename = moveUploadedFile($directory, $uploadedFile['file'], $providerId);

      $this->sql->insert(
        'providers_media',
        'providerId, isVideo, filename, isMain',
        [$providerId, 1, $filename, 1]
      );

      return emit($response, FILESTORE_URL . 'videos/' . $filename);
    }

    private function getProviderPhotos() {
      $providerId = $this->provider();

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
