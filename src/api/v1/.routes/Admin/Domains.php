<?php
namespace Admin;
use Psr\Container\ContainerInterface as Container;
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

define('FILESTORE_URL', $_ENV["FILESTORE_URL"]);
define('SITE_URL', $_ENV["SITE_URL"]);

ini_set('max_execution_time', 2400);

class Domains
{
    /** @var \Slim\Container
     */
    protected $container;

    public function __construct(Container $container)
    {
       $this->sql= $container->get('mysql');
    }

    public function domainsGet($request, $response, $args)
    {
      $all = $this->sql->select('words_all', 'word', 'domainAvailable=? ORDER BY word ASC', [1]);
      $names = $this->sql->select('words_names', 'word', 'domainAvailable=? ORDER BY word ASC', [1]);
      $places = $this->sql->select('words_places', 'word', 'domainAvailable=? ORDER BY word ASC', [1]);

      $words = array_merge($all, $names, $places);
      foreach($words as &$w) $w['word'] = ucwords($w['word']);
      sortArrays($words, 'word', 'ASC');

      $data = [];

      //checked
      $all = $this->sql->single('words_all', 'COUNT(*) as count', 'isChecked=?', [1]);
      $names = $this->sql->single('words_names', 'COUNT(*) as count', 'isChecked=?', [1]);
      $places = $this->sql->single('words_places', 'COUNT(*) as count', 'isChecked=?', [1]);
      $data['checked'] = $all['count'] + $names['count'] + $places['count'];

      //checked
      $all = $this->sql->single('words_all', 'COUNT(*) as count', 'domainAvailable=?', [1]);
      $names = $this->sql->single('words_names', 'COUNT(*) as count', 'domainAvailable=?', [1]);
      $places = $this->sql->single('words_places', 'COUNT(*) as count', 'domainAvailable=?', [1]);
      $data['found'] = $all['count'] + $names['count'] + $places['count'];

      $data['words'] = $words;

      return emit($response, $data);
    }

     public function domainsPost($request, $response, $args) {


      //reset
      // $this->sql->update('words_all', 'isChecked = 0, domainAvailable=0', 'id > ?', [0]);
      // $this->sql->update('words_names', 'isChecked = 0, domainAvailable=0', 'id > ?', [0]);
      // $this->sql->update('words_places', 'isChecked = 0, domainAvailable=0', 'id > ?', [0]);
      // return emit($response, [true]);

      // $all = $this->sql->select('words_all', 'id, word, "all" AS table', 'isChecked = ? ORDER BY RAND() LIMIT 1000', [0]);
      // $names = $this->sql->select('words_names', 'id, word, "names" AS table', 'isChecked = ? ORDER BY RAND() LIMIT 1000', [0]);
      // $places = $this->sql->select('words_places', 'id, word, "places" AS table', 'isChecked = ? ORDER BY RAND() LIMIT 1000', [0]);
      $all = $this->sql->select('words_all', 'id, word, "all" AS table', 'isChecked = ? AND LENGTH(word) <= 7 ORDER BY RAND()', [0]);
      $names = $this->sql->select('words_names', 'id, word, "names" AS table', 'isChecked = ? AND LENGTH(word) <= 7 ORDER BY RAND()', [0]);
      $places = $this->sql->select('words_places', 'id, word, "places" AS table', 'isChecked = ? AND LENGTH(word) <= 7 ORDER BY RAND()', [0]);
      $words = array_merge($all, $names, $places);
      shuffle($words);

      $i = 0;
      foreach ($words as $w) {
        if (strlen($w['word']) > 7 ) continue;
        $url = $w['word'] . '.com';
        $available = (!checkdnsrr($url . '.', 'ANY') && !checkdnsrr('www.' . $url . '.', 'ANY')) ? 1 :  0;
        $lastTime = 0;
        if ($available == 1) {
          while (time() - $lastTime < 4) {}
          $lastTime = time();
          $available = $this->whois($url, $w);
          // return emit($response, [true]);
        } else {
          $table = 'words_' . $w['AStable'];
          $this->sql->update($table, 'isChecked=?, domainAvailable=?', 'id=?', [1, $available, $w['id']]);
        }

        $i++;
      }
      return emit($response, [count($words)]);
     }

     private function whois($url, $w) {
      try {
        $available = null;
        $curl = curl_init();
        curl_setopt_array($curl, array(
          CURLOPT_URL => 'https://api.whoisfreaks.com/v1.0/whois?whois=live&domainName='.$url.'&apiKey=ac25d80d00b140aaaa642fd58227c941',
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => '',
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 0,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => 'GET',
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        // print_r($response);
        $response = json_decode($response, true);
        // echo $response['domain_registered'] . PHP_EOL;
        if (isset($response['domain_registered'])) {
          $available = $response['domain_registered'] == 'no' ? 1 : 0;
          $table = 'words_' . $w['AStable'];
          $this->sql->update($table, 'isChecked=?, domainAvailable=?', 'id=?', [1, $available, $w['id']]);
        }
      } catch (Exception $e) {}
      return $available;

     }

}

 ?>




