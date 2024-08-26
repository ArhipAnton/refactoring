<?php

namespace NW\WebService\References\Operations\Notification;

use InvalidArgumentException;

class RequestDto
{
    private const TYPE_NEW = 1;
    private const TYPE_CHANGE = 2;

    private const AVAILABLE_STATUSES = [0, 1, 2];

    private int $resellerId;
    private int $notificationType;
    private int $clientId;
    private int $creatorId;
    private int $expertId;
    private int $differencesFrom;
    private int $differencesTo;
    private int $complaintId;
    private string $complaintNumber;
    private int $consumptionId;
    private string $consumptionNumber;
    private string $agreementNumber;
    private string $date;

    /**
     * @throws InvalidArgumentException
     */
    private function __construct(
        int $resellerId,
        int $notificationType,
        int $clientId,
        int $creatorId,
        int $expertId,
        $diffFrom,
        $diffTo,
        int $complaintId,
        string $complaintNumber,
        int $consumptionId,
        string $consumptionNumber,
        string $agreementNumber,
        string $date
    ) {

        if (!in_array($notificationType, [self::TYPE_NEW, self::TYPE_CHANGE])) {
            throw new InvalidArgumentException('Invalid notification type');
        }

        if ($notificationType === self::TYPE_CHANGE) {
            foreach ([$diffTo, $diffFrom] as $status) {
                if (!in_array($status, self::AVAILABLE_STATUSES, true)) {
                    throw new InvalidArgumentException('Invalid status');
                }
            }
            $this->differencesFrom = $diffFrom;
            $this->differencesTo = $diffTo;
        }

        $this->resellerId = $resellerId;
        $this->notificationType = $notificationType;
        $this->clientId = $clientId;
        $this->creatorId = $creatorId;
        $this->expertId = $expertId;
        $this->complaintId = $complaintId;
        $this->complaintNumber = $complaintNumber;
        $this->consumptionId = $consumptionId;
        $this->consumptionNumber = $consumptionNumber;
        $this->agreementNumber = $agreementNumber;
        $this->date = $date;
    }

    public function __set($name, $value)
    {
        $this->$name = $value;
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function create(array $data): self
    {
        $numericFields = [
            'resellerId',
            'notificationType',
            'clientId',
            'creatorId',
            'expertId',
            'complaintId',
            'consumptionId',
            'agreementId',
        ];
        foreach ($numericFields as $field) {
            if (!isset($data[$field])) {
                throw new InvalidArgumentException($field . ' field is required');
            }
            if (!is_int($data[$field]) || $data[$field] < 0) {
                throw new InvalidArgumentException($field . ' must be an positive integer');
            }
        }

        $stringFields = [
            'complaintNumber',
            'consumptionNumber',
            'date',
        ];
        foreach ($stringFields as $field) {
            if (!isset($data[$field])) {
                throw new InvalidArgumentException($field . ' field is required');
            }
            if (!is_string($data[$field])) {
                throw new InvalidArgumentException($field . ' must be a string');
            }
        }

        $differences = $data['differences'] ?? [];
        return new self(
            $data['resellerId'],
            $data['notificationType'],
            $data['clientId'],
            $data['creatorId'],
            $data['expertId'],
            $differences['from'] ?? null,
            $differences['to'] ?? null,
            $data['complaintId'],
            $data['complaintNumber'],
            $data['consumptionId'],
            $data['consumptionNumber'],
            $data['agreementId'],
            $data['date'] ?? ''
        );
    }

    public function isNotificationTypeNew(): bool
    {
        return $this->notificationType === self::TYPE_NEW;
    }

    public function isNotificationTypeChange(): bool
    {
        return $this->notificationType === self::TYPE_CHANGE;
    }

    public function getResellerId(): int
    {
        return $this->resellerId;
    }

    public function getClientId(): int
    {
        return $this->clientId;
    }

    public function getCreatorId(): int
    {
        return $this->creatorId;
    }

    public function getExpertId(): int
    {
        return $this->expertId;
    }

    public function getDifferencesFrom(): int
    {
        return $this->differencesFrom;
    }

    public function getDifferencesTo(): int
    {
        return $this->differencesTo;
    }

    public function getComplaintId(): int
    {
        return $this->complaintId;
    }

    public function getComplaintNumber(): string
    {
        return $this->complaintNumber;
    }

    public function getConsumptionId(): int
    {
        return $this->consumptionId;
    }

    public function getConsumptionNumber(): string
    {
        return $this->consumptionNumber;
    }

    public function getAgreementNumber(): string
    {
        return $this->agreementNumber;
    }

    public function getDate(): string
    {
        return $this->date;
    }

}
