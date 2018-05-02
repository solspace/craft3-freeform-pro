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

namespace Solspace\FreeformPro\Integrations\MailingLists;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Solspace\Freeform\Library\Exceptions\Integrations\IntegrationException;
use Solspace\Freeform\Library\Integrations\DataObjects\FieldObject;
use Solspace\Freeform\Library\Integrations\IntegrationStorageInterface;
use Solspace\Freeform\Library\Integrations\MailingLists\AbstractMailingListIntegration;
use Solspace\Freeform\Library\Integrations\MailingLists\DataObjects\ListObject;
use Solspace\Freeform\Library\Integrations\SettingBlueprint;
use Solspace\Freeform\Library\Logging\LoggerInterface;

class CampaignMonitor extends AbstractMailingListIntegration
{
    const TITLE        = 'Campaign Monitor';
    const LOG_CATEGORY = 'Campaign Monitor';

    const SETTING_API_KEY   = 'api_key';
    const SETTING_CLIENT_ID = 'client_id';

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
                'Enter your Campaign Monitor API key here.',
                true
            ),
            new SettingBlueprint(
                SettingBlueprint::TYPE_TEXT,
                self::SETTING_CLIENT_ID,
                'Client ID',
                'Enter your Campaign Monitor Client ID here.',
                true
            ),
        ];
    }

    /**
     * Returns the MailingList service provider short name
     * i.e. - MailChimp, Constant Contact, etc...
     *
     * @return string
     */
    public function getServiceProvider(): string
    {
        return 'Campaign Monitor';
    }

    /**
     * Check if it's possible to connect to the API
     *
     * @return bool
     */
    public function checkConnection(): bool
    {
        $client = new Client();

        try {
            $response = $client->get(
                $this->getEndpoint('/clients/' . $this->getClientID() . '.json'),
                [
                    'auth' => [$this->getAccessToken(), 'freeform'],
                ]
            );

            $json = json_decode((string) $response->getBody());

            return isset($json->ApiKey) && !empty($json->ApiKey);
        } catch (RequestException $exception) {
            $responseBody = (string) $exception->getResponse()->getBody();

            $this->getLogger()->log(LoggerInterface::LEVEL_ERROR, $responseBody, self::LOG_CATEGORY);
            $this->getLogger()->log(LoggerInterface::LEVEL_ERROR, $exception->getMessage(), self::LOG_CATEGORY);

            throw new IntegrationException($exception->getMessage(), $exception->getCode(), $exception->getPrevious());
        }
    }

    /**
     * Push emails to a specific mailing list for the service provider
     *
     * @param ListObject $mailingList
     * @param array      $emails
     * @param array      $mappedValues
     *
     * @return bool
     * @throws IntegrationException
     */
    public function pushEmails(ListObject $mailingList, array $emails, array $mappedValues): bool
    {
        $client   = new Client();
        $endpoint = $this->getEndpoint("/subscribers/{$mailingList->getId()}.json");

        try {
            $customFields = [];
            foreach ($mappedValues as $key => $value) {
                if ($key === 'Name') {
                    continue;
                }

                if (is_array($value)) {
                    foreach ($value as $subValue) {
                        $customFields[] = [
                            'Key'   => $key,
                            'Value' => $subValue,
                        ];
                    }
                } else {
                    $customFields[] = [
                        'Key'   => $key,
                        'Value' => $value,
                    ];
                }
            }

            foreach ($emails as $email) {
                $data = [
                    'EmailAddress'                           => $email,
                    'Name'                                   => $mappedValues['Name'] ?? '',
                    'CustomFields'                           => $customFields,
                    'Resubscribe'                            => true,
                    'RestartSubscriptionBasedAutoresponders' => true,
                ];

                $client->post(
                    $endpoint,
                    [
                        'auth' => [$this->getAccessToken(), 'freeform'],
                        'json' => $data,
                    ]
                );
            }
        } catch (RequestException $e) {
            $responseBody = (string) $e->getResponse()->getBody();

            $this->getLogger()->log(LoggerInterface::LEVEL_ERROR, $responseBody, self::LOG_CATEGORY);
            $this->getLogger()->log(LoggerInterface::LEVEL_ERROR, $e->getMessage(), self::LOG_CATEGORY);

            throw new IntegrationException(
                $this->getTranslator()->translate('Could not connect to API endpoint')
            );
        }

        return true;
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
     */
    public function fetchAccessToken(): string
    {
        return $this->getSetting(self::SETTING_API_KEY);
    }

    /**
     * Perform anything necessary before this integration is saved
     *
     * @param IntegrationStorageInterface $model
     *
     * @throws IntegrationException
     */
    public function onBeforeSave(IntegrationStorageInterface $model)
    {
        $model->updateAccessToken($this->getSetting(self::SETTING_API_KEY));
    }

    /**
     * Makes an API call that fetches mailing lists
     * Builds ListObject objects based on the results
     * And returns them
     *
     * @return ListObject[]
     * @throws IntegrationException
     */
    protected function fetchLists(): array
    {
        $client   = new Client();
        $endpoint = $this->getEndpoint('/clients/' . $this->getClientID() . '/lists.json');

        try {
            $response = $client->get(
                $endpoint,
                [
                    'auth' => [$this->getAccessToken(), 'freeform'],
                ]
            );
        } catch (RequestException $e) {
            $responseBody = (string) $e->getResponse()->getBody();

            $this->getLogger()->log(LoggerInterface::LEVEL_ERROR, $responseBody, self::LOG_CATEGORY);
            $this->getLogger()->log(LoggerInterface::LEVEL_ERROR, $e->getMessage(), self::LOG_CATEGORY);

            throw new IntegrationException(
                $this->getTranslator()->translate('Could not connect to API endpoint')
            );
        }

        $status = $response->getStatusCode();
        if ($status !== 200) {
            throw new IntegrationException(
                $this->getTranslator()->translate(
                    'Could not fetch {serviceProvider} lists',
                    ['serviceProvider' => $this->getServiceProvider()]
                )
            );
        }

        $json = json_decode((string) $response->getBody());

        $lists = [];
        if (is_array($json)) {
            foreach ($json as $list) {
                if (isset($list->ListID, $list->Name)) {
                    $lists[] = new ListObject(
                        $this,
                        $list->ListID,
                        $list->Name,
                        $this->fetchFields($list->ListID),
                        0
                    );
                }
            }
        }

        return $lists;
    }

    /**
     * Fetch all custom fields for each list
     *
     * @param string $listId
     *
     * @return FieldObject[]
     * @throws IntegrationException
     */
    protected function fetchFields($listId): array
    {
        $client   = new Client();
        $endpoint = $this->getEndpoint("/lists/$listId/customfields.json");

        try {
            $response = $client->get(
                $endpoint,
                [
                    'auth' => [$this->getAccessToken(), 'freeform'],
                ]
            );
        } catch (RequestException $e) {
            $responseBody = (string) $e->getResponse()->getBody();

            $this->getLogger()->log(LoggerInterface::LEVEL_ERROR, $responseBody, self::LOG_CATEGORY);
            $this->getLogger()->log(LoggerInterface::LEVEL_ERROR, $e->getMessage(), self::LOG_CATEGORY);

            throw new IntegrationException(
                $this->getTranslator()->translate('Could not connect to API endpoint')
            );
        }

        $json = json_decode((string) $response->getBody());

        $fieldList = [
            new FieldObject('Name', 'Name', FieldObject::TYPE_STRING, false),
        ];

        if (is_array($json)) {
            foreach ($json as $field) {
                switch ($field->DataType) {
                    case 'Text':
                    case 'MultiSelectOne':
                        $type = FieldObject::TYPE_STRING;
                        break;

                    case 'Number':
                        $type = FieldObject::TYPE_NUMERIC;
                        break;

                    case 'MultiSelectMany':
                        $type = FieldObject::TYPE_ARRAY;
                        break;

                    default:
                        $type = null;
                        break;
                }

                if (null === $type) {
                    continue;
                }

                $fieldList[] = new FieldObject(
                    str_replace(['[', ']'], '', $field->Key),
                    $field->FieldName,
                    $type,
                    false
                );
            }
        }

        return $fieldList;
    }

    /**
     * Returns the API root url without endpoints specified
     *
     * @return string
     * @throws IntegrationException
     */
    protected function getApiRootUrl(): string
    {
        return 'https://api.createsend.com/api/v3.1/';
    }

    /**
     * @return string
     */
    private function getClientID(): string
    {
        return $this->getSetting(self::SETTING_CLIENT_ID);
    }
}
