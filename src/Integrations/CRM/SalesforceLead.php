<?php
/**
 * Freeform for Craft
 *
 * @package       Solspace:Freeform
 * @author        Solspace, Inc.
 * @copyright     Copyright (c) 2008-2018, Solspace, Inc.
 * @link          https://solspace.com/craft/freeform
 * @license       https://solspace.com/software/license-agreement
 */

namespace Solspace\FreeformPro\Integrations\CRM;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Solspace\Freeform\Library\Exceptions\Integrations\CRMIntegrationNotFoundException;
use Solspace\Freeform\Library\Exceptions\Integrations\IntegrationException;
use Solspace\Freeform\Library\Integrations\CRM\AbstractCRMIntegration;
use Solspace\Freeform\Library\Integrations\DataObjects\FieldObject;
use Solspace\Freeform\Library\Integrations\IntegrationStorageInterface;
use Solspace\Freeform\Library\Integrations\SettingBlueprint;
use Solspace\Freeform\Library\Integrations\TokenRefreshInterface;
use Solspace\Freeform\Library\Logging\LoggerInterface;

class SalesforceLead extends AbstractCRMIntegration implements TokenRefreshInterface
{
    const TITLE        = 'Salesforce Lead';
    const LOG_CATEGORY = 'Salesforce';

    const SETTING_CLIENT_ID     = 'salesforce_client_id';
    const SETTING_CLIENT_SECRET = 'salesforce_client_secret';
    const SETTING_USER_LOGIN    = 'salesforce_username';
    const SETTING_USER_PASSWORD = 'salesforce_password';
    const SETTING_LEAD_OWNER    = 'salesforce_lead_owner';
    const SETTING_SANDBOX       = 'salesforce_sandbox';
    const SETTING_CUSTOM_URL    = 'salesforce_custom_url';
    const SETTING_INSTANCE      = 'instance';

    /**
     * Returns a list of additional settings for this integration
     * Could be used for anything, like - AccessTokens
     *
     * @return SettingBlueprint[]
     */
    public static function getSettingBlueprints(): array
    {
        return [
            new SettingBlueprint(
                SettingBlueprint::TYPE_BOOL,
                self::SETTING_LEAD_OWNER,
                'Assign Lead Owner?',
                'Enabling this will make Salesforce assign a lead owner based on lead owner assignment rules',
                false
            ),
            new SettingBlueprint(
                SettingBlueprint::TYPE_BOOL,
                self::SETTING_SANDBOX,
                'Sandbox Mode',
                'Enabling this connects to "test.salesforce.com" instead of "login.salesforce.com"',
                false
            ),
            new SettingBlueprint(
                SettingBlueprint::TYPE_BOOL,
                self::SETTING_CUSTOM_URL,
                'Using custom URL?',
                '',
                false
            ),
            new SettingBlueprint(
                SettingBlueprint::TYPE_CONFIG,
                self::SETTING_CLIENT_ID,
                'Client ID',
                'Enter the Client ID of your app in here',
                true
            ),
            new SettingBlueprint(
                SettingBlueprint::TYPE_CONFIG,
                self::SETTING_CLIENT_SECRET,
                'Client Secret',
                'Enter the Client Secret of your app here',
                true
            ),
            new SettingBlueprint(
                SettingBlueprint::TYPE_CONFIG,
                self::SETTING_USER_LOGIN,
                'Username',
                'Enter your Salesforce username here',
                true
            ),
            new SettingBlueprint(
                SettingBlueprint::TYPE_CONFIG,
                self::SETTING_USER_PASSWORD,
                'Password',
                'Enter your Salesforce password here',
                true
            ),
            new SettingBlueprint(
                SettingBlueprint::TYPE_INTERNAL,
                self::SETTING_INSTANCE,
                'Instance',
                'This will be fetched automatically upon authorizing your credentials.',
                false
            ),
        ];
    }

    /**
     * A method that initiates the authentication
     */
    public function initiateAuthentication()
    {
    }

