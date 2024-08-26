<?php

namespace NW\WebService\References\Operations\Notification;

use InvalidArgumentException;
use LogicException;

class TsReturnOperation extends ReferencesOperation
{
    public const TYPE_NEW = 1;
    public const TYPE_CHANGE = 2;

    public const MESSAGE_TYPE_EMAIL = 0;

    private const DATA_PARAM_NAME = 'data';

    private const EVENT_NAME = 'tsGoodsReturn';

    private bool $isEmployeeNotified = false;
    private bool $isClientNotifiedByMail = false;
    private bool $isClientNotifiedBySMS = false;

    private string $smsError = '';

    /**
     * @throws InvalidArgumentException
     * @throws LogicException
     */
    public function doOperation(): array
    {
        $this->clearStatuses();
        $requestDto = $this->createRequestDto();

        $reseller = $this->getSeller($requestDto->getResellerId());
        $client = $this->getCustomer($requestDto->getClientId());
        $creator = $this->getEmployee($requestDto->getCreatorId());
        $expert = $this->getEmployee($requestDto->getExpertId());

        $messageData = $this->buildMessageData(
            $requestDto,
            $reseller,
            $client,
            $creator,
            $expert
        );

        $emailFrom = getResellerEmailFrom();

        $this->sendEmployeeMails($requestDto, $emailFrom, $messageData);

        if ($requestDto->isNotificationTypeChange() && $requestDto->getDifferencesTo()) {
            $this->sendClientMail($requestDto, $emailFrom, $client, $messageData);
            $this->sendClientSms($requestDto, $client, $messageData);
        }

        return $this->buildAnswer();
    }

    private function clearStatuses(): void
    {
        $this->isEmployeeNotified = false;
        $this->isClientNotifiedByMail = false;
        $this->isClientNotifiedBySMS = false;
        $this->smsError = '';
    }

    /**
     * @throws InvalidArgumentException
     */
    private function createRequestDto(): RequestDto
    {
        $data = (array)$this->getRequest(static::DATA_PARAM_NAME);
        return RequestDto::create($data);
    }

    private function getSeller(int $getResellerId): Contractor
    {
        return Seller::getById($getResellerId);
    }

    /**
     * @throws LogicException
     */
    private function getCustomer(int $getClientId): Contractor
    {
        $customer = Contractor::getById($getClientId);
        if ($customer->type !== Contractor::TYPE_CUSTOMER) {
            throw new LogicException('This contractor must be a Customer');
        }

        return $customer;
    }

    private function getEmployee(int $getCreatorId): Contractor
    {
        return Employee::getById($getCreatorId);
    }

    private function buildMessageData(
        RequestDto $requestDto,
        Contractor $reseller,
        Contractor $client,
        Contractor $creator,
        Contractor $expert
    ): array {

        $clientFullName = $client->getFullName();
        if (empty($client->getFullName())) {
            $clientFullName = $client->name;
        }

        $differences = $this->buildDifferences($requestDto);

        return [
            'COMPLAINT_ID' => $requestDto->getComplaintId(),
            'COMPLAINT_NUMBER' => $requestDto->getComplaintNumber(),
            'CREATOR_ID' => $creator->id,
            'CREATOR_NAME' => $creator->getFullName(),
            'EXPERT_ID' => $expert->id,
            'EXPERT_NAME' => $expert->getFullName(),
            'CLIENT_ID' => $client->id,
            'CLIENT_NAME' => $clientFullName,
            'CONSUMPTION_ID' => $requestDto->getConsumptionId(),
            'CONSUMPTION_NUMBER' => $requestDto->getConsumptionNumber(),
            'AGREEMENT_NUMBER' => $requestDto->getAgreementNumber(),
            'DATE' => $requestDto->getDate(),
            'DIFFERENCES' => $differences,
        ];
    }

    /**
     * @throws LogicException
     */
    private function buildDifferences(RequestDto $requestDto): string
    {
        if ($requestDto->isNotificationTypeNew()) {
            $name = 'NewPositionAdded';
            $differenceData = null;
        } elseif ($requestDto->isNotificationTypeChange()) {
            $name = 'PositionStatusHasChanged';
            $differenceData = [
                'FROM' => Status::getName($requestDto->getDifferencesFrom()),
                'TO' => Status::getName($requestDto->getDifferencesTo()),
            ];
        } else {
            throw new LogicException('Differences are not valid');
        }

        return __($name, $differenceData, $requestDto->getResellerId());
    }

    private function sendEmployeeMails(RequestDto $requestDto, string $emailFrom, array $messageData): void
    {
        $resellerId = $requestDto->getResellerId();
        $emailsTo = getEmailsByPermit($resellerId, self::EVENT_NAME);
        foreach ($emailsTo as $emailTo) {
            $res = MessagesClient::sendMessage(
                [
                    self::MESSAGE_TYPE_EMAIL => [
                        'emailFrom' => $emailFrom,
                        'emailTo' => $emailTo,
                        'subject' => __('complaintEmployeeEmailSubject', $messageData, $resellerId),
                        'message' => __('complaintEmployeeEmailBody', $messageData, $resellerId),
                    ],
                ],
                $resellerId,
                null,
                NotificationEvents::CHANGE_RETURN_STATUS
            );

            $this->isEmployeeNotified = true;
        }
    }

    private function sendClientMail(RequestDto $requestDto, string $emailFrom, Contractor $client, array $messageData): void
    {
        $resellerId = $requestDto->getResellerId();
        if (!empty($emailFrom) && !empty($client->email)) {
            $res = MessagesClient::sendMessage(
                [
                    self::MESSAGE_TYPE_EMAIL => [
                        'emailFrom' => $emailFrom,
                        'emailTo' => $client->email,
                        'subject' => __('complaintClientEmailSubject', $messageData, $resellerId),
                        'message' => __('complaintClientEmailBody', $messageData, $resellerId),
                    ],
                ],
                $resellerId,
                $client->id,
                NotificationEvents::CHANGE_RETURN_STATUS,
                $requestDto->getDifferencesTo()
            );

            $this->isClientNotifiedByMail = true;
        }
    }

    private function sendClientSms(RequestDto $requestDto, Contractor $client, array $messageData): void
    {
        if (!empty($client->mobile)) {
            $res = NotificationManager::send(
                $requestDto->getResellerId(),
                $client->id,
                NotificationEvents::CHANGE_RETURN_STATUS,
                $requestDto->getDifferencesTo(),
                $messageData,
                $error
            );
            if ($res) {
                $this->isClientNotifiedBySMS = true;
                return;
            }
            if (!empty($error)) {
                $this->smsError = $error;
            }
        }
    }

    private function buildAnswer(): array
    {
        return [
            'notificationEmployeeByEmail' => $this->isEmployeeNotified,
            'notificationClientByEmail' => $this->isClientNotifiedByMail,
            'notificationClientBySms' => [
                'isSent' => $this->isClientNotifiedBySMS,
                'message' => $this->smsError,
            ],
        ];
    }

}
