<?php
namespace Provider;
use Psr\Container\ContainerInterface as Container;
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

define('FILESTORE_URL', $_ENV["FILESTORE_URL"]);

class Account
{
    /** @var \Slim\Container
     */
    protected $container;

    public function __construct(Container $container)
    {
       $this->sql= $container->get('mysql');
       $this->container = $container;
    }

    public function transactionGet($request, $response, $args)
    {
      $providerId = $this->provider();
      $bytes = random_bytes(20);
      $transactionId = bin2hex($bytes);

      $this->sql->delete('providers_transactions', 'providerId=? AND timestamp < (NOW() - INTERVAL 24 HOUR)', [$providerId]);

      $this->sql->insert('providers_transactions', 'providerId, transactionId', [$providerId, $transactionId]);
      $data = [
        'providerId' => $providerId,
        'transactionId' => $transactionId
      ];
      return emit($response, $data);
    }

    public function resetPost($request, $response, $args)
    {
      $providerId = $this->provider();
      $this->sql->delete('providers_account', 'providerId=?', [$providerId]);
      return emit($response, true);
    }

    public function tokenPost($request, $response, $args)
    {
      $data = (object)$request->getParsedBody();
      $membershipTypeId = $data->membershipTypeId ?? null;
      $tokens = $data->tokens ?? null;
      $providerId = $this->provider();
      $token = $data->token;

      $check = $this->sql->single(
        'providers_transactions',
        'id',
        'transactionId=? AND providerId=? AND isComplete=0',
        [$token, $providerId]
      );

      if ($check) {
        $account = $this->sql->single('providers_account', 'tokens', 'providerId=?', [$providerId]);
        if ($membershipTypeId) {
          $membership = $this->sql->single('memberships', 'tokens', 'id=?', [$membershipTypeId]);
          $tokenUpdate = $membership['tokens'];
          if ($tokenUpdate == -1) {
            $newTokens = -1;
          } else {
            $newTokens = $account ? $account['tokens'] + $tokenUpdate : $tokenUpdate;
          }
        } else {
          $tokenUpdate = $tokens;
          $newTokens = $account ? $account['tokens'] + $tokenUpdate : $tokenUpdate;
        }
        $this->sql->update(
          'providers_transactions',
          'membershipId=?, tokens=?, isComplete=1',
          'id=?',
          [$membershipTypeId, $tokenUpdate, $check['id']]
        );
        $expires =date('Y-m-d', strtotime('+1 year'));
        if ($account) {
          $this->sql->update('providers_account', 'membershipTypeId=?, tokens=?, membershipExpires=?', 'providerId=?', [$membershipTypeId, $tokenUpdate, $expires, $providerId]);
        } else {
          $this->sql->insert('providers_account', 'providerId, membershipTypeId, tokens, membershipExpires', [$providerId, $membershipTypeId, $tokenUpdate, $expires]);
        }
      }

      return emit($response, $data);
    }

    public function accountGet($request, $response, $args)
    {
      $providerId = $this->provider();
      $account = $this->sql->single(
        'providers_account',
        'providerId, membershipTypeId, membershipExpires, tokens',
        'providerId=?',
        [$providerId]
      );

      if (!$account) {
        $account = [
          'providerId' => $providerId,
          'membershipTypeId' => null,
          'membershipType' => null,
          'tokenCostUSD' => 99,
          'membershipExpires' => null,
          'hasMembership' => false,
          'tokens' => 0,
          'hasMembership' => false
        ];
      } else {
        $account['membershipExpiresTidy'] = date("d-m-Y", strtotime($account['membershipExpires']));
        $account['hasMembership'] = strtotime($account['membershipExpires']) > time();
        $account['hadMembership'] = $account['membershipExpires'] ? true : false;
        $membership = $this->sql->single('memberships', 'name, tokenCostUSD', 'id=?', [$account['membershipTypeId']]);
        if ($membership) {
          $account['membershipType'] = $membership['name'];
          $account['tokenCostUSD'] = $membership['tokenCostUSD'];
        } else {
          $account['membershipType'] = null;
          $account['tokenCostUSD'] = null;
        }
      }

      return emit($response, $account);

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
