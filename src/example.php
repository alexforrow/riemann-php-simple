<?php
error_reporting(-1);

// replace with your autoloader
require __DIR__ . '/../../ms-apre/src/main/APRE/bootstrap.php';

date_default_timezone_set('UTC');

$riemann = new Riemann\Client('localhost', 5555, 'process:super-app:');


// normal
$riemann->send(array(
  'service' => 'test1',
  'state' => 'ok',
  'tags' => array('gauge', 'first'),
  'description' => 'first test',
  'ttl' => 60,
  'metric' => mt_rand(0, 99),
));


// send over tcp
$riemann->send(array(
  'service' => 'test2_tcp',
  'state' => 'ok',
  'tags' => array('gauge', 'second'),
  'description' => 'second test',
  'ttl' => 60,
  'metric' => mt_rand(0, 99),
), 'tcp');


// manual flush
$riemann->send(array(
  'service' => 'test3_manual_flush',
  'state' => 'critical',
  'tags' => array('gauge', 'third'),
  'description' => 'third test',
  'ttl' => 300,
  'metric' => mt_rand(0, 99),
), false);

$riemann->send(array(
  'service' => 'test4:manual_flush',
  'state' => 'warning',
  'tags' => array('gauge', 'forth'),
  'description' => 'forth test',
  'ttl' => 60,
  'metric' => mt_rand(0, 999)/10,
), false);

$riemann->flush('tcp');


// large event. Will be sent via TCP
$riemann->send(array(
  'service' => 'test7:large_event',
  'state' => 'warning',
  'tags' => array('gauge', 'fifth'),
  'description' => str_repeat('a', 4096),
  'ttl' => 60,
  'metric' => mt_rand(0, 999)/10,
));
