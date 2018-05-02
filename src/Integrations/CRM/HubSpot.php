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
use Solspace\Freeform\Library\Exceptions\Integrations\IntegrationException;
use Solspace\Freeform\Library\Integrations\CRM\AbstractCRMIntegration;
use Solspace\Freeform\Library\Integrations\DataObjects\FieldObject;
use Solspace\Freeform\Library\Integrations\IntegrationStorageInterface;
use Solspace\Freeform\Library\Integrations\SettingBlueprint;
use Solspace\Freeform\Library\Logging\LoggerInterface;

class HubSpot extends AbstractCRMIntegration
{
    const SETTING_API_KEY = 'api_key';
    const TITLE           = 'HubSpot';
    const LOG_CATEGORY    = 'HubSpot';

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
                SettingBlueprint::TYPE_TEXT,
                self::SETTING_API_KEY,
                'API Key',
                'Enter your HubSpot API key here.',
                true
            ),
        ];
    }

    /**
     * Push objects to the CRM
     *
     * @param array $keyValueList
     *
     * @return bool
     */
    public function pushObject(array $keyValueList): bool
    {
        $client   = new Client();
        $endpoint = $this->getEndpoint('/deals/v1/deal/');

        $dealProps    = [];
        $contactProps = [];
        $companyProps = [];

        foreach ($keyValueList as $key => $value) {
            preg_match('/^(\w+)___(.+)$/', $key, $matches);

            list ($all, $target, $propName) = $matches;

            switch ($target) {
                case 'contact':
                    $contactProps[] = ['value' => $value, 'property' => $propName];
                    break;

                case 'company':
                    $companyProps[] = ['value' => $value, 'name' => $propName];
                    break;

                case 'deal':
                    $dealProps[] = ['value' => $value, 'name' => $propName];
                    break;
            }
        }

        $contactId = null;
        if ($contactProps) {
            try {
                $response = $client->post(
                    $this->getEndpoint('/contacts/v1/contact'),
                    [
                        'json'  => ['properties' => $contactProps],
                        'query' => ['hapikey' => $this->getAccessToken()],
                    ]
                );

                $json = json_decode((string) $response->getBody());
                if (isset($json->vid)) {
                    $contactId = $json->vid;
                }
            } catch (RequestException $e) {
                if ($e->getResponse()) {
                    $json = json_decode((string) $e->getResponse()->getBody());
                    if (isset($json->error, $json->identityProfile) && $json->error === 'CONTACT_EXISTS') {
                        $contactId = $json->identityProfile->vid;
                    } else {
                        $responseBody = (string) $e->getResponse()->getBody();

                        $this->getLogger()->log(LoggerInterface::LEVEL_ERROR, $responseBody, self::LOG_CATEGORY);
                        $this->getLogger()->log(LoggerInterface::LEVEL_ERROR, $e->getMessage(), self::LOG_CATEGORY);
                    }
                }
            } catch (\Exception $e) {
                $this->getLogger()->log(LoggerInterface::LEVEL_WARNING, $e->getMessage(), self::LOG_CATEGORY);
            }
        }

        $companyId = null;
        if ($companyProps) {
            try {
                $response = $client->post(
                    $this->getEndpoint('companies/v2/companies'),
                    [
                        'json'  => ['properties' => $companyProps],
                        'query' => ['hapikey' => $this->getAccessToken()],
                    ]
                );

                $json = json_decode((string) $response->getBody());
                if (isset($json->companyId)) {
                    $companyId = $json->companyId;
                }
            } catch (RequestException $e) {
                $responseBody = (string) $e->getResponse()->getBody();

                $this->getLogger()->log(LoggerInterface::LEVEL_ERROR, $responseBody, self::LOG_CATEGORY);
                $this->getLogger()->log(LoggerInterface::LEVEL_ERROR, $e->getMessage(), self::LOG_CATEGORY);
            } catch (\Exception $e) {
                $this->getLogger()->log(LoggerInterface::LEVEL_WARNING, $e->getMessage(), self::LOG_CATEGORY);
            }
        }

        $deal = [
            'properties' => $dealProps,
        ];

        if ($companyId || $contactId) {
            $deal['associations'] = [];

            if ($companyId) {
                $deal['associations']['associatedCompanyIds'] = [$companyId];
            }

            if ($contactId) {
                $deal['associations']['associatedVids'] = [$contactId];
            }
        }

        $response = $client->post(
            $endpoint,
            [
                'json'  => $deal,
                'query' => ['hapikey' => $this->getAccessToken()],
            ]
        );

        return $response->getStatusCode() === 200;
    }

    /**
     * Check if it's possible to connect to the API
     *
     * @return bool
     */
    public function checkConnection(): bool
    {
        $client   = new Client();
        $endpoint = $this->getEndpoint('/contacts/v1/lists/all/contacts/all');

        try {
            $response = $client->get(
                $endpoint,
                [
                    'query' => ['hapikey' => $this->getAccessToken()],
                ]
            );

            $json = json_decode((string) $response->getBody(), true);

            return isset($json['contacts']);
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

        $response = $client->get(
            $this->getEndpoint('/properties/v1/deals/properties/'),
            ['query' => ['hapikey' => $this->getAccessToken()]]
        );

        $data = json_decode((string) $response->getBody());

        $fieldList = [
            new FieldObject('contact___email', 'Email', FieldObject::TYPE_STRING, false),
            new FieldObject('contact___firstname', 'First Name', FieldObject::TYPE_STRING, false),
            new FieldObject('contact___lastname', 'Last Name', FieldObject::TYPE_STRING, false),
            new FieldObject('contact___website', 'Website', FieldObject::TYPE_STRING, false),
            new FieldObject('contact___phone', 'Phone', FieldObject::TYPE_NUMERIC, false),
            new FieldObject('contact___address', 'Address', FieldObject::TYPE_STRING, false),
            new FieldObject('contact___city', 'City', FieldObject::TYPE_STRING, false),
            new FieldObject('contact___state', 'State', FieldObject::TYPE_STRING, false),
            new FieldObject('contact___zip', 'Zip', FieldObject::TYPE_NUMERIC, false),
            new FieldObject('company___name', 'Company Name', FieldObject::TYPE_STRING, false),
            new FieldObject('company___description', 'Company Description', FieldObject::TYPE_STRING, false),
        ];

        foreach ($data as $field) {
            if ($field->readOnlyValue || $field->hidden || $field->calculated) {
                continue;
            }

            $type = null;
            switch ($field->type) {
                case 'string':
                    $type = FieldObject::TYPE_STRING;
                    break;

                case 'number':
                    $type = FieldObject::TYPE_NUMERIC;
                    break;
            }

            if (null === $type) {
                continue;
            }

            $fieldObject = new FieldObject(
                'deal___' . $field->name,
                $field->label,
                $type,
                false
            );

            $fieldList[] = $fieldObject;
        }

        return $fieldList;
    }

    /**
     * Authorizes the application
     * Returns the access_token
     *
     * @return string
     * @throws IntegrationException
     */
    public function fetchAccessToken(): string
    {
        return $this->getSetting(self::SETTING_API_KEY);
    }

    /**
     * A method that initiates the authentication
     */
    public function initiateAuthentication()
    {
    }

    /**
     * Perform anything necessary before this integration is saved
     *
     * @param IntegrationStorageInterface $model
     */
    public function onBeforeSave(IntegrationStorageInterface $model)
    {
        $model->updateAccessToken($this->getSetting(self::SETTING_API_KEY));
    }

    /**
     * @return string
     */
    protected function getApiRootUrl(): string
    {
        return 'https://api.hubapi.com/';
    }
}