    /**
     * Authorizes the application
     * Returns the access_token
     *
     * @return string
     * @throws IntegrationException
     * @throws \Exception
     */
    public function fetchAccessToken(): string
    {
        $client = new Client();

        $clientId     = $this->getClientId();
        $clientSecret = $this->getClientSecret();
        $username     = $this->getUsername();
        $password     = $this->getPassword();

        if (!$clientId || !$clientSecret || !$username || !$password) {
            throw new IntegrationException('Some or all of the configuration values are missing');
        }

        $payload = [
            'grant_type'    => 'password',
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
            'username'      => $username,
            'password'      => $password,
        ];

        try {
            $response = $client->post(
                $this->getAccessTokenUrl(),
                [
                    'form_params' => $payload,
                ]
            );

            $json = json_decode((string) $response->getBody());

            if (!isset($json->access_token)) {
                throw new IntegrationException(
                    $this->getTranslator()->translate(
                        "No 'access_token' present in auth response for {serviceProvider}",
                        ['serviceProvider' => $this->getServiceProvider()]
                    )
                );
            }

            $this->setAccessToken($json->access_token);
            $this->setAccessTokenUpdated(true);

            $this->onAfterFetchAccessToken($json);
        } catch (RequestException $e) {
            $responseBody = (string) $e->getResponse()->getBody();

            $this->getLogger()->log(LoggerInterface::LEVEL_ERROR, $responseBody, self::LOG_CATEGORY);
            $this->getLogger()->log(LoggerInterface::LEVEL_ERROR, $e->getMessage(), self::LOG_CATEGORY);

            throw $e;
        }

        return $this->getAccessToken();
    }

    /**
     * Perform anything necessary before this integration is saved
     *
     * @param IntegrationStorageInterface $model
     */
    public function onBeforeSave(IntegrationStorageInterface $model)
    {
        $clientId     = $this->getClientId();
        $clientSecret = $this->getClientSecret();
        $username     = $this->getUsername();
        $password     = $this->getPassword();

        // If one of these isn't present, we just return void
        if (!$clientId || !$clientSecret || !$username || !$password) {
            return;
        }

        $this->fetchAccessToken();
        $model->updateAccessToken($this->getAccessToken());
        $model->updateSettings($this->getSettings());
    }

    /**
     * Push objects to the CRM
     *
     * @param array $keyValueList
     *
     * @return bool
     * @throws \Exception
     */
    public function pushObject(array $keyValueList): bool
    {
        $client   = new Client();
        $endpoint = $this->getEndpoint('/sobjects/Lead');

        $setOwner = $this->getSetting(self::SETTING_LEAD_OWNER);

        try {
            $response = $client->post(
                $endpoint,
                [
                    'headers' => [
                        'Authorization'      => 'Bearer ' . $this->getAccessToken(),
                        'Accept'             => 'application/json',
                        'Sforce-Auto-Assign' => $setOwner ? 'TRUE' : 'FALSE',
                    ],
                    'json'    => $keyValueList,
                ]
            );

            return $response->getStatusCode() === 201;
        } catch (RequestException $e) {
            $responseBody = (string) $e->getResponse()->getBody();

            $this->getLogger()->log(LoggerInterface::LEVEL_ERROR, $responseBody, self::LOG_CATEGORY);
            $this->getLogger()->log(LoggerInterface::LEVEL_ERROR, $e->getMessage(), self::LOG_CATEGORY);

            if ($e->getResponse()->getStatusCode() === 400) {
                $errors = json_decode((string) $e->getResponse()->getBody());

                if (is_array($errors)) {
                    foreach ($errors as $error) {
                        if (strtoupper($error->errorCode) === 'REQUIRED_FIELD_MISSING') {
                            return false;
                        }
                    }

                }
            }

            throw $e;
        }
    }

    /**
     * Check if it's possible to connect to the API
     *
     * @return bool
     */
    public function checkConnection(): bool
    {
        $client   = new Client();
        $endpoint = $this->getEndpoint('/');

        try {
            $response = $client->get(
                $endpoint,
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->getAccessToken(),
                        'Content-Type'  => 'application/json',
                    ],
                ]
            );
            $json     = json_decode((string) $response->getBody(), true);

