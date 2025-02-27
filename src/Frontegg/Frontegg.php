<?php

namespace Frontegg;

use Frontegg\Audits\AuditsClient;
use Frontegg\Authenticator\Authenticator;
use Frontegg\Config\Config;
use Frontegg\Events\EventsClient;
use Frontegg\Events\Config\TriggerOptionsInterface;
use Frontegg\Exception\AuthenticationException;
use Frontegg\Exception\FronteggSDKException;
use Frontegg\Exception\InvalidParameterException;
use Frontegg\Exception\InvalidUrlConfigException;
use Frontegg\Http\ApiRawResponse;
use Frontegg\HttpClient\FronteggCurlHttpClient;
use Frontegg\HttpClient\FronteggHttpClientInterface;
use Frontegg\Proxy\Adapter\FronteggHttpClient\FronteggAdapter;
use Frontegg\Proxy\Proxy;
use Psr\Http\Message\RequestInterface;

class Frontegg
{
    /**
     * @const string Version number of the Frontegg PHP SDK.
     */
    public const VERSION = '0.2.0';

    /**
     * @const string Default API version for requests.
     */
    public const DEFAULT_API_VERSION = 'v1.0';

    /**
     * @const string The name of the environment variable that contains the client ID.
     */
    public const CLIENT_ID_ENV_NAME = 'FRONTEGG_CLIENT_ID';

    /**
     * @const string The name of the environment variable that contains the client secret key.
     */
    public const CLIENT_SECRET_ENV_NAME = 'FRONTEGG_CLIENT_SECRET_KEY';

    /**
     * @const string The name of the environment variable that contains the tenant ID.
     */
    public const TENANT_ID_ENV_NAME = 'FRONTEGG_TENANT_ID';

    /**
     * @const string Default API version for requests.
     */
    public const DEFAULT_API_BASE_URL = 'https://api.frontegg.com';

    /**
     * @var FronteggHttpClientInterface
     */
    protected $client;

    /**
     * @var Config
     */
    protected $config;

    /**
     * Frontegg authenticator instance.
     *
     * @var Authenticator
     */
    protected $authenticator;

    /**
     * Frontegg audits client instance.
     *
     * @var AuditsClient
     */
    protected $auditsClient;

    /**
     * Frontegg events client instance.
     *
     * @var EventsClient
     */
    protected $eventsClient;

    /**
     * Frontegg proxy.
     *
     * @var Proxy
     */
    protected $proxy;

    /**
     * Frontegg constructor.
     *
     * @param array $config
     *
     * @throws FronteggSDKException
     */
    public function __construct(array $config = [])
    {
        $config = array_merge(
            [
                'clientId' => getenv(static::CLIENT_ID_ENV_NAME),
                'clientSecret' => getenv(static::CLIENT_SECRET_ENV_NAME),
                'apiBaseUrl' => static::DEFAULT_API_BASE_URL,
                'apiUrls' => [],
                'apiVersion' => static::DEFAULT_API_VERSION,
                'httpClientHandler' => null,
                'disableCors' => false,
                'contextResolver' => null,
            ],
            $config
        );

        if (!$config['clientId']) {
            throw new FronteggSDKException(
                'Required "clientId" key not supplied in config and
                could not find fallback environment variable "'
                . static::CLIENT_ID_ENV_NAME . '"'
            );
        }
        if (!$config['clientSecret']) {
            throw new FronteggSDKException(
                'Required "clientSecret" key not supplied in config and
                could not find fallback environment variable "'
                . static::CLIENT_SECRET_ENV_NAME . '"'
            );
        }
        if (!is_callable($config['contextResolver'])) {
            throw new FronteggSDKException(
                'Required "contextResolver" key not supplied in config and
                could not find fallback value'
            );
        }

        $this->config = new Config(
            $config['clientId'],
            $config['clientSecret'],
            $config['apiBaseUrl'],
            $config['apiUrls'],
            $config['disableCors'],
            $config['contextResolver']
        );
        $this->client = $config['httpClientHandler'] ??
            new FronteggCurlHttpClient();

        $this->authenticator = new Authenticator($this->config, $this->client);
        $this->auditsClient = new AuditsClient($this->authenticator);
        $this->eventsClient = new EventsClient($this->authenticator);
        $this->proxy = new Proxy(
            $this->authenticator,
            new FronteggAdapter($this->client),
            $this->config->getContextResolver()
        );
    }

    /**
     * @return Authenticator
     */
    public function getAuthenticator(): Authenticator
    {
        return $this->authenticator;
    }

    /**
     * @return FronteggHttpClientInterface
     */
    public function getClient(): FronteggHttpClientInterface
    {
        return $this->client;
    }

    /**
     * @return Config
     */
    public function getConfig(): Config
    {
        return $this->config;
    }

    /**
     * @return AuditsClient
     */
    public function getAuditsClient(): AuditsClient
    {
        return $this->auditsClient;
    }

    /**
     * @return EventsClient
     */
    public function getEventsClient(): EventsClient
    {
        return $this->eventsClient;
    }

    /**
     * Initialize Frontegg service by authenticating into the Frontegg API.
     *
     * @return void
     */
    public function init(): void
    {
        $this->authenticator->authenticate();
    }

    /**
     * Retrieves filtered and sorted audits data collection.
     *
     * @param string      $tenantId
     * @param string      $filter
     * @param int         $offset
     * @param int|null    $count
     * @param string|null $sortBy
     * @param string      $sortDirection
     * @param mixed       $filters Dynamic query params based on the metadata
     *
     * @throws Exception\AuthenticationException
     * @return array
     */
    public function getAudits(
        string $tenantId,
        string $filter = '',
        int $offset = 0,
        ?int $count = null,
        ?string $sortBy = null,
        string $sortDirection = 'ASC',
        ...$filters
    ): array {
        return $this->auditsClient->getAudits(
            $tenantId,
            $filter,
            $offset,
            $count,
            $sortBy,
            $sortDirection,
            ... $filters
        );
    }

    /**
     * Sends audit log data into the Frontegg system.
     * Returns created audit log data.
     *
     * @param string $tenantId
     * @param array  $auditLog   Audits parameters:
     *                           user: string - User email
     *                           resource: string - Source of log event
     *                           action: string - Log event name
     *                           severity: string (required) - Log level
     *                           ip: string - User IP
     *
     * @throws AuthenticationException
     * @throws FronteggSDKException
     * @throws InvalidParameterException
     * @throws InvalidUrlConfigException
     *
     * @return array
     */
    public function sendAudit(string $tenantId, array $auditLog): array
    {
        return $this->auditsClient->sendAudit($tenantId, $auditLog);
    }

    /**
     * Trigger the event specified by trigger options.
     * Returns true on success.
     * Returns true on failure and $apiError property will contain an error.
     *
     * @param TriggerOptionsInterface $triggerOptions
     *
     * @throws Exception\EventTriggerException
     * @throws FronteggSDKException
     * @throws InvalidParameterException
     * @throws InvalidUrlConfigException
     *
     * @return bool
     */
    public function triggerEvent(TriggerOptionsInterface $triggerOptions): bool
    {
        return $this->eventsClient->trigger($triggerOptions);
    }

    /**
     * Forwards the request to Frontegg API.
     *
     * @param RequestInterface $request
     *
     * @throws Exception\UnexpectedValueException
     *
     * @return ApiRawResponse
     */
    public function forward(RequestInterface $request): ApiRawResponse
    {
        return $this->proxy->forwardTo($request, $this->config->getProxyUrl());
    }
}
