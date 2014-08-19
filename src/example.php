<?php
error_reporting(-1);

// replace with your autoloader
require __DIR__ . '/../../../ms-apre/src/main/APRE/bootstrap.php';

date_default_timezone_set('UTC');

$riemann = new Riemann\Client();

$riemann->send(array(
  'service' => 'test1',
  'state' => 'ok',
  'tags' => array('gauge', 'first'),
  'description' => 'first test',
  'ttl' => 60,
  'metric' => mt_rand(0, 99),
));

$riemann->send(array(
  'service' => 'test2',
  'state' => 'ok',
  'tags' => array('gauge', 'second'),
  'description' => 'second test',
  'ttl' => 60,
  'metric' => mt_rand(0, 99),
), true, 'tcp');

$riemann->send(array(
  'service' => 'test3',
  'state' => 'critical',
  'tags' => array('gauge', 'third'),
  'description' => 'third test',
  'ttl' => 300,
  'metric' => mt_rand(0, 99),
), false);

$riemann->send(array(
  'service' => 'test4',
  'state' => 'warning',
  'tags' => array('gauge', 'forth'),
  'description' => 'forth test',
  'ttl' => 60,
  'metric' => mt_rand(0, 999)/10,
), false);

$riemann->flush('tcp');
