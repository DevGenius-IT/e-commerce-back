<?php

return [
  /*
    |--------------------------------------------------------------------------
    | Micro-Services
    |--------------------------------------------------------------------------
    |
    | Here you may define the configuration for each of the micro-services
    | that are used in the application. A micro-service is a small piece of
    | software that is designed to perform a single function or a group of
    | related functions. These services are used to build larger software
    | systems and are typically accessed via HTTP requests.
    |
    */

  "auth" => [
    "base_uri" => env("AUTH_SERVICE_URL"),
    "secret" => env("AUTH_SERVICE_SECRET"),
  ],
  
  "addresses" => [
    "base_uri" => env("ADDRESSES_SERVICE_URL"),
    "secret" => env("ADDRESSES_SERVICE_SECRET"),
  ],
  
  "products" => [
    "base_uri" => env("PRODUCTS_SERVICE_URL"),
    "secret" => env("PRODUCTS_SERVICE_SECRET"),
  ],

  "baskets" => [
    "base_uri" => env("BASKETS_SERVICE_URL"),
    "secret" => env("BASKETS_SERVICE_SECRET"),
  ],

  "orders" => [
    "base_uri" => env("ORDERS_SERVICE_URL"),
    "secret" => env("ORDERS_SERVICE_SECRET"),
  ],

  "messages-broker" => [
    "base_uri" => env("MESSAGES_BROKER_URL"),
    "secret" => env("MESSAGES_BROKER_SECRET"),
  ],

  /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

  "postmark" => [
    "token" => env("POSTMARK_TOKEN"),
  ],

  "ses" => [
    "key" => env("AWS_ACCESS_KEY_ID"),
    "secret" => env("AWS_SECRET_ACCESS_KEY"),
    "region" => env("AWS_DEFAULT_REGION", "us-east-1"),
  ],

  "resend" => [
    "key" => env("RESEND_KEY"),
  ],

  "slack" => [
    "notifications" => [
      "bot_user_oauth_token" => env("SLACK_BOT_USER_OAUTH_TOKEN"),
      "channel" => env("SLACK_BOT_USER_DEFAULT_CHANNEL"),
    ],
  ],
];
