<?php

namespace Frontegg\Tests;

use Frontegg\Authenticator\AccessToken;
use Frontegg\Authenticator\Authenticator;
use Frontegg\Config\Config;
use Frontegg\Events\Channel\WebHookBody;
use Frontegg\Events\Config\ChannelsConfig;
use Frontegg\Events\Config\DefaultProperties;
use Frontegg\Events\Config\TriggerOptions;
use Frontegg\Frontegg;
use Frontegg\Http\ApiRawResponse;
use Frontegg\HttpClient\FronteggHttpClientInterface;
use Frontegg\Tests\Helper\AuthenticatorTestCaseHelper;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\RequestInterface;

class FronteggTest extends AuthenticatorTestCaseHelper
{
    /**
     * @throws \Frontegg\Exception\FronteggSDKException
     *
     * @return void
     */
    public function testFronteggAuthenticatorIsCreated(): void
    {
        // Arrange
        $config = [
            'clientId' => 'clientTestID',
            'clientSecret' => 'apiTestSecretKey',
            'contextResolver' => function (RequestInterface $request) {
                return [];
            }
        ];
        $frontegg = new Frontegg($config);

        // Assert
        $this->assertInstanceOf(
            Authenticator::class,
            $frontegg->getAuthenticator()
        );
        $this->assertInstanceOf(Config::class, $frontegg->getConfig());
        $this->assertEquals(
            'clientTestID',
            $frontegg->getConfig()->getClientId()
        );
        $this->assertEquals(
            'apiTestSecretKey',
            $frontegg->getConfig()->getClientSecret()
        );
        $this->assertInstanceOf(
            FronteggHttpClientInterface::class,
            $frontegg->getClient()
        );
    }

    /**
     * @throws \Frontegg\Exception\FronteggSDKException
     *
     * @return void
     */
    public function testFronteggInitialized(): void
    {
        // Arrange
        $httpClient = $this->createFronteggCurlHttpClientStub(
            [$this->createAuthHttpApiRawResponse()]
        );
        $config = [
            'clientId' => 'clientTestID',
            'clientSecret' => 'apiTestSecretKey',
            'httpClientHandler' => $httpClient,
            'contextResolver' => function (RequestInterface $request) {
                return [];
            }
        ];
        $frontegg = new Frontegg($config);

        // Act
        $frontegg->init();

        // Assert
        $this->assertInstanceOf(
            Authenticator::class,
            $frontegg->getAuthenticator()
        );
        $this->assertInstanceOf(
            AccessToken::class,
            $frontegg->getAuthenticator()->getAccessToken()
        );
    }

    /**
     * @throws \Frontegg\Exception\FronteggSDKException
     *
     * @return void
     */
    public function testFronteggGetAudits(): void
    {
        // Arrange
        $authResponse = $this->createAuthHttpApiRawResponse();
        $auditsResponse = new ApiRawResponse(
            [],
            json_encode(
                [
                    'data' => [
                        ['log1'],
                        ['log 2'],
                    ],
                    'total' => 2,
                ]
            ),
            200
        );
        $httpClient = $this->createFronteggCurlHttpClientStub(
            [$authResponse, $auditsResponse]
        );
        $config = [
            'clientId' => 'clientTestID',
            'clientSecret' => 'apiTestSecretKey',
            'httpClientHandler' => $httpClient,
            'contextResolver' => function (RequestInterface $request) {
                return [];
            }
        ];
        $frontegg = new Frontegg($config);

        // Act
        $auditLogs = $frontegg->getAudits('THE-TENANT-ID');

        // Assert
        $this->assertNotEmpty($auditLogs['data']);
        $this->assertGreaterThanOrEqual(2, count($auditLogs['data']));
        $this->assertNotEmpty($auditLogs['total']);
        $this->assertContains(['log1'], $auditLogs['data']);
        $this->assertContains(['log 2'], $auditLogs['data']);
    }

    /**
     * @throws \Frontegg\Exception\FronteggSDKException
     *
     * @return void
     */
    public function testFronteggTriggerEvent(): void
    {
        // Arrange
        $authResponse = $this->createAuthHttpApiRawResponse();
        $eventsResponse = new ApiRawResponse(
            [],
            '{
                "eventKey":"event-key",
                "properties":{},
                "channels":{},
                "vendorId":"THE-VENDOR-ID",
                "tenantId":"THE-TENANT-ID"
            }',
            200
        );
        $httpClient = $this->createFronteggCurlHttpClientStub(
            [$authResponse, $eventsResponse]
        );
        $config = [
            'clientId' => 'clientTestID',
            'clientSecret' => 'apiTestSecretKey',
            'httpClientHandler' => $httpClient,
            'contextResolver' => function (RequestInterface $request) {
                return [];
            }
        ];
        $frontegg = new Frontegg($config);

