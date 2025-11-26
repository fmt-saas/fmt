<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use communication\email\Mailbox;
use equal\auth\JWT;
use equal\http\HttpRequest;
use infra\server\Instance;

// announce script and fetch parameters values
[$params, $providers] = eQual::announce([
    'description'   => "Callback for receiving confirmation from Microsoft Outlook OAuth.",
    'help'          => "Script called after OAuth redirect from Microsoft.",
    'params'        => [
        'code' => [
            'type'          => 'string',
            'usage'         => 'text/plain:3000',
            'required'      => true
        ],
        'state' => [
            'type'          => 'string',
            'required'      => true
        ]
    ],
    'constants'     => [
        'BACKEND_URL',
        'MS_OUTLOOK_CLIENT_ID',
        'MS_OUTLOOK_CLIENT_SECRET',
        'AUTH_ACCESS_TOKEN_VALIDITY'
    ],
    'access'        => [
        'visibility' => 'public'
    ],
    'response'      => [
        'content-type'      => 'application/json',
        'charset'           => 'utf-8',
        'accept-origin'     => '*'
    ],
    'providers'     => ['context', 'auth', 'orm']
]);

['context' => $context, 'orm' => $om, 'auth' => $auth] = $providers;

/*
Example of received params:

    "state": "https://test1.fmtsolutions.be/",
    "code": "M.C522_BAY.2.U.ddaf3e1a-03fa-e995-9cad-0e428fc26d03",
    "scope": "openid profile offline_access email User.Read Mail.ReadWrite IMAP.AccessAsUser.All",
    "authuser": "1",
    "prompt": "consent",
    "system_info": "{\"resolution\":\"1920x1080\",\"platform\":\"Windows 19.0.0\",\"vendor\":\"Google Inc. (Intel)\",\"renderer\":\"ANGLE (Intel, Intel(R) Iris(R) Xe Graphics (0x0000A7A1) Direct3D11 vs_5_0 ps_5_0, D3D11)\"}",
    "access_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpZCI6MywiYW1yIjp7ImF1dGhfdHlwZSI6InB3ZCIsImF1dGhfbGV2ZWwiOjF9LCJleHAiOjE3NjM3NDU2OTJ9.yiqiey9NRKj6jZTktqgaph4kI6UB8Y9Xu046gC_5KQA"

*/

/* --------------------------------------------------------------------------
   1) Exchange authorization code for tokens (Microsoft OAuth)
   -------------------------------------------------------------------------- */

$tokenUrl = "https://login.microsoftonline.com/common/oauth2/v2.0/token";

$oauthRequest = new HttpRequest('POST ' . $tokenUrl);

$response = $oauthRequest
    ->header('Content-Type', 'application/x-www-form-urlencoded')
    ->setBody([
        'grant_type'    => 'authorization_code',
        'code'          => $params['code'],
        'redirect_uri'  => constant('BACKEND_URL') . '/oauth/outlook',
        'client_id'     => constant('MS_OUTLOOK_CLIENT_ID'),
        'client_secret' => constant('MS_OUTLOOK_CLIENT_SECRET'),
        // mandatory for MS token exchange
        'scope'         => 'openid profile offline_access email User.Read Mail.ReadWrite IMAP.AccessAsUser.All'
    ])
    ->send();
