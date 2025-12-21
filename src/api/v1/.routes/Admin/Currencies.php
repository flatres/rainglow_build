<?php
namespace Admin;
use Psr\Container\ContainerInterface as Container;
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

define('EXCHANGE_API', $_ENV["EXCHANGE_API"]);

ini_set('max_execution_time', 2400);

class Currencies
{
    /** @var \Slim\Container
     */
    protected $container;

    public function __construct(Container $container)
    {
       $this->sql = $container->get('mysql');
    }

    public function currenciesGet($request, $response, $args)
    {

      $data = [];
      return emit($response, $data);
    }

     public function currenciesPost($request, $response, $args) {
      // URL you want to fetch data from
      $urlBase = 'http://api.exchangeratesapi.io/v1/latest?access_key=' . EXCHANGE_API .'&base=EUR&symbols=USD,GBP';

      // Initialize cURL session
      $curl = curl_init();

      // Set the cURL options
      curl_setopt_array($curl, array(
          CURLOPT_URL => $urlBase,
          CURLOPT_RETURNTRANSFER => true, // Return the response as a string instead of outputting it directly
          CURLOPT_TIMEOUT => 30, // Set maximum execution time for the cURL request
          CURLOPT_HTTPGET => true, // Set request method to GET
      ));

      // Execute the cURL request and store the response
      $response = curl_exec($curl);

      curl_close($curl);

      // Decode the JSON response
      $data = json_decode($response, true);
      $rates = $data['rates'];
      $eurId = $this->sql->single('currencies', 'id', 'code=?', ['EUR'])['id'];

      $currencies = $this->sql->select('currencies', 'id, code', 'id>0', []);
      foreach ($currencies as $cF) {
        $fromId = $cF['id'];
        $fromCode = $cF['code'];
        foreach ($currencies as $cT) {
          $toId = $cT['id'];
          $toCode = $cT['code'];
          if ($fromId == $toId) continue;
          if ($fromId == $eurId) {
            $rate = $rates[$toCode];
          } elseif ($toId == $eurId) {
            $rate = 1 / $rates[$fromCode];
          } else {
            $rate = $rates[$toCode] / $rates[$fromCode];
          }
          $rateCheck = $this->sql->single('currencies_rates', 'id', 'fromId=? AND toId=?', [$fromId, $toId]);
          if ($rateCheck) {
            $this->sql->update('currencies_rates', 'rate=?', 'id=?', [$rate, $rateCheck['id']]);
          } else {
            $this->sql->insert('currencies_rates', 'fromId, toId, rate', [$fromId, $toId, $rate]);
          }

        }
      }


      return emit($response, [$data]);
     }

}

 ?>




