<?php
// Configuración
$to_email = "info@resihubapp.com";
$from_email = "noreply@resihubapp.com";
$from_name = "ResiHub Contact Form";

// Google reCAPTCHA Enterprise Configuration
// API key: obtén una en https://console.cloud.google.com/apis/credentials (proyecto: crudcreativo)
// Habilita "reCAPTCHA Enterprise API" y crea una clave de API sin restricciones (o restringida a este dominio)
$recaptcha_site_key     = "6LfNhuMsAAAAAG-XBXG2bbs-8uI5YzlaUQB6y0le";
$recaptcha_api_key      = "AIzaSyAfKfJSaKic87ECrjXinv6GS2zFavBXYZI"; // ← Pegar aquí la API key de Google Cloud
$recaptcha_project_id   = "crudcreativo";
$recaptcha_score_threshold = 0.5;

$recaptcha_actions = [
    'contact'     => 'contact',
    'start'       => 'start',
    'get-resihub' => 'get_resihub',
    'sales'       => 'sales'
];

// Headers base para el email
$headers  = "MIME-Version: 1.0\r\n";
$headers .= "Content-type:text/html;charset=UTF-8\r\n";

function sanitize_input($data) {
    return htmlspecialchars(stripslashes(trim($data)));
}

function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function verify_recaptcha_enterprise($token, $site_key, $project_id, $api_key, $expected_action) {
    // Si no hay API key configurada, permitir el envío para evitar bloqueos de configuración
    if (empty($api_key)) {
        error_log("reCAPTCHA: API key no configurada, omitiendo verificación.");
        $result = new stdClass();
        $result->success     = true;
        $result->score       = 1.0;
        $result->action      = $expected_action;
        $result->actionMatch = true;
        return $result;
    }

    if (!function_exists('curl_init')) {
        error_log("reCAPTCHA: curl no disponible.");
        return false;
    }

    $url  = "https://recaptchaenterprise.googleapis.com/v1/projects/{$project_id}/assessments?key={$api_key}";
    $body = json_encode([
        'event' => [
            'token'          => $token,
            'siteKey'        => $site_key,
            'expectedAction' => $expected_action,
        ]
    ]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST,           true);
    curl_setopt($ch, CURLOPT_POSTFIELDS,     $body);
    curl_setopt($ch, CURLOPT_HTTPHEADER,     ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT,        10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $raw       = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err  = curl_error($ch);
    curl_close($ch);

    if ($raw === false || $http_code !== 200) {
        error_log("reCAPTCHA Enterprise error: HTTP $http_code — $curl_err");
        return false;
    }

    $data = json_decode($raw);
    if (!$data) {
        error_log("reCAPTCHA Enterprise: respuesta JSON inválida.");
        return false;
    }

    $result              = new stdClass();
    $result->success     = $data->tokenProperties->valid  ?? false;
    $result->score       = $data->riskAnalysis->score     ?? 0.0;
    $result->action      = $data->tokenProperties->action ?? '';
    $result->actionMatch = ($result->action === $expected_action);

    return $result;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header('Location: index.html');
    exit;
}

$errors   = [];
$response = [];

$form_type       = isset($_POST['form_type']) ? sanitize_input($_POST['form_type']) : 'contact';
$expected_action = $recaptcha_actions[$form_type] ?? 'contact';
$recaptcha_token = $_POST['recaptcha_token'] ?? '';

if (empty($recaptcha_token)) {
    $errors[] = "Error de verificación de seguridad. Por favor, recarga la página e intenta nuevamente.";
} else {
    $rc = verify_recaptcha_enterprise($recaptcha_token, $recaptcha_site_key, $recaptcha_project_id, $recaptcha_api_key, $expected_action);

    if (!$rc || !$rc->success) {
        $errors[] = "Verificación de seguridad fallida. Por favor, intenta nuevamente.";
    } elseif (!$rc->actionMatch) {
        $errors[] = "La acción de seguridad no coincide. Por favor, intenta nuevamente.";
    } elseif ($rc->score < $recaptcha_score_threshold) {
        $errors[] = "Tu solicitud no pasó nuestros controles de seguridad. Si eres humano, por favor contacta con soporte.";
    }
}

if (!empty($errors)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => "Error de verificación", 'errors' => $errors]);
    exit;
}

