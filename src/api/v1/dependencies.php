<?php

// monolog
$container->set('logger', function ($c) {
  $settings = $c->get('settings')['logger'];
  $logger = new Monolog\Logger($settings['name']);
  $logger->pushProcessor(new Monolog\Processor\UidProcessor());
  $logger->pushHandler(new Monolog\Handler\StreamHandler($settings['path'], $settings['level']));

  $syslog = new Monolog\Handler\SyslogHandler('myfacility', 'local6');
  $formatter = new Monolog\Formatter\LineFormatter("%channel%.%level_name%: %message% %extra%");
  $syslog->setFormatter($formatter);
  $logger->pushHandler($syslog);

  return $logger;
});

// monolog
$container->set('mysql',  function ($c) {
  $mysql = new Dependency\Databases\RainGlow();
  return $mysql;
});

//allows sql object to be passed to middleware via the constructor
$container->set('Authenticate',  function ($c) {
  return new Middleware\Authenticate($c->get('mysql'), $c->get('logger'));
});
