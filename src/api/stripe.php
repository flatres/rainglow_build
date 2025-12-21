<?php

require __DIR__ . '/vendor/autoload.php';


ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();


// load environmental variables
$dotenv = \Dotenv\Dotenv::createImmutable(dirname(__FILE__) . '/v1/');
$dotenv->load();

define('STRIPE_SECRET_KEY', $_ENV["STRIPE_SECRET_KEY"]);
define('STRIPE_ENDPOINT_SECRET', $_ENV["STRIPE_ENDPOINT_SECRET"]);

\Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

// Replace this endpoint secret with your endpoint's unique secret
// If you are testing with the CLI, find the secret by running 'stripe listen'
// If you are using an endpoint defined with the API or dashboard, look in your webhook settings
// at https://dashboard.stripe.com/webhooks

$payload = @file_get_contents('php://input');
$event = null;

$sql = new Dependency\Databases\LearnFlow();

try {
  $event = \Stripe\Event::constructFrom(
    json_decode($payload, true)
  );
} catch(\UnexpectedValueException $e) {
  // Invalid payload
  echo '⚠️  Webhook error while parsing basic request.';
  http_response_code(400);
  exit();
}
// Handle the event
switch ($event->type) {
  case 'checkout.session.completed':
    $orderId = $event->data->object->client_reference_id;

    $sql->update('providers_transactions', 'isComplete=1', 'id=?', [$orderId]);
    $t = $sql->single('providers_transactions', 'providerId, membershipId, tokens', 'id=?', [$orderId]);

    if (!$t['membershipId']) {
      // additional tokens - already has account
      $account = $sql->single('providers_account', 'id, tokens', 'providerId=?', [$t['providerId']]);
      $newTokens = (int)$t['tokens'] + (int)$account['tokens'];
      $sql->update('providers_account', 'tokens=?', 'id=?', [$newTokens, $account['id']]);

    } else {
      // membership
      $expires=date('Y-m-d', strtotime('+1 year'));

      //check for existing entry
      $check = $sql->single('providers_account', 'id', 'providerId=?', [$t['providerId']]);

      if ($check) {
        $newTokens = (int)$check['tokens'] == -1 ? -1 : (int)$check['tokens'] + (int)$t['tokens'];
        $sql->update(
          'providers_account', 'membershipTypeId=?, membershipExpires=?, tokens=?',
          'providerId=?',
          [$t['membershipId'], $expires, $t['tokens'], $t['providerId']]
        );
      } else {
        $sql->insert(
          'providers_account', 'membershipTypeId, membershipExpires, tokens, providerId',
          [$t['membershipId'], $expires, $t['tokens'], $t['providerId']]
        );
      }
    }


    break;

  default:
    // Unexpected event type
    echo 'Received unknown event type';
}


// <?php

// require_once 'shared.php';

// $event = null;

// try {
// 	// Make sure the event is coming from Stripe by checking the signature header
// 	$event = \Stripe\Webhook::constructEvent($input, $_SERVER['HTTP_STRIPE_SIGNATURE'], $config['stripe_webhook_secret']);
// }
// catch (Exception $e) {
// 	http_response_code(403);
// 	echo json_encode([ 'error' => $e->getMessage() ]);
// 	exit;
// }

// $details = '';

// $type = $event['type'];
// $object = $event['data']['object'];

// if($type == 'checkout.session.completed') {
//   error_log('🔔  Checkout Session was completed!');
// } else {
// 	error_log('🔔  Other webhook received! ' . $type);
// }

// $output = [
// 	'status' => 'success'
// ];

// echo json_encode($output, JSON_PRETTY_PRINT);