        $webhookBody = new WebHookBody(
            [
                'field 1' => 'value 1',
                'field 2' => 'value 2',
                'field 3' => 'value 3',
            ]
        );

        $channelsConfiguration = new ChannelsConfig();
        $channelsConfiguration->setWebhook($webhookBody);

        $triggerOptions = new TriggerOptions(
            'event-key',
            new DefaultProperties(
                'Default notification title',
                'Default notification description!'
            ),
            $channelsConfiguration,
            'THE-TENANT-ID'
        );

        // Act
        $isSuccess = $frontegg->triggerEvent($triggerOptions);

        // Assert
        $this->assertTrue($isSuccess);
        $this->assertNull($frontegg->getEventsClient()->getApiError());
    }

    /**
     * @throws \Frontegg\Exception\UnexpectedValueException
     *
     * @return void
     */
    public function testProxyCanForwardPostAuditLogs(): void
    {
        // Arrange
        $authResponse = $this->createAuthHttpApiRawResponse();
        $apiResponse = new ApiRawResponse(
            ['Content-type' => 'application/json'],
            '{
                "data":[
                    {
                        "title":"Default title",
                        "severity":"Info",
                        "tenantId":"tacajob400@icanav.net",
                        "vendorId":"6da27373-1572-444f-b3c5-ef702ce65123",
                        "createdAt":"2020-08-22 06:47:25.025",
                        "description":"Default description",
                        "frontegg_id":"6eacf416-67e2-4760-85d7-9ab90a18a945"
                    }
                ]
            }',
            200
        );
        $httpClient = $this->createFronteggCurlHttpClientStub(
            [$authResponse, $apiResponse]
        );
        $config = [
            'clientId' => 'clientTestID',
            'clientSecret' => 'apiTestSecretKey',
            'httpClientHandler' => $httpClient,
            'contextResolver' => function (RequestInterface $request) {
                return [];
            }
        ];
        $frontegg = new Frontegg($config);
        $auditLogData = [
            'user' => 'testuser@t.com',
            'resource' => 'Portal',
            'action' => 'Login',
            'severity' => 'Info',
            'ip' => '123.1.2.3',
        ];
        $request = new Request(
            'POST',
            Config::PROXY_URL . '/audits',
            [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            http_build_query($auditLogData)
        );

        // Act
        $response = $frontegg->forward($request);


        // Assert
        $this->assertInstanceOf(ApiRawResponse::class, $response);
        $this->assertContains($response->getHttpResponseCode(), [200, 202]);
        $this->assertNotEmpty($response->getHeaders());
        $this->assertJson($response->getBody());
    }

    /**
     * @throws \Frontegg\Exception\UnexpectedValueException
     *
     * @return void
     */
    public function testProxyCanForwardGetAuditLogs(): void
    {
        // Arrange
        $authResponse = $this->createAuthHttpApiRawResponse();
        $apiResponse = new ApiRawResponse(
            ['Content-type' => 'application/json'],
            '{
                "data":[
                    {
                        "title":"Default title",
                        "severity":"Info",
                        "tenantId":"tacajob400@icanav.net",
                        "vendorId":"6da27373-1572-444f-b3c5-ef702ce65123",
                        "createdAt":"2020-08-22 06:47:25.025",
                        "description":"Default description",
                        "frontegg_id":"6eacf416-67e2-4760-85d7-9ab90a18a945"
                    }
                ]
            }',
            200
        );
        $httpClient = $this->createFronteggCurlHttpClientStub(
            [$authResponse, $apiResponse]
        );
        $config = [
            'clientId' => 'clientTestID',
            'clientSecret' => 'apiTestSecretKey',
            'httpClientHandler' => $httpClient,
            'contextResolver' => function (RequestInterface $request) {
                return [];
            }
        ];
        $frontegg = new Frontegg($config);
        $request = new Request(
            'GET',
            Config::PROXY_URL . '/audits?sortDirection=desc&sortBy=createdAt&filter=&offset=0&count=20'
        );

        // Act
        $response = $frontegg->forward($request);

        // Assert
        $this->assertInstanceOf(ApiRawResponse::class, $response);
        $this->assertEquals(200, $response->getHttpResponseCode());
        $this->assertNotEmpty($response->getHeaders());
        $this->assertJson($response->getBody());
    }
}
