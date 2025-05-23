<?php

namespace App\SmsSenders;

use App\Contracts\SmsSender;
use App\Sms\Sms;
use Illuminate\Support\Facades\Date;

class LogSmsSender implements SmsSender
{
    public function send(Sms $sms)
    {
        logger()->debug('SMS sent via Log SMS at ['.Date::now()->toDateTimeString().']', [
            'to' => $sms->to,
            'templateId' => $sms->templateId,
            'values' => $sms->values,
            'reference' => $sms->reference,
            'senderId' => $sms->senderId,
        ]);

        $sms->notification->update(['message' => 'Sent by log sender - no message content provided']);
    }
}
