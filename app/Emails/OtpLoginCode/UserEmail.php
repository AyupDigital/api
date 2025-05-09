<?php

namespace App\Emails\OtpLoginCode;

use App\Emails\Email;

class UserEmail extends Email
{
    protected function getTemplateId(): string
    {
        return config('gov_uk_notify.notifications_template_ids.otp_login_code.email');
    }

    /**
     * {@inheritDoc}
     */
    public function getContent(): string
    {
        return 'emails.otp.user.content';
    }

    /**
     * {@inheritDoc}
     */
    public function getSubject(): string
    {
        return 'emails.otp.user.subject';
    }
}