/*
Example of received response :

{
    "token_type": "Bearer",
    "scope": "openid profile email User.Read Mail.ReadWrite IMAP.AccessAsUser.All",
    "expires_in": 3599,
    "ext_expires_in": 3599,
    "access_token": "EwBYBMl6BAAUBKgm8k1UswUNwklmy2v7U/S+1fEAAct2V9fZDB7frSYbzZE9TQpqFASXGMVyKkpxfuuORIwIrcEMaaCW83Gr3XGv8VfgL2jv5GKPXZ45YjHfzVHDd9ZXNNm2wZJKgksQ5hr5BjahenAPClI0eEIHBdIDjz8gwp+indDNM9pbnKC25FG0QKpG8npe5LfPZ4zggmn68+ymm3AFhkt0U7tfz9bZxRtc8tKKvJ1s5++qR5mFo2wTsJTUKsZmDx4qoHTMz1aGzsn0ziJcUOZGrQ37GRNpr7V2ON6zsmutxuo9FumYHmnLOdefmndnAGJh1YdY2tM69mljykDnkaZxv+q9RU7yTyneElpaI7yJbyltSsYDupm2IK0QZgAAECTBDdarxws/xB9l5VCtsbEgA4IZNhedlnE1VUtCnDGc7YVK4pWCdy0UHvPRSQciW2Bza7gy1KhUTgW8w+PSqW3PTXZUtXroaKk0Aut7pOt2TBkWfZ1bfygibJFidi/+8qe1zOuqD5MnY+OgNXg9JpcS9Br2gJfy+0JZKD+aRdvbQpbp3981FkDEu3tZUF1utA18pdG8wqmy9lkgFgqSfwB/wK6FYottnyi8BI4FAeuGNxXtQiFzVyEkgFAU94Uu8Wvju8uuYE1GzRzkoPqf61y9873/fR645f8VhJXthJyU5spQGP/b916/T/1+cP5/VduLaJeBb3pNykRT178LmPmEUo2L9pN7/f/AyVCYJLRP4FDWynTWxkKnj9pafazrwQoMXyoen3kYPg1O9GeOFhvujD9q+VcpTs8n+ibHFxlGGY3xTaG4BrQRUJWCQNO/bTrXTkj+03dggvCxhmozVoosR4chgkp4xmS7ogRUjy9NMVeVxEDQZGTYuXG4TkU2X/URoltvs2Ua4GGe8T9RURFzvqklhxNeR+ABcgs6mVZdqwk10q1PzBEsXhZnYqqfyPBy7Yyjeeq1HS2u5aj5AqMy1cbx5o+A5KWw+ndnWymp3anWg4rcUmDpvUThWGd6QsnkJn5U8x2gOPltx4npRXa4psouqLEYfHDyIWnr1zr0BfNgG822ZvM3BK1t4QWPe8JsEcz5wC3ezJU1b0s8YetGneC5DrD3Lo6mQMxz8ZmXMnDm/KmdEFp/QeaK1zpQ4+nB1RwlK9wBXRirR9S4sjZK/5G0SIqKD5HOicrhyB3VYLPVBSawyzuA9Hvt/FZh8uEgsdBUkTxstChQH+h3XlhLqGzMCZeuQix/uuzyhbyemwhEuF/KknkIAINKtnRIuF1RT0wC+UP4E/MA810Ci66bfrHuGg7C2iPWnssZ8cR/G6WbD6aJ0aGwA5cl2dWQE5F1EE25TZkFbajUrspq70Ew5c5zboVw1ZJ2bT1eQ46BfkpxvTDS+pHZzbCNK/OX1oUXhrzixqjwIjzBZah/kWtc1I61aAZCF29KSupFS2/nUCXot8CnTIL6+0AEI0UMtr1cXwM=",
    "refresh_token": "M.C522_BAY.0.U.-CrLBO7o*f*pCePeW!ky!f6e5YNw0m2e58RL!WqZGbztLbU5kBTwgdGnZBSFWDeC1Pr!y5!SZ4!mF*9sQxoYhckg!6RgtkojK9xcEKH8ZFbpW12jmuQ!aPaVnkO1mgz3f68RXLzT*ro!ITvP772lrmXgLZZk2cIYERw7plspqDm6hzvE2NHVq2wJ7PaWFqfLOi9t2NZQ8RKJFOp7MTRRhhIR9XiGzqaHqibK82LnWDf5AeAgaH!bzkyxNvy1Do7S1Afb7H5mozwnuxroZt0*RaRk7acG0R4jau32Q9vLgBfCD7uBpdQJPzbHt3juC3V0wjQjhi0nWUUplZV3ByhyS9Jf*EdFtG3eNaJhD2kJboyypoxPVJE1QHYy342S1ArU9Cw$$",
    "id_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImtpZCI6Imw3WWF2VjFLZnNIajhQVUdTenRET2s5VnBLQSJ9.eyJ2ZXIiOiIyLjAiLCJpc3MiOiJodHRwczovL2xvZ2luLm1pY3Jvc29mdG9ubGluZS5jb20vOTE4ODA0MGQtNmM2Ny00YzViLWIxMTItMzZhMzA0YjY2ZGFkL3YyLjAiLCJzdWIiOiJBQUFBQUFBQUFBQUFBQUFBQUFBQUFPc3A1RHRtOTBvRkxBVlMtTHlKU3VjIiwiYXVkIjoiYWRiZDlkNjAtYjY5MS00MDM2LTg3NDgtMzhlNTk2ZGJkNjRmIiwiZXhwIjoxNzY0MjQyNTQ5LCJpYXQiOjE3NjQxNTU4NDksIm5iZiI6MTc2NDE1NTg0OSwibmFtZSI6IkPDqWRyaWMgRnJhbmNveXMiLCJwcmVmZXJyZWRfdXNlcm5hbWUiOiJmbXRzb2x1dGlvbnMueWJAb3V0bG9vay5jb20iLCJvaWQiOiIwMDAwMDAwMC0wMDAwLTAwMDAtYzJjNi1iYjM3N2FmZDljZjMiLCJlbWFpbCI6ImZtdHNvbHV0aW9ucy55YkBvdXRsb29rLmNvbSIsInRpZCI6IjkxODgwNDBkLTZjNjctNGM1Yi1iMTEyLTM2YTMwNGI2NmRhZCIsImFpbyI6IkRueDJLbGxMSXVBN3AhckswUzNkbVJRRFZvbHdEaFhheDNvNzVFRUJZSjJEYUwhQmtXbXBWVkhqSUIwa242ZlNvdVhYSDFsNnNIb0trdEZnRVlqd1pJeiE0a201dEhzUWZpaDBGWnozWmJ0a2RaQjZZcXYwc0NSWUtnRTdRTm5sU0ZybnFZSnVzNXYqYldDVnFhVUtNV3BBMU01MWptbk9pZ1o0QlJ4bXlqZHYifQ.AL7CqWHzMK-lnSBEySK4hbnWpDFAMqQf4hRFcJvFB-_wpe1GQH11gPf4Qz4p89SynqZG3aHcDQsnoxi1WeQNLgfplu73wl5TfWRQwe2mhsi-s0CbRjU8idHIaxCO3sXu4KGHJdb4qCaGe3x6grTt68L8BLBUMqxkZkET8kemQVIkYUYcIL7hIKoPWGDPsUxBRuZY6QB-QxapNJQ_6vgLZ1KplnlcdT5mXIZX_LCFWo4TeXQbNw3HfYzBpL6UbK13CaV-j9XicrxKX_jZY6IKqVyMb3J9GxwNlYBkbweR-I9r7G6CViU2X6AdNe6KAWbHeQdKWMwqmoJCgxV-g3J9og"
}

*/
$data = $response->body();
$status = $response->getStatusCode();

