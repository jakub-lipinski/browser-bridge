<?php

namespace App\Services;

use App\Models\Device;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DeviceResolver
{
    public function required(string $uuid): Device
    {
        $device = Device::query()->where('uuid', $uuid)->first();

        if (! $device instanceof Device) {
            throw new NotFoundHttpException('Device was not found.');
        }

        $device->forceFill(['last_seen_at' => now()])->save();

        return $device;
    }
}