switch ($form_type) {
    case 'contact':
        $name    = isset($_POST['name'])    ? sanitize_input($_POST['name'])    : '';
        $email   = isset($_POST['email'])   ? sanitize_input($_POST['email'])   : '';
        $phone   = isset($_POST['phone'])   ? sanitize_input($_POST['phone'])   : '';
        $message = isset($_POST['message']) ? sanitize_input($_POST['message']) : '';

        if (empty($name))                        $errors[] = "El nombre es requerido";
        if (empty($email) || !validate_email($email)) $errors[] = "Un email válido es requerido";
        if (empty($phone))                       $errors[] = "El teléfono es requerido";
        if (empty($message))                     $errors[] = "El mensaje es requerido";

        if (empty($errors)) {
            $subject    = "Nuevo mensaje de contacto desde ResiHub";
            $email_body = "
            <html><head><style>
                body{font-family:Arial,sans-serif;line-height:1.6;color:#333}
                .container{max-width:600px;margin:0 auto;padding:20px}
                .header{background-color:#1e40af;color:white;padding:20px;text-align:center}
                .content{background-color:#f9fafb;padding:20px}
                .field{margin-bottom:15px}
                .label{font-weight:bold;color:#1e40af}
                .value{margin-top:5px;padding:10px;background-color:white;border-left:3px solid #1e40af}
            </style></head><body>
            <div class='container'>
                <div class='header'><h2>Nuevo Mensaje de Contacto</h2></div>
                <div class='content'>
                    <div class='field'><div class='label'>Nombre:</div><div class='value'>$name</div></div>
                    <div class='field'><div class='label'>Email:</div><div class='value'>$email</div></div>
                    <div class='field'><div class='label'>Teléfono:</div><div class='value'>$phone</div></div>
                    <div class='field'><div class='label'>Mensaje:</div><div class='value'>" . nl2br($message) . "</div></div>
                </div>
            </div></body></html>";
            $headers .= "From: $from_name <$from_email>\r\n";
            $headers .= "Reply-To: $email\r\n";
        }
        break;

    case 'start':
        $name     = isset($_POST['name'])     ? sanitize_input($_POST['name'])     : '';
        $email    = isset($_POST['email'])    ? sanitize_input($_POST['email'])    : '';
        $phone    = isset($_POST['phone'])    ? sanitize_input($_POST['phone'])    : '';
        $empresa  = isset($_POST['empresa'])  ? sanitize_input($_POST['empresa'])  : '';
        $unidades = isset($_POST['unidades']) ? sanitize_input($_POST['unidades']) : '';
        $comments = isset($_POST['comments']) ? sanitize_input($_POST['comments']) : '';
        $plan     = isset($_POST['plan'])     ? sanitize_input($_POST['plan'])     : 'No especificado';

        if (empty($name))                          $errors[] = "El nombre es requerido";
        if (empty($email) || !validate_email($email)) $errors[] = "Un email válido es requerido";
        if (empty($phone))                         $errors[] = "El teléfono es requerido";
        if (empty($empresa))                       $errors[] = "El nombre de la empresa es requerido";
        if (empty($unidades))                      $errors[] = "El número de unidades es requerido";

        if (empty($errors)) {
            $subject    = "Solicitud para comenzar con ResiHub" . ($plan !== 'No especificado' ? " - Plan: $plan" : "");
            $email_body = "
            <html><head><style>
                body{font-family:Arial,sans-serif;line-height:1.6;color:#333}
                .container{max-width:600px;margin:0 auto;padding:20px}
                .header{background-color:#10b981;color:white;padding:20px;text-align:center}
                .content{background-color:#f9fafb;padding:20px}
                .field{margin-bottom:15px}
                .label{font-weight:bold;color:#10b981}
                .value{margin-top:5px;padding:10px;background-color:white;border-left:3px solid #10b981}
                .plan-badge{display:inline-block;background-color:rgba(255,255,255,0.2);color:white;padding:5px 15px;border-radius:20px;margin-top:10px}
            </style></head><body>
            <div class='container'>
                <div class='header'>
                    <h2>Nueva Solicitud para Comenzar</h2>
                    " . ($plan !== 'No especificado' ? "<div class='plan-badge'>Plan: $plan</div>" : "") . "
                </div>
                <div class='content'>
                    <div class='field'><div class='label'>Nombre:</div><div class='value'>$name</div></div>
                    <div class='field'><div class='label'>Email:</div><div class='value'>$email</div></div>
                    <div class='field'><div class='label'>Teléfono:</div><div class='value'>$phone</div></div>
                    <div class='field'><div class='label'>Empresa:</div><div class='value'>$empresa</div></div>
                    <div class='field'><div class='label'>Número de Unidades:</div><div class='value'>$unidades</div></div>
                    " . (!empty($comments) ? "<div class='field'><div class='label'>Comentarios:</div><div class='value'>" . nl2br($comments) . "</div></div>" : "") . "
                </div>
            </div></body></html>";
            $headers .= "From: $from_name <$from_email>\r\n";
            $headers .= "Reply-To: $email\r\n";
        }
        break;

    case 'get-resihub':
        $name           = isset($_POST['name'])           ? sanitize_input($_POST['name'])           : '';
        $email          = isset($_POST['email'])          ? sanitize_input($_POST['email'])          : '';
        $phone          = isset($_POST['phone'])          ? sanitize_input($_POST['phone'])          : '';
        $property       = isset($_POST['property'])       ? sanitize_input($_POST['property'])       : '';
        $property_type  = isset($_POST['property_type'])  ? sanitize_input($_POST['property_type'])  : '';
        $units          = isset($_POST['units'])          ? sanitize_input($_POST['units'])          : '';
        $location       = isset($_POST['location'])       ? sanitize_input($_POST['location'])       : '';
        $plan           = isset($_POST['plan'])           ? sanitize_input($_POST['plan'])           : 'profesional';
        $comments       = isset($_POST['comments'])       ? sanitize_input($_POST['comments'])       : '';
        $current_system = isset($_POST['current_system']) ? sanitize_input($_POST['current_system']) : '';

        $property_types_map = [
            'condominio'   => 'Condominio',
            'apartamentos' => 'Edificio de Apartamentos',
            'villas'       => 'Villas / Casas',
            'locales'      => 'Locales Comerciales',
            'mixto'        => 'Uso Mixto',
            'otro'         => 'Otro'
        ];
        $current_systems_map = [
            'excel'        => 'Excel / Hojas de cálculo',
            'otro-software'=> 'Otro software de gestión',
            'papel'        => 'Registros en papel',
            'ninguno'      => 'No usa ningún sistema'
        ];
        $plans_map = [
            'basico'       => 'Básico (hasta 50 unidades)',
            'profesional'  => 'Profesional (hasta 200 unidades)',
            'enterprise'   => 'Enterprise (unidades ilimitadas)'
        ];
        $property_type_label  = $property_types_map[$property_type]   ?? $property_type;
        $current_system_label = $current_systems_map[$current_system]  ?? $current_system;
        $plan_label           = $plans_map[$plan]                      ?? $plan;

        if (empty($name))                          $errors[] = "El nombre es requerido";
        if (empty($email) || !validate_email($email)) $errors[] = "Un email válido es requerido";
        if (empty($phone))                         $errors[] = "El teléfono es requerido";
        if (empty($property))                      $errors[] = "El nombre de la propiedad es requerido";
        if (empty($property_type))                 $errors[] = "El tipo de propiedad es requerido";
        if (empty($units))                         $errors[] = "El número de unidades es requerido";
        if (empty($location))                      $errors[] = "La ubicación es requerida";

        if (empty($errors)) {
            $subject    = "Nueva Solicitud de ResiHub - $property ($plan_label)";
            $email_body = "
            <html><head><style>
                body{font-family:Arial,sans-serif;line-height:1.6;color:#333}
                .container{max-width:600px;margin:0 auto;padding:20px}
                .header{background:linear-gradient(135deg,#8b5cf6,#a855f7,#d946ef);color:white;padding:25px;text-align:center;border-radius:10px 10px 0 0}
                .content{background-color:#f9fafb;padding:25px;border-radius:0 0 10px 10px}
                .section{margin-bottom:20px;padding:15px;background-color:white;border-radius:8px;border-left:4px solid #8b5cf6}
                .section-title{font-size:14px;font-weight:bold;color:#8b5cf6;margin-bottom:10px;text-transform:uppercase}
                .field{margin-bottom:12px}
                .label{font-weight:bold;color:#374151;font-size:13px}
                .value{margin-top:3px;padding:8px 12px;background-color:#f3f4f6;border-radius:5px;color:#1f2937}
                .plan-badge{display:inline-block;background-color:rgba(255,255,255,0.25);color:white;padding:8px 20px;border-radius:25px;margin-top:10px;font-weight:bold}
                .highlight{background-color:#fef3c7;border-left-color:#f59e0b}
            </style></head><body>
            <div class='container'>
                <div class='header'>
                    <h2 style='margin:0 0 5px 0;'>Nueva Solicitud de ResiHub</h2>
                    <p style='margin:0;opacity:0.9;'>get.resihubapp.com</p>
                    <div class='plan-badge'>$plan_label</div>
                </div>
                <div class='content'>
                    <div class='section'>
                        <div class='section-title'>Información Personal</div>
                        <div class='field'><div class='label'>Nombre Completo:</div><div class='value'>$name</div></div>
                        <div class='field'><div class='label'>Email:</div><div class='value'>$email</div></div>
                        <div class='field'><div class='label'>Teléfono:</div><div class='value'>$phone</div></div>
                    </div>
                    <div class='section highlight'>
                        <div class='section-title'>Información de la Propiedad</div>
                        <div class='field'><div class='label'>Nombre del Condominio/Propiedad:</div><div class='value'>$property</div></div>
                        <div class='field'><div class='label'>Tipo de Propiedad:</div><div class='value'>$property_type_label</div></div>
                        <div class='field'><div class='label'>Número de Unidades:</div><div class='value'>$units</div></div>
                        <div class='field'><div class='label'>Ubicación:</div><div class='value'>$location</div></div>
                    </div>
                    " . (!empty($current_system) ? "
                    <div class='section'>
                        <div class='section-title'>Sistema Actual</div>
                        <div class='field'><div class='label'>Sistema que usa actualmente:</div><div class='value'>$current_system_label</div></div>
                    </div>" : "") . "
                    " . (!empty($comments) ? "
                    <div class='section'>
                        <div class='section-title'>Comentarios Adicionales</div>
                        <div class='field'><div class='value'>" . nl2br($comments) . "</div></div>
                    </div>" : "") . "
                </div>
            </div></body></html>";
            $headers .= "From: $from_name <$from_email>\r\n";
            $headers .= "Reply-To: $email\r\n";
        }
        break;

    case 'sales':
        $name    = isset($_POST['name'])    ? sanitize_input($_POST['name'])    : '';
        $email   = isset($_POST['email'])   ? sanitize_input($_POST['email'])   : '';
        $phone   = isset($_POST['phone'])   ? sanitize_input($_POST['phone'])   : '';
        $empresa = isset($_POST['empresa']) ? sanitize_input($_POST['empresa']) : '';
        $message = isset($_POST['message']) ? sanitize_input($_POST['message']) : '';
        $plan    = isset($_POST['plan'])    ? sanitize_input($_POST['plan'])    : 'No especificado';

        if (empty($name))                          $errors[] = "El nombre es requerido";
        if (empty($email) || !validate_email($email)) $errors[] = "Un email válido es requerido";
        if (empty($phone))                         $errors[] = "El teléfono es requerido";
        if (empty($empresa))                       $errors[] = "El nombre de la empresa es requerido";
        if (empty($message))                       $errors[] = "El mensaje es requerido";

        if (empty($errors)) {
            $subject    = "Contacto de Ventas - Plan: $plan";
            $email_body = "
            <html><head><style>
                body{font-family:Arial,sans-serif;line-height:1.6;color:#333}
                .container{max-width:600px;margin:0 auto;padding:20px}
                .header{background-color:#7c3aed;color:white;padding:20px;text-align:center}
                .content{background-color:#f9fafb;padding:20px}
                .field{margin-bottom:15px}
                .label{font-weight:bold;color:#7c3aed}
                .value{margin-top:5px;padding:10px;background-color:white;border-left:3px solid #7c3aed}
                .plan-badge{display:inline-block;background-color:#7c3aed;color:white;padding:5px 15px;border-radius:20px;margin-bottom:10px}
            </style></head><body>
            <div class='container'>
                <div class='header'>
                    <h2>Nuevo Contacto de Ventas</h2>
                    <div class='plan-badge'>Plan: $plan</div>
                </div>
                <div class='content'>
                    <div class='field'><div class='label'>Nombre:</div><div class='value'>$name</div></div>
                    <div class='field'><div class='label'>Email:</div><div class='value'>$email</div></div>
                    <div class='field'><div class='label'>Teléfono:</div><div class='value'>$phone</div></div>
                    <div class='field'><div class='label'>Empresa:</div><div class='value'>$empresa</div></div>
                    <div class='field'><div class='label'>Mensaje:</div><div class='value'>" . nl2br($message) . "</div></div>
                </div>
            </div></body></html>";
            $headers .= "From: $from_name <$from_email>\r\n";
            $headers .= "Reply-To: $email\r\n";
        }
        break;

    default:
        $errors[] = "Tipo de formulario no válido";
}

if (empty($errors)) {
    if (mail($to_email, $subject, $email_body, $headers)) {
        $response = ['success' => true, 'message' => "¡Gracias! Hemos recibido tu mensaje. Te contactaremos pronto."];
    } else {
        $response = ['success' => false, 'message' => "Hubo un error al enviar el mensaje. Por favor, intenta nuevamente."];
    }
} else {
    $response = ['success' => false, 'message' => "Errores de validación", 'errors' => $errors];
}

header('Content-Type: application/json');
echo json_encode($response);
