<?php
namespace Admin;
use Psr\Container\ContainerInterface as Container;
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

define('FILESTORE_URL', $_ENV["FILESTORE_URL"]);
define('SITE_URL', $_ENV["SITE_URL"]);

ini_set('max_execution_time', 6000);

class Dummies
{
    /** @var \Slim\Container
     */
    protected $container;

    public function __construct(Container $container)
    {
       $this->sql= $container->get('mysql');
       ini_set('max_execution_time', 6000);
    }

    public function dummiesGet($request, $response, $args)
    {

      return emit($response, [true]);
    }

     public function dummiesPost($request, $response, $args) {

      $count = $args['count'];
      // $count = 1;
      $countries = $this->sql->select('countries', 'id', 'id>0', []);
      $categories = $this->sql->select('categories', 'id', 'id>0', []);

      $randomCosts = $this->generateRandomNumbers($count);

      $current = $this->sql->select('usr_details', 'id, email', 'isDummy=1', []);
      $start = count($current);
      for ($i = 0; $i < $count; $i++) {
        //user account
        $email = 'dummy' . ($start + $i) . '@learnflowhub.com';
        $id = $this->sql->insert(
          'usr_details',
          'email, first_name, last_name, activated, isDummy',
          [
            $email,
            $this->sql->single('words_names', 'word', 'id > 0 ORDER BY RAND() LIMIT 1')['word'],
            ucfirst($this->sql->single('words_all', 'word', 'id > 0 ORDER BY RAND() LIMIT 1')['word']),
            1,
            1
          ]
        );
        //provider account
        $providerId = $this->sql->insert(
          'providers',
          'userId, name, hasOnline, countryId',
          [$id, $this->sql->single('words_places', 'word', 'id > 0 ORDER BY RAND() LIMIT 1')['word'], random_int(0, 1), $countries[array_rand($countries, 1)]['id']]
        );

        $rand = mt_rand(1, 100); // random number from 1 to 100
        if ($rand <= 5) {
            $membershipTypeId = 4; // 5%
        } elseif ($rand <= 15) {
            $membershipTypeId = 3; // next 10%
        } elseif ($rand <= 45) {
            $membershipTypeId = 2; // next 30%
        } else {
            $membershipTypeId = 1; // remaining 55%
        }
        $date = new \DateTime();              // current date and time
        $date->modify('+1 year');            // add 1 year
        $membershipExpires = $membershipTypeId == 1 ? '2099-12-31' : $date->format('Y-m-d');
        $this->sql->insert('providers_account', 'providerId, membershipTypeId, tokens, membershipExpires', [$id, 1, 0, '2099-12-31']);

        //logo
        $hash = bin2hex(random_bytes(18));
        $fileName = "{$hash}{$providerId}.png";
        $path = FILESTORE_PATH . 'images/';
        $file = $path . $fileName;
        $image = imagecreatefromjpeg('https://picsum.photos/200');
        imagepng($image, $file);
        $this->sql->update('providers', 'logoImg=?', 'id=?', [$fileName, $providerId]);

        $testimonials = "'Excellent service' - John Barnes <br/> 'I was extremely impressed with the quality of the course' - Myra Smith";

        //profile
        $this->sql->insert(
          'providers_profiles',
          'providerId, localeId, tagline, description, testimonials',
          [$providerId, 1, 'This is a tagline', 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.', $testimonials]
        );

        //countries
        $cntCount = random_int(2, 6);
        $cnts = array_rand($countries, $cntCount);
        foreach($cnts as $c) $this->sql->insert('countries_providers', 'providerId, countryId', [$providerId, $countries[$c]['id']]);

        //categories
        $catCount = random_int(2, 6);
        $cats = array_rand($categories, $catCount);
        foreach($cats as $cat) $this->sql->insert('categories_providers', 'providerId, categoryId', [$providerId, $categories[$cat]['id']]);

        //media
        $mediaCount = random_int(2, 8);
        for ($m = 0; $m < $mediaCount; $m++) {
          $hash = bin2hex(random_bytes(18));
          $fileName = "{$hash}{$providerId}.png";
          $path = FILESTORE_PATH . 'images/';
          $file = $path . $fileName;
          $image = imagecreatefromjpeg('https://picsum.photos/900/600');
          imagepng($image, $file);
          $this->sql->insert(
            'providers_media',
            'providerId, isMain, isVideo, filename',
            [$providerId, $m == 0 ? 1 : 0, 0, $fileName]);
        }

        //membership
        $currentDate = new \DateTime();
        // Generate a random interval between 1 and 365 days
        $randomInterval = mt_rand(1, 365);
        // Add the random interval to the current date
        $currentDate->add(new \DateInterval('P' . $randomInterval . 'D'));
        // Format the date
        $randomDate = $currentDate->format('Y-m-d');

        $randomMembership = random_int(1, 4);
        $tokens = $this->sql->single('memberships', 'tokens', 'id=?', [$randomMembership])['tokens'];
        $this->sql->insert('providers_account', 'providerId, membershipTypeId, membershipExpires, tokens', [$providerId, $randomMembership, $randomDate, $tokens]);


        //courses
        $courseCount = random_int(2, 8);
        for ($c = 0; $c < $courseCount; $c++) {
          $isOnline = random_int(0,1);

          $currentDate = new \DateTime();
          // Generate a random interval between 1 and 365 days
          $randomInterval = mt_rand(1, 365);
          // Add the random interval to the current date
          $currentDate->add(new \DateInterval('P' . $randomInterval . 'D'));
          // Format the date
          $randomDate = $currentDate->format('Y-m-d');
          $randomCost = $randomCosts[random_int(0, $count - 1)];
          if ($isOnline == 0) {
            $data = [
              $providerId,
              0,
              $randomDate,
              random_int(2,5),
              random_int(1,3),
              ucfirst($this->sql->single('words_all', 'word', 'id > 0 ORDER BY RAND() LIMIT 1')['word']),
              $countries[array_rand($countries)]['id'],
              $randomCost,
              1
            ];
          } else {
            $data = [
              $providerId,
              1,
              $randomDate,
              random_int(2,5),
              random_int(1,3),
              null,
              null,
              $randomCost,
              1
            ];
          }
          $cId = $this->sql->insert(
            'courses',
            'providerId, isOnline, startDate, duration, durationUnitId, location, countryId, cost, isPublished',
            $data
          );

          //categories
          $catCount = random_int(2, 6);
          $cats = array_rand($categories, $catCount);
          foreach($cats as $cat) $this->sql->insert('categories_courses', 'courseId, categoryId', [$cId, $categories[$cat]['id']]);

          $title = ucfirst($this->sql->single('words_all', 'word', 'id > 0 ORDER BY RAND() LIMIT 1')['word']) . ' & ' . ucfirst($this->sql->single('words_all', 'word', 'id > 0 ORDER BY RAND() LIMIT 1')['word']);
          $description = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.';
          $outcomes = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.';

          $data = [
            $providerId,
            $cId,
            1,
            $title,
            $description,
            $outcomes
          ];
          //profile
          $this->sql->insert(
            'courses_profiles',
            'providerId, courseId, localeId, title, description, outcomes',
            $data
          );

        }

      }

      return emit($response, [true]);
     }

    public function dummiesDelete($request, $response, $args) {
      $dummies = $this->sql->select('usr_details', 'id', 'isDummy = 1', []);

      foreach ($dummies as $d) {
        $provider = $this->sql->single('providers', 'id, logoImg', 'userId=?', [$d['id']]);
        if ($provider) {
          $providerId = $provider['id'];
          $this->deleteImg($provider['logoImg']);
          $media = $this->sql->select('providers_media', 'filename', 'providerId=? AND isVideo=0', [$providerId]);
          foreach ($media as $m) $this->deleteImg($m['filename']);

        }
      }

      $this->sql->delete('usr_details', 'isDummy = 1', []);

      return emit($response, [true]);
    }

    private function deleteImg($filename) {
      $directory = FILESTORE_PATH . "images/";
      $filePath = $directory . $filename;

      // Check if the file exists before attempting to delete
      if (file_exists($filePath)) {
          // Attempt to delete the file
          if (unlink($filePath)) return true;
      }
    }

    private function generateRandomLogo($filename) {
        // Create a 128x128 image
        $image = imagecreatetruecolor(128, 128);

        // Generate random colors
        $bgColor = imagecolorallocate($image, rand(0, 255), rand(0, 255), rand(0, 255));
        $textColor = imagecolorallocate($image, rand(0, 255), rand(0, 255), rand(0, 255));

        // Fill the background with a random color
        imagefilledrectangle($image, 0, 0, 128, 128, $bgColor);

        // Load English words from an external file
        $dictionary = file('english_words.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        // Add random text from the dictionary
        $text = $dictionary[array_rand($dictionary)];
        $fontSize = 20;
        $angle = rand(-45, 45);
        $x = rand(10, 50);
        $y = rand(50, 100);

        imagettftext($image, $fontSize, $angle, $x, $y, $textColor, 'arial.ttf', $text);

        // Save the image to a file
        imagepng($image, $filename);

        // Free up memory
        imagedestroy($image);
    }

    private function generateRandomNumbers($totalNumbers) {
        $numbers = [];
        $zerosCount = (int) ($totalNumbers * 0.2);
        $minusOnesCount = (int) ($totalNumbers * 0.2);
        $randomCount = $totalNumbers - $zerosCount - $minusOnesCount;

        // Add 0's
        for ($i = 0; $i < $zerosCount; $i++) {
            $numbers[] = 0;
        }

        // Add -1's
        for ($i = 0; $i < $minusOnesCount; $i++) {
            $numbers[] = -1;
        }

        // Add random numbers between 100 and 2000
        for ($i = 0; $i < $randomCount; $i++) {
            $numbers[] = rand(100, 2000);
        }

        // Shuffle the array to randomize the order
        shuffle($numbers);

        return $numbers;
    }


}

 ?>




