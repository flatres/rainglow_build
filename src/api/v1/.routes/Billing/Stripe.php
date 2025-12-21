<?php
namespace Billing;
use Psr\Container\ContainerInterface as Container;
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

define('STRIPE_SECRET_KEY', $_ENV["STRIPE_SECRET_KEY"]);
define('SITE_URL', $_ENV["SITE_URL"]);

\Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

ini_set('max_execution_time', 2400);

$YOUR_DOMAIN = SITE_URL;

class Stripe
{
    /** @var \Slim\Container
     */
    protected $container;

    public function __construct(Container $container)
    {
       $this->sql = $container->get('mysql');
    }

    public function subscriptionSessionPost($request, $response, $args)
    {
      global $userId;

      $data = $request->getParsedBody();
      $key = $data['key'];

      //create a transactions log
      $providerId = $this->provider();
      $membership = $this->sql->single('memberships', 'id, tokens, costUSD', '`key`=?', [$key]);
      $membershipId = $membership['id'];
      $cost = $membership['costUSD'];
      $tokens = $membership['tokens'];
      $orderId = $this->sql->insert(
        'providers_transactions',
        'providerId, membershipId, tokens, costUSD',
        [$providerId, $membershipId, $tokens, $cost]
      );

      try {
        $prices = \Stripe\Price::all([
          // retrieve lookup_key from form data POST body
          'lookup_keys' => [$key],
          'expand' => ['data.product']
        ]);

        $session = \Stripe\Checkout\Session::create([
          'line_items' => [[
            'price' => $prices->data[0]->id,
            'quantity' => 1,
          ]],
          'mode' => 'subscription',
          'allow_promotion_codes' => true,
          'client_reference_id' => $orderId,
          'success_url' => SITE_URL . '/dashboard/account',
          'cancel_url' => SITE_URL . '/dashboard/account',
        ]);

        // header("HTTP/1.1 303 See Other");
        // header("Location: " . $session->url);
      } catch (Error $e) {
        // http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
      }
      return emit($response, $session->url);
    }

    public function tokensSessionPost($request, $response, $args)
    {
      global $userId;

      $data = $request->getParsedBody();
      $key = $data['key'];

      //create a transactions log
      $providerId = $this->provider();
      //get current membership
      $tokens = explode("_", $key)[1];
      $orderId = $this->sql->insert(
        'providers_transactions',
        'providerId, tokens, costUSD',
        [$providerId, $tokens, 99 * $tokens]
      );

      try {
        $prices = \Stripe\Price::all([
          // retrieve lookup_key from form data POST body
          'lookup_keys' => [$key],
          'expand' => ['data.product']
        ]);

        $session = \Stripe\Checkout\Session::create([
          'line_items' => [[
            'price' => $prices->data[0]->id,
            'quantity' => 1,
          ]],
          'mode' => 'payment',
          'client_reference_id' => $orderId,
          'success_url' => SITE_URL . '/dashboard/account',
          'cancel_url' => SITE_URL . '/dashboard/account',
        ]);

        // header("HTTP/1.1 303 See Other");
        // header("Location: " . $session->url);
      } catch (Error $e) {
        // http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
      }
      return emit($response, $session->url);
    }


    private function provider()
    {
      global $userId;
      $provider = $this->sql->single('providers', 'id', 'userId=?', [$userId]);
      if (!$provider) exit();
      return $provider['id'];
    }

}

 ?>




