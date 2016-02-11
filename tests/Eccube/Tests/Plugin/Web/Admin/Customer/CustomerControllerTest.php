<?php

namespace Eccube\Tests\Plugin\Web\Admin\Customer;

use Eccube\Event\EccubeEvents;
use Eccube\Tests\Plugin\Web\Admin\AbstractAdminWebTestCase;

class CustomerControllerTest extends AbstractAdminWebTestCase
{
    public function setUp()
    {
        parent::setUp();
        $this->initializeMailCatcher();
        for ($i = 0; $i < 10; $i++) {
            $this->createCustomer('user-'.$i.'@example.com');
        }
        // sqlite では CsvType が生成されないので、ここで作る
        $CsvType = $this->app['eccube.repository.master.csv_type']->find(2);
        if (!is_object($CsvType)) {
            $CsvType = new CsvType();
            $CsvType->setId(2);
            $CsvType->setName('会員CSV');
            $CsvType->setRank(4);
            $this->app['orm.em']->persist($CsvType);
            $this->app['orm.em']->flush();
        }
    }

    public function tearDown()
    {
        $this->cleanUpMailCatcherMessages();
        parent::tearDown();
    }

    public function testIndex()
    {
        $this->client->request(
            'GET',
            $this->app->path('admin_customer')
        );
        $this->assertTrue($this->client->getResponse()->isSuccessful());

        $expected = array(
            EccubeEvents::ADMIN_CUSTOMER_INDEX_INITIALIZE,
        );

        $this->verifyOutputString($expected);
    }

    public function testIndexWithPost()
    {
        $this->client->request(
            'POST',
            $this->app->path('admin_customer'),
            array(
                'admin_search_customer' => array(
                    '_token' => 'dummy'
                )
            )
        );
        $this->assertTrue($this->client->getResponse()->isSuccessful());

        $expected = array(
            EccubeEvents::ADMIN_CUSTOMER_INDEX_INITIALIZE,
            EccubeEvents::ADMIN_CUSTOMER_INDEX_SEARCH
        );

        $this->verifyOutputString($expected);
    }

    public function testResend()
    {
        $Customer = $this->createCustomer();
        $this->client->request(
            'PUT',
            $this->app->path('admin_customer_resend', array('id' => $Customer->getId()))
        );
        $this->assertTrue($this->client->getResponse()->isRedirect($this->app->url('admin_customer')));

        $expected = array(
            EccubeEvents::MAIL_ADMIN_CUSTOMER_CONFIRM,
            EccubeEvents::ADMIN_CUSTOMER_RESEND_COMPLETE,
        );

        $this->verifyOutputString($expected);

        $Messages = $this->getMailCatcherMessages();
        $Message = $this->getMailCatcherMessage($Messages[0]->id);

        // 件名の書き換えを検証
        $this->expected = '会員登録のご確認';
        $this->actual = $Message->subject;
        $this->verify();
    }

    public function testDelete()
    {
        $Customer = $this->createCustomer();
        $this->client->request(
            'DELETE',
            $this->app->path('admin_customer_delete', array('id' => $Customer->getId()))
        );
        $this->assertTrue($this->client->getResponse()->isRedirect($this->app->url('admin_customer')));

        $expected = array(
            EccubeEvents::ADMIN_CUSTOMER_DELETE_COMPLETE,
        );

        $this->verifyOutputString($expected);
    }
}
