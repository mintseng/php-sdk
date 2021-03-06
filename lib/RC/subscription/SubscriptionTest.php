<?php

use RC\http\mocks\PresenceSubscriptionMock;
use RC\http\mocks\SubscriptionMock;
use RC\subscription\events\NotificationEvent;
use RC\subscription\Subscription;
use RC\test\TestCase;

class SubscriptionTest extends TestCase
{

    public function testPresenceDecryption()
    {

        $sdk = $this->getSDK();

        $sdk->getContext()
            ->getMocks()
            ->add(new PresenceSubscriptionMock());

        $executed = false;
        $aesMessage = 'gkw8EU4G1SDVa2/hrlv6+0ViIxB7N1i1z5MU/Hu2xkIKzH6yQzhr3vIc27IAN558kTOkacqE5DkLpRdnN1orwtIBsUHmPM' .
                      'kMWTOLDzVr6eRk+2Gcj2Wft7ZKrCD+FCXlKYIoa98tUD2xvoYnRwxiE2QaNywl8UtjaqpTk1+WDImBrt6uabB1WICY/qE0' .
                      'It3DqQ6vdUWISoTfjb+vT5h9kfZxWYUP4ykN2UtUW1biqCjj1Rb6GWGnTx6jPqF77ud0XgV1rk/Q6heSFZWV/GP23/iytD' .
                      'PK1HGJoJqXPx7ErQU=';

        $t = $this;

        $s = $sdk->getSubscription();
        $s->addEvents(array('/restapi/v1.0/account/~/extension/1/presence'))
          ->on(Subscription::EVENT_NOTIFICATION, function (NotificationEvent $e) use (&$executed, &$t) {

              $expected = array(
                  "timestamp" => "2014-03-12T20:47:54.712+0000",
                  "body"      => array(
                      "extensionId"     => 402853446008,
                      "telephonyStatus" => "OnHold"
                  ),
                  "event"     => "/restapi/v1.0/account/~/extension/402853446008/presence",
                  "uuid"      => "db01e7de-5f3c-4ee5-ab72-f8bd3b77e308"
              );

              $t->assertEquals($expected, $e->getPayload());

              $executed = true;

          })
          ->register();

        $s->getPubnub()->receiveMessage($aesMessage);

        $this->assertTrue($executed, 'make sure that callback has been called');

    }

    public function testPlainSubscription()
    {

        $sdk = $this->getSDK();

        $sdk->getContext()
            ->getMocks()
            ->add(new SubscriptionMock());

        $executed = false;

        $expected = array(
            "timestamp" => "2014-03-12T20:47:54.712+0000",
            "body"      => array(
                "extensionId"     => 402853446008,
                "telephonyStatus" => "OnHold"
            ),
            "event"     => "/restapi/v1.0/account/~/extension/402853446008/presence",
            "uuid"      => "db01e7de-5f3c-4ee5-ab72-f8bd3b77e308"
        );

        $t = $this;

        $s = $sdk->getSubscription();
        $s->addEvents(array('/restapi/v1.0/account/~/extension/1/presence'))
          ->on(Subscription::EVENT_NOTIFICATION, function (NotificationEvent $e) use (&$executed, $expected, &$t) {

              $t->assertEquals($expected, $e->getPayload());

              $executed = true;

          })
          ->register();

        $s->getPubnub()
          ->receiveMessage(array_merge(array(), $expected));

        $this->assertTrue($executed, 'make sure that callback has been called');

    }

    public function testSubscribeWithEvents()
    {

        $sdk = $this->getSDK();

        $sdk->getContext()
            ->getMocks()
            ->add(new SubscriptionMock());

        $s = $sdk->getSubscription()->register(array('events' => array('/restapi/v1.0/account/~/extension/1/presence')));

        $this->assertEquals('/restapi/v1.0/account/~/extension/1/presence', $s->getJson()->eventFilters[0]);

    }

}