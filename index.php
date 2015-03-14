<?php

require_once('lib/autoload.php');
require_once('vendor/autoload.php');

use RC\http\Response;
use RC\SDK;
use RC\subscription\NotificationEvent;
use RC\subscription\Subscription;

date_default_timezone_set('UTC');

// Make all PHP errors to be thrown as Exceptions

set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) {
        return;
    }
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

// Set up global exception handler (this includes Guzzle HTTP Exceptions)

set_exception_handler(function (Exception $e) {

    print 'Exception: ' . $e->getMessage() . PHP_EOL;

    if ($e instanceof \GuzzleHttp\Exception\RequestException) {

        $response = $e->getResponse();

        if ($response instanceof Response) {
            print 'SDK HTTP Error: ' . $response->getError() . PHP_EOL;
        }

        print print_r($response->json(), true) . PHP_EOL;

    }

    print $e->getTraceAsString() . PHP_EOL;

});

// Retrieve previous authentication data

$cacheDir = __DIR__ . DIRECTORY_SEPARATOR . '_cache';
$file = $cacheDir . DIRECTORY_SEPARATOR . 'platform.json';

if (!file_exists($cacheDir)) {
    mkdir($cacheDir);
}

$cachedAuth = [];

if (file_exists($file)) {
    $cachedAuth = json_decode(file_get_contents($file), true);
    unlink($file); // dispose cache file, it will be updated if script ends successfully
}

// Create SDK instance

$credentials = require('credentials.php');

$rcsdk = new SDK($credentials['appKey'], $credentials['appSecret'], $credentials['server']);

$platform = $rcsdk->getPlatform();

$platform->setAuthData($cachedAuth);

try {

    $platform->isAuthorized();

    print 'Authorization was restored' . PHP_EOL;

} catch (Exception $e) {

    print 'Auth exception: ' . $e->getMessage() . PHP_EOL;

    $auth = $platform->authorize($credentials['username'], $credentials['extension'], $credentials['password'], true);

    print 'Authorized' . PHP_EOL;

}

// Perform token refresh

print 'Refreshing' . PHP_EOL;

$refresh = $platform->refresh();

print 'Refreshed' . PHP_EOL;

// Load extensions

$extensions = $platform->get('/account/~/extension', ['query' => ['perPage' => 10]])->json()->records;

print 'Users loaded ' . count($extensions) . PHP_EOL;

// Load presence

$presences = $platform->get('/account/~/extension/' . $extensions[0]->id . ',' . $extensions[0]->id . '/presence')
                      ->getResponses();

print 'Presence loaded ' .
      $extensions[0]->name . ' - ' . $presences[0]->json()->presenceStatus . ', ' .
      $extensions[0]->name . ' - ' . $presences[1]->json()->presenceStatus . PHP_EOL;

try {

    $platform->get('/account/~/whatever');

} catch (\GuzzleHttp\Exception\RequestException $e) {

    $response = $e->getResponse();

    if ($response instanceof Response) {
        $message = $response->getError() . ' (from backend)';
    } else {
        $message = $e->getMessage();
    }

    print 'Expected HTTP Error: ' . $message . PHP_EOL;

}

// Send an SMS (asynchronously via Promise)

if (!$argv || !in_array('skipSMS', $argv)) {

    $platform
        ->post('/account/~/extension/~/sms', [
            'json'   => [
                'from' => ['phoneNumber' => $credentials['smsNumber']],
                'to'   => [
                    ['phoneNumber' => $credentials['mobileNumber']],
                ],
                'text' => 'Test from PHP',
            ],
            'future' => true
        ])
        ->then(function (Response $response) {
            print 'Sent ' . $response->json()->uri . PHP_EOL;
        });

    print 'Sending SMS' . PHP_EOL;

}

// Subscription

$subscription = $rcsdk->getSubscription();

$subscription->addEvents(['/account/~/extension/~/presence?detailedTelephonyState=true']);

$subscription->setKeepPolling(false);

$subscription->on(Subscription::EVENT_NOTIFICATION, function (NotificationEvent $e) {
    print 'Notification' . print_r($e->getPayload(), true) . PHP_EOL;
});

print 'Subscribing' . PHP_EOL;

$subscription->register();

print 'End' . PHP_EOL;

// Save authentication data

file_put_contents($file, json_encode($platform->getAuthData(), JSON_PRETTY_PRINT));
