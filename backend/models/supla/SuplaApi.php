<?php

namespace suplascripts\models\supla;

use Supla\ApiClient\SuplaApiClient;
use suplascripts\app\Application;
use suplascripts\models\User;

class SuplaApi
{
    /** @var SuplaApiClient */
    private $client;

    private $devices;

    protected function __construct(User $user)
    {
        $apiCredentials = $user->getApiCredentials();
        $this->client = new SuplaApiClient($apiCredentials, false, false, false);
    }

    public static function getInstance(User $user)
    {
        $readOnly = Application::getInstance()->getSetting('readOnly', true);
        return $readOnly
            ? new SuplaApiReadOnly($user)
            : new self($user);
    }

    public function getDevices(): array
    {
        if (!$this->devices) {
            $response = $this->client->ioDevices();
            $this->handleError($response);
            $this->devices = $response->iodevices;
        }
        return $this->devices;
    }

    public function getChannelWithState(int $channelId)
    {
        foreach ($this->getDevices() as $device) {
            foreach ($device->channels as $channel) {
                if ($channel->id == $channelId) {
                    $state = $this->getChannelState($channelId);
                    return (object)array_merge((array)$channel, (array)$state);
                }
            }
        }
        throw new SuplaApiException($this->client, 'Could not get status for channel #' . $channelId);
    }

    public function getChannelState(int $channelId)
    {
        $state = $this->client->channel($channelId);
        $this->handleError($state);
        return $state;
    }

    public function turnOn(int $channelId)
    {
        $result = $this->client->channelTurnOn($channelId);
        if ($result === false) {
            $result = $this->toggleUnpredictable($channelId);
        }
        return $result !== false;
    }

    public function turnOff(int $channelId)
    {
        $result = $this->client->channelTurnOff($channelId);
        if ($result === false) {
            $result = $this->toggleUnpredictable($channelId);
        }
        return $result !== false;
    }

    public function toggle(int $channelId)
    {
        $state = $this->getChannelState($channelId);
        $this->handleError($state);
        if (isset($state->on)) {
            return $state->on ? $this->turnOff($channelId) : $this->turnOn($channelId);
        } else {
            return $this->toggleUnpredictable($channelId);
        }
    }

    private function toggleUnpredictable(int $channelId)
    {
        $result = $this->client->channelOpenClose($channelId);
        if ($result === false) {
            $result = $this->client->channelOpen($channelId);
        }
        return $result !== false;
    }

    private function handleError($response)
    {
        if (!$response) {
            throw new SuplaApiException($this->client);
        }
    }
}