            return !empty($json);
        } catch (RequestException $exception) {
            throw new IntegrationException($exception->getMessage(), $exception->getCode(), $exception->getPrevious());
        }
    }

    /**
     * Fetch the custom fields from the integration
     *
     * @return FieldObject[]
     */
    public function fetchFields(): array
    {
        $client = new Client();

        try {
            $response = $client->get(
                $this->getEndpoint('/sobjects/Lead/describe'),
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->getAccessToken(),
                        'Content-Type'  => 'application/json',
                    ],
                ]
            );
        } catch (RequestException $e) {
            $responseBody = (string) $e->getResponse()->getBody();

            $this->getLogger()->log(LoggerInterface::LEVEL_ERROR, $responseBody, self::LOG_CATEGORY);
            $this->getLogger()->log(LoggerInterface::LEVEL_ERROR, $e->getMessage(), self::LOG_CATEGORY);

            return [];
        }

        $data = json_decode((string) $response->getBody());

        $fieldList = [];
        foreach ($data->fields as $field) {
            if (!$field->updateable || !empty($field->referenceTo)) {
                continue;
            }

            $type = null;
            switch ($field->type) {
                case 'string':
                case 'textarea':
                case 'email':
                case 'url':
                case 'address':
                case 'picklist':
                    $type = FieldObject::TYPE_STRING;
                    break;

                case 'boolean':
                    $type = FieldObject::TYPE_BOOLEAN;
                    break;

                case 'multipicklist':
                    $type = FieldObject::TYPE_ARRAY;
                    break;

                case 'number':
                case 'phone':
                case 'currency':
                    $type = FieldObject::TYPE_NUMERIC;
                    break;
            }

            if (null === $type) {
                continue;
            }

            $fieldObject = new FieldObject(
                $field->name,
                $field->label,
                $type,
                !$field->nillable
            );

            $fieldList[] = $fieldObject;
        }

        return $fieldList;
    }

    /**
     * Initiate a token refresh and fetch a refreshed token
     * Returns true on success
     *
     * @return bool
     * @throws IntegrationException
     */
    public function refreshToken(): bool
    {
        return (bool) $this->fetchAccessToken();
    }

    /**
     * @param FieldObject $fieldObject
     * @param mixed|null  $value
     *
     * @return bool|string
     */
    public function convertCustomFieldValue(FieldObject $fieldObject, $value = null)
    {
        if ($fieldObject->getType() === FieldObject::TYPE_ARRAY) {
            return is_array($value) ? implode(';', $value) : $value;
        }

        return parent::convertCustomFieldValue($fieldObject, $value);
    }

    /**
     * @param \stdClass $responseData
     *
     * @throws CRMIntegrationNotFoundException
     */
    protected function onAfterFetchAccessToken(\stdClass $responseData)
    {
        if (!isset($responseData->instance_url)) {
            throw new CRMIntegrationNotFoundException("Salesforce response data doesn't contain the instance URL");
        }

        $pattern = '/https:\/\/([A-Za-z0-9\-]+)\./';

        preg_match($pattern, $responseData->instance_url, $matches);

        if (!isset($matches[1])) {
            throw new CRMIntegrationNotFoundException(
                sprintf("Could not pull the instance from '%s'", $responseData->instance_url)
            );
        }

        $this->setSetting(self::SETTING_INSTANCE, $matches[1]);
    }

    /**
     * URL pointing to the OAuth2 authorization endpoint
     *
     * @return string
     */
    protected function getAuthorizeUrl(): string
    {
        return 'https://' . $this->getLoginUrl() . '.salesforce.com/services/oauth2/authorize';
    }

    /**
     * URL pointing to the OAuth2 access token endpoint
     *
     * @return string
     */
    protected function getAccessTokenUrl(): string
    {
        return 'https://' . $this->getLoginUrl() . '.salesforce.com/services/oauth2/token';
    }

    /**
     * @return string
     */
    protected function getApiRootUrl(): string
    {
        $instance        = $this->getSetting(self::SETTING_INSTANCE);
        $usingCustomUrls = $this->getSetting(self::SETTING_CUSTOM_URL);

        return sprintf(
            'https://%s%s.salesforce.com/services/data/v20.0/',
            $instance,
            ($usingCustomUrls ? '.my' : '')
        );
    }

    /**
     * @return string
     */
    private function getLoginUrl(): string
    {
        $isSandboxMode = $this->getSetting(self::SETTING_SANDBOX);

        if ($isSandboxMode) {
            return 'test';
        }

        return 'login';
    }

    /**
     * @return mixed|null
     */
    private function getClientId()
    {
        return $this->getSetting(self::SETTING_CLIENT_ID);
    }

    /**
     * @return mixed|null
     */
    private function getClientSecret()
    {
        return $this->getSetting(self::SETTING_CLIENT_SECRET);
    }

    /**
     * @return mixed|null
     */
    private function getUsername()
    {
        return $this->getSetting(self::SETTING_USER_LOGIN);
    }

    /**
     * @return mixed|null
     */
    private function getPassword()
    {
        return $this->getSetting(self::SETTING_USER_PASSWORD);
    }
}