if($status < 200 || $status > 299) {
    $context->httpResponse()
        ->status($status)
        ->body($data)
        ->send();
    exit;
}


/* --------------------------------------------------------------------------
   2) Retrieve user email
   -------------------------------------------------------------------------- */

$email = null;

// If id_token is present → decode email (openid flow)
if(!empty($data['id_token'])) {
    $identity_jwt = JWT::decode($data['id_token']);
    $email = $identity_jwt['payload']['preferred_username'] ?? null;
}

// Otherwise call Graph API /me
if(!$email) {
    $meRequest = new HttpRequest('GET https://graph.microsoft.com/v1.0/me');
    $meResponse = $meRequest
        ->header('Authorization', 'Bearer ' . $data['access_token'])
        ->send();
    $me = $meResponse->body();
    $email = $me['mail'] ?? $me['userPrincipalName'] ?? null;
}

if(!$email) {
    $context->httpResponse()
        ->status(400)
        ->body(['error' => 'Unable to determine user email.'])
        ->send();
}


/* --------------------------------------------------------------------------
   3) Identify instance to validate mailbox
   -------------------------------------------------------------------------- */

$origin_url = $params['state'];
$domain = parse_url($origin_url, PHP_URL_HOST);

$instance = Instance::search(['name', '=', $domain])->first();

if($instance) {
    $data['email'] = $email;
    $data['provider'] = 'outlook';
    $data['access_token_expiry'] = time() + $data['expires_in'];
    // Microsoft exposes refresh token validity differently; fallback 90 days
    $data['refresh_token_expiry'] = time() + (constant('AUTH_ACCESS_TOKEN_VALIDITY') * 5);

    $validationRequest = new HttpRequest('POST https://' . $domain . '/?do=communication_email_Mailbox_validate');
    $response = $validationRequest
        ->header('Content-Type', 'application/json')
        ->setBody($data)
        ->send();
}


/* --------------------------------------------------------------------------
   4) Update Mailbox in eQual if exists
   -------------------------------------------------------------------------- */

$mailbox = Mailbox::search([
        ['email', '=', $email],
        ['auth_type', '=', 'oauth'],
        ['status', '=', 'pending']
    ])
    ->first();

if ($mailbox) {
    Mailbox::id($mailbox['id'])->update([
        'access_token'          => $data['access_token'],
        'refresh_token'         => $data['refresh_token'],
        'access_token_expiry'   => time() + $data['expires_in'],
        'refresh_token_expiry'  => time() + (constant('AUTH_ACCESS_TOKEN_VALIDITY') * 5),
        'imap_server'           => 'imap.outlook.com',
        'status'                => 'validated'
    ]);
}

$context->httpResponse()
    ->status(204)
    ->send();
