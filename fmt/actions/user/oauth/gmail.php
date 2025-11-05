<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use communication\email\Mailbox;
use equal\auth\JWT;
use equal\http\HttpRequest;
use identity\User;

// announce script and fetch parameters values
[$params, $providers] = eQual::announce([
    'description'	=>	"Callback for receiving confirmation from Google OAuth.",
    'params' 		=>	[
        'code' => [
            'type'          => 'string',
            'usage'         => 'text/plain:3000',
            //'required'      => true
        ],
        'access_token' => [
            'type'          => 'string',
            'usage'         => 'text/plain:3000',
            //'required'      => true
        ],
        'expires_in' => [
            'type'          => 'integer',
            //'required'      => true
        ],
        'refresh_token' => [
            'type'          => 'string',
            'usage'         => 'text/plain:3000',
            //'required'      => true
        ],
        'scope' => [
            'type'          => 'string',
            //'required'      => true
        ],
        'token_type' => [
            'type'          => 'string',
            //'required'      => true
        ],
        'id_token' => [
            'type'          => 'string',
            'usage'         => 'text/plain.small',
            //'required'      => true
        ]
    ],
    'constants'     => ['BACKEND_URL'],
    'access'        => [
        'visibility' => 'public'
    ],
    'response'      => [
        'content-type'      => 'application/json',
        'charset'           => 'utf-8',
        'accept-origin'     => '*'
    ],
    'providers'     => ['context', 'auth', 'orm'],
    'constants'     => ['BACKEND_URL', 'AUTH_ACCESS_TOKEN_VALIDITY', 'AUTH_TOKEN_HTTPS', 'FMT_INSTANCE_TYPE']
]);

/**
 * @var equal\php\Context                   $context
 * @var equal\orm\ObjectManager             $om
 * @var equal\auth\AuthenticationManager    $auth
 */
['context' => $context, 'orm' => $om, 'auth' => $auth] = $providers;

if($params['code']) {
 // constant('BACKEND_URL')

    $body = [
        'grant_type' => 'authorization_code',
        'code' => $params['code'],
        // #todo - utiliser global.fmt.yb.run (puis rediriger les réponses vers les instances concernées)
        'redirect_uri' => constant('BACKEND_URL').'/oauth/gmail',
        'client_id' => '24230475119-6fabc7k3lh9v9u3aa01im86d48bsudp0.apps.googleusercontent.com',
        'client_secret' => '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'
    ];

    $oauthRequest = new HttpRequest('POST https://oauth2.googleapis.com/token');
    $response = $oauthRequest
                ->header('Content-Type', 'application/x-www-form-urlencoded')
                ->setBody($body)
                ->send();

    $data = $response->body();
    $status = $response->getStatusCode();

    $identity_jwt = JWT::decode($data['id_token']);

    $email = $identity_jwt['payload']['email'];

/*
Response example:
{
  "access_token": "ya29.a0ATi6K2s7j8guT78Y3VV7Mck99zFLtT0qCQVQH5nBseO9GLeANtVmnuzQHujILB3wFdymZiEN2SrKv-UYdoH3KQHiMNn-Jfs52bMez4B7qIveZmv9q_qcBLCI3DSQwLlGljT1b4pJdQFlfGCOZnX1XuUIykKtZ0goy3ZC-QCor6B2O9-jKjUw8fvoEj1NJ4kRPs36e1MaCgYKAbQS
ARISFQHGX2Mid4P-kZZXK9X3ZiOXAGCpUA0206"
  "expires_in": 3599
  "refresh_token": "1//03y07JzvzAK18CgYIARAAZAMSNwF-L9IrLqVs-_PthCHucUNDMTIOEdcgrvGhTFnzIRi6PodurQcBZ_Bl36fl3IsOS1Kwca2cdmc"
  "scope": "https://www.googleapis.com/auth/gmail.readonly https://www.googleapis.com/auth/userinfo.email https://www.googleapis.com/auth/userinfo.profile openid"
  "token_type": "Bearer"
  "id_token": "eyJhbGciOiJSUzI1NiIsImtpZCI6ImI1ZTQ0MGFlOTQxZTk5ODFlZTJmYTEzNzZkNDJjNDZkNzMxZGVlM2YiLCJ0eXAiOiJKV1QifQ.eyJpc3MiOiJodHRwczovL2FjY291bnRzLmdvb2dsZS5jb20iLCJhenAiOiIyNDIzMDQ3NTExOS02ZmFiYzdrM2xoOXY5dTNhYTAxaW04NmQ0OGJ
zdWRwMC5hcHBzLmdvb2dsZXVzZXJjb250ZW50LmNvbSIsImF1ZCI6IjI0MjMwNDc1MTE5LTZmYWJjN2szbGg5djl1M2FhMDFpbTg2ZDQ4YnN1ZHAwLmFwcHMuZ29vZ2xldXNlcmNvbnRlbnQuY29tIiwic3ViIjoiMTA4NjYwNTc5MDc4MjI4ODQ2Mjk2IiwiaGQiOiJ5ZXNiYWJ5bG9uLmNvbSIsImVtYWlsI
joiaW5mb0B5ZXNiYWJ5bG9uLmNvbSIsImVtYWlsX3ZlcmlmaWVkIjp0cnVlLCJhdF9oYXNoIjoiWnlYMmlQQWQ2blZ3RVQweDFPakRnQSIsIm5hbWUiOiJDw6lkcmljIEZyYW5jb3lzIiwiZ2l2ZW5fbmFtZSI6IkPDqWRyaWMiLCJmYW1pbHlfbmFtZSI6IkZyYW5jb3lzIiwiaWF0IjoxNzYyMzUwNjI1LCJ
leHAiOjE3NjIzNTQyMjV9.T2WV04l42jObUjZ9nDtnmC-2gJuTSm6KSH210FkSWFBHtD8HoMa7E7Vbdh5PH_kqLVd6ZD0-5ytRtdoo6r0_XH0AszgJHrrpdMhL3Iuz4lKEOtjrO9VLlbyg1LNCBiM_BMrXSFNRzhWLGMDR06Jjb2evETfcBYPwZl1QcJfcH73PcbW9Vn6n-IkuLb7kNqFmRU_cA0q84Kn-ZuHo
gpAJMjKCav1DcLXXe2fngZBoMZadqf_FcCwx-ZqsI5iG-HjCSnPJ_D3TT323CnQVmKNDXv8RePmU70lvtEkxSjmRj4BU-cPfzqLSUVeH9zGzFPrS796PPmr2j4PKUHdvch4mbg"
  "refresh_token_expires_in": 604799
}
*/

    // attempt to retrieve a matching Mailbox
    $mailbox = Mailbox::search([
            ['email', '=', $email],
            ['auth_type', '=', 'oauth'],
            ['status', '=', 'pending']
        ])
        ->first();

    // if found, update it and mark it as validated
    if($mailbox) {
        Mailbox::id($mailbox['id'])->update([
                'access_token'          => $data['access_token'],
                'refresh_token'         => $data['refresh_token'],
                'access_token_expiry'   => time() + $data['expires_in'],
                'refresh_token_expiry'  => time() + $data['refresh_token_expires_in'],
                'imap_server'           => 'imap.gmail.com',
                'status'                => 'validated'
            ]);
    }

}

$context->httpResponse()
        ->status(204)
        ->send();
