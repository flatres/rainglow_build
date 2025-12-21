<?php
namespace Admin;
use Psr\Container\ContainerInterface as Container;
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

define('EXCHANGE_API', $_ENV["EXCHANGE_API"]);

ini_set('max_execution_time', 2400);

class Accounts
{
    /** @var \Slim\Container
     */
    protected $container;

    public function __construct(Container $container)
    {
       $this->sql = $container->get('mysql');
    }

    public function usersGet($request, $response, $args)
    {
      $data = $this->sql->select(
        'usr_details',
        'id, first_name, last_name, email, last_login, activated, disabled, is_locked, last_active, isDummy, isAdmin, created',
        'isAdmin = 0 AND id NOT IN (SELECT userId FROM providers) ORDER BY last_name ASC',
        []
      );
      return emit($response, $data);
    }

    public function providersGet($request, $response, $args)
    {
      $data = $this->sql->query('
          SELECT
            usr_details.id,
            usr_details.first_name,
            usr_details.email,
            usr_details.last_login,
            usr_details.activated,
            usr_details.disabled,
            usr_details.is_locked,
            usr_details.last_active,
            usr_details.isDummy,
            providers.id AS providerId,
            providers.name AS name,
            providers.homepage AS homepage,
            providers.hasOnline AS hasOnline,
            providers.logoImg AS logoImg,
            providers.bannerImg AS bannerImg,
            providers.color AS provider_color,
            providers.countryId AS countryId,
            providers.createdAt AS provider_createdAt
            FROM usr_details
            JOIN providers ON usr_details.id = providers.userId
            WHERE usr_details.isAdmin = 0
      ', []);

      foreach ($data as &$d) {
        $d['courses'] = $this->sql->query('Select count(*) as count FROM courses WHERE providerId=?', [$d['providerId']])[0]['count'];
        $d['profileClicks'] = $this->sql->query('Select count(*) as count FROM providers_clicks WHERE providerId=? AND courseId is NULL', [$d['providerId']])[0]['count'];
        $d['courseClicks'] = $this->sql->query('Select count(*) as count FROM providers_clicks WHERE providerId=? AND courseId is NOT NULL', [$d['providerId']])[0]['count'];

        $country = $this->sql->single('countries', 'name, isoCode', 'id=?', [$d['countryId']]);
        $d['country'] =$country['isoCode'] ?? '';
        $d['countryFull'] =$country['name'] ?? '';


        $membership = $this->sql->query('
          SELECT pa.*, m.name AS membership
          FROM providers_account AS pa
          LEFT JOIN
              memberships AS m ON pa.membershipTypeId = m.id
          WHERE
              pa.providerId = ?', [$d['providerId']]
        );
        $d['membership'] = $membership[0]['membership'] ?? '';
        $d['membershipExpires'] = $membership[0]['membershipExpires'] ?? '';

      }
      return emit($response, $data);
    }

}

 ?>




