<?php

namespace Modules\Kiosk\App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class ClickSendService
{
  private PendingRequest $http;
  public function __construct()
  {
    $this->http = Http::withBasicAuth(config('kiosk.clicksend.username'), config('kiosk.clicksend.api_key'));
  }

  public function sendSms(string $to, string $message): bool
  {
    $response = $this->http->post('https://rest.clicksend.com/v3/sms/send', [
      'messages' => [
        [
          'to' => $to,
          'from' => 'HCKiosk',
          'body' => $message
        ]
      ]
    ]);

    return $response->ok();
  }

  public function sendEmail(string $to, string $subject, string $message): bool
  {
    $response = $this->http->post('https://rest.clicksend.com/v3/email/send', [
      'to' => [
        [
          'email' => $to,
        ]
      ],
      'from' => [
        'email_address_id' => config('kiosk.clicksend.email_address_id'),
        'name' => config('kiosk.clicksend.email_address_name')
      ],
      'subject' => $subject,
      'body' => $message,
    ]);

    return $response->ok();
  }

  public function storeUpload(string $fileContents): string
  {
    $response = $this->http->post('https://rest.clicksend.com/v3/uploads', [
      'content' => base64_encode($fileContents)
    ]);
    if (!$response->ok()) {
      return '';
    }
    return $response->json()['data']['_url'];
  }

  public function sendLetter(string $url, string $addressName, string $addressLine1, string $addressLine2 = '', string $postcode, string $city, string $county = ''): bool
  {
    $response = $this->http->post('https://rest.clicksend.com/v3/post/letters/send', [
      'file_url' => $url,
      'recipients' => [
        [
          'address_name' => $addressName,
          'address_line_1' => $addressLine1,
          'address_line_2' => $addressLine2,
          'address_postal_code' => $postcode,
          'address_city' => $city,
          'address_state' => $county,
          'address_country' => 'GB',
          'return_address_id' => config('kiosk.clicksend.letter_return_id'),
        ]
      ],
      'priority_post' => 1
    ]);

    return $response->ok();
  }
}

