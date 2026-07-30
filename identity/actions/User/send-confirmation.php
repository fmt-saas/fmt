<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
use equal\email\Email;
use equal\html\HtmlTemplate;
use core\Mail;
use identity\Organisation;
use identity\User;

[$params, $providers] = eQual::announce([
    'description'   => "Send a confirmation email to newly created User accounts.",
    'params'        => [
        'id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'identity\User',
            'description'       => "The specific User the request refers to.",
            'default'           => 0
        ],
        'language' => [
            'type'              => 'string',
            'description'       => "Language of the email template.",
            'default'           => 'fr'
        ]
    ],
    'constants'     => ['BACKEND_URL', 'EMAIL_SMTP_ACCOUNT_DISPLAYNAME'],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'access'        => [
        'visibility'    => 'protected',
        'groups'        => ['admins', 'operators']
    ],
    'providers'     => ['context', 'auth', 'self']
]);

/**
 * @var \equal\php\Context                 $context
 * @var \equal\auth\AuthenticationManager  $auth
 */
['context' => $context, 'auth' => $auth, 'self' => $self] = $providers;

$generate_confirmation_secret = function (): string {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
    $chars_count = strlen($chars);
    $bytes = random_bytes(24);
    $secret = '';

    foreach(str_split($bytes) as $byte) {
        $secret .= $chars[ord($byte) % $chars_count];
    }

    return $secret;
};

$current_user_id = $auth->userId();

$auth->su();

$user = $self->read([
        'id', 'login', 'username', 'firstname', 'lastname', 'language', 'status'
    ])
    ->first(true);

if(!$user) {
    throw new Exception('unknown_user', EQ_ERROR_INVALID_PARAM);
}

if(empty($user['login'])) {
    trigger_error("APP::ignored confirmation email for user {$params['id']} with no login.", EQ_REPORT_WARNING);
    throw new Exception('invalid_user', EQ_ERROR_INVALID_PARAM);
}

if(($user['status'] ?? null) === 'confirmed') {
    trigger_error("APP::ignored confirmation email for already confirmed user {$params['id']}.", EQ_REPORT_WARNING);
    throw new Exception('invalid_user', EQ_ERROR_INVALID_PARAM);
}

try {

    $template_language = $params['language'] ?: 'fr';

    $template_file = "packages/identity/i18n/{$template_language}/mail_user_confirm.html";

    if(!($html = @file_get_contents($template_file))) {
        $template_file = "packages/identity/i18n/fr/mail_user_confirm.html";
        if(!($html = @file_get_contents($template_file))) {
            throw new Exception('missing_dependency', EQ_ERROR_INVALID_CONFIG);
        }
    }

    $organisation = Organisation::id(1)->read(['name'])->first();

    $password = $generate_confirmation_secret();

    User::id($user['id'])->update(['password' => $password]);

    $subject = $organisation['name'] . '- Création de votre compte utilisateur';

    $mail_params = array_merge($user, [
            'password'  => $password,
            'org_name'  => $organisation['name']
        ]);

    $template = new HtmlTemplate($html, [
            'subject'      => function ($params, $attributes) use (&$subject) {
                $subject = $attributes['title'];
                return '';
            },
            'display_name' => function ($params, $attributes) {
                $name = trim(($params['firstname'] ?? '') . ' ' . ($params['lastname'] ?? ''));
                return $name ?: ($params['username'] ?: $params['login']);
            },
            'confirm_url'  => function ($params, $attributes) {
                $code = base64_encode($params['login'].':' . $params['password']);
                $url = rtrim(constant('BACKEND_URL'), '/') . '/?do=user_confirm&code=' . rawurlencode($code);
                $label = htmlspecialchars($attributes['title'], ENT_QUOTES, 'UTF-8');
                $href = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
                return "<a href=\"{$href}\">{$label}</a>";
            },
            'origin'       => function ($params, $attributes) {
                return $params['org_name'];
            }
        ],
        $mail_params);

    $message = new Email();

    $message->setTo($user['login'])
        ->setSubject($subject)
        ->setContentType('text/html')
        ->setBody($template->getHtml());

    Mail::queue($message);
}
finally {
    $auth->su($current_user_id);
}

$context->httpResponse()
    ->status(201)
    ->send();
