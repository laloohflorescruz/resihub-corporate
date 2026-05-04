<?php
require_once __DIR__ . '/vendor/autoload.php';

use Google\Cloud\RecaptchaEnterprise\V1\Client\RecaptchaEnterpriseServiceClient;
use Google\Cloud\RecaptchaEnterprise\V1\Event;
use Google\Cloud\RecaptchaEnterprise\V1\Assessment;
use Google\Cloud\RecaptchaEnterprise\V1\CreateAssessmentRequest;

// Configuración
$to_email = "info@resihubapp.com";
$from_email = "noreply@resihubapp.com";
$from_name = "ResiHub Contact Form";

// Google reCAPTCHA Enterprise Configuration
$recaptcha_site_key = "6LewSdksAAAAAOcEeGnJ3B1U2vQey92BiadZm1a7";
$recaptcha_project_id = "crudcreativo";
$recaptcha_score_threshold = 0.5; // Minimum score to accept (0.0 - 1.0)

// reCAPTCHA actions per form type
$recaptcha_actions = [
    'contact' => 'contact',
    'start' => 'start',
    'get-resihub' => 'get_resihub',
    'sales' => 'sales'
];

// Headers para el email
$headers = "MIME-Version: 1.0" . "\r\n";
$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";

// Función para sanitizar datos
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Función para validar email
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Función para verificar reCAPTCHA Enterprise usando Google Cloud PHP Library
function verify_recaptcha_enterprise($token, $site_key, $project_id, $expected_action) {
    try {
        // Create the reCAPTCHA client
        $client = new RecaptchaEnterpriseServiceClient();
        $projectName = $client->projectName($project_id);

        // Set the properties of the event to be tracked
        $event = (new Event())
            ->setSiteKey($site_key)
            ->setToken($token);

        // Build the assessment request
        $assessment = (new Assessment())
            ->setEvent($event);

        $request = (new CreateAssessmentRequest())
            ->setParent($projectName)
            ->setAssessment($assessment);

        $response = $client->createAssessment($request);

        // Build result object
        $result = new stdClass();
        $result->success = $response->getTokenProperties()->getValid();
        $result->score = $response->getRiskAnalysis()->getScore();
        $result->action = $response->getTokenProperties()->getAction();
        $result->actionMatch = ($result->action === $expected_action);

        $client->close();

        return $result;
    } catch (Exception $e) {
        error_log("reCAPTCHA Enterprise error: " . $e->getMessage());
        return false;
    }
}

// Verificar que la solicitud sea POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $errors = [];
    $response = [];

    // Determinar el tipo de formulario primero para obtener la acción correcta
    $form_type = isset($_POST['form_type']) ? sanitize_input($_POST['form_type']) : 'contact';

    // Obtener la acción de reCAPTCHA esperada para este tipo de formulario
    $expected_action = isset($recaptcha_actions[$form_type]) ? $recaptcha_actions[$form_type] : 'contact';

    // Verificar reCAPTCHA Enterprise
    $recaptcha_token = isset($_POST['recaptcha_token']) ? $_POST['recaptcha_token'] : '';

    if (empty($recaptcha_token)) {
        $errors[] = "Error de verificación de seguridad. Por favor, recarga la página e intenta nuevamente.";
    } else {
        $recaptcha_response = verify_recaptcha_enterprise($recaptcha_token, $recaptcha_site_key, $recaptcha_project_id, $expected_action);

        if (!$recaptcha_response || !$recaptcha_response->success) {
            $errors[] = "Verificación de seguridad fallida. Por favor, intenta nuevamente.";
        } elseif (!$recaptcha_response->actionMatch) {
            $errors[] = "La acción de seguridad no coincide. Por favor, intenta nuevamente.";
        } elseif ($recaptcha_response->score < $recaptcha_score_threshold) {
            $errors[] = "Tu solicitud no pasó nuestros controles de seguridad. Si eres humano, por favor contacta con soporte.";
        }
    }

    // Si reCAPTCHA falla, no continuar con la validación del formulario
    if (!empty($errors)) {
        $response['success'] = false;
        $response['message'] = "Error de verificación";
        $response['errors'] = $errors;
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    // Validar según el tipo de formulario
    switch($form_type) {
        case 'contact':
            // Formulario de contacto general
            $name = isset($_POST['name']) ? sanitize_input($_POST['name']) : '';
            $email = isset($_POST['email']) ? sanitize_input($_POST['email']) : '';
            $phone = isset($_POST['phone']) ? sanitize_input($_POST['phone']) : '';
            $message = isset($_POST['message']) ? sanitize_input($_POST['message']) : '';

            if (empty($name)) {
                $errors[] = "El nombre es requerido";
            }

            if (empty($email) || !validate_email($email)) {
                $errors[] = "Un email válido es requerido";
            }

            if (empty($phone)) {
                $errors[] = "El teléfono es requerido";
            }

            if (empty($message)) {
                $errors[] = "El mensaje es requerido";
            }

            if (empty($errors)) {
                $subject = "Nuevo mensaje de contacto desde ResiHub";
                $email_body = "
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background-color: #1e40af; color: white; padding: 20px; text-align: center; }
                        .content { background-color: #f9fafb; padding: 20px; }
                        .field { margin-bottom: 15px; }
                        .label { font-weight: bold; color: #1e40af; }
                        .value { margin-top: 5px; padding: 10px; background-color: white; border-left: 3px solid #1e40af; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h2>Nuevo Mensaje de Contacto</h2>
                        </div>
                        <div class='content'>
                            <div class='field'>
                                <div class='label'>Nombre:</div>
                                <div class='value'>$name</div>
                            </div>
                            <div class='field'>
                                <div class='label'>Email:</div>
                                <div class='value'>$email</div>
                            </div>
                            <div class='field'>
                                <div class='label'>Teléfono:</div>
                                <div class='value'>$phone</div>
                            </div>
                            <div class='field'>
                                <div class='label'>Mensaje:</div>
                                <div class='value'>" . nl2br($message) . "</div>
                            </div>
                        </div>
                    </div>
                </body>
                </html>
                ";

                $headers .= "From: $from_name <$from_email>" . "\r\n";
                $headers .= "Reply-To: $email" . "\r\n";
            }
            break;

        case 'start':
            // Formulario de inicio/demo
            $name = isset($_POST['name']) ? sanitize_input($_POST['name']) : '';
            $email = isset($_POST['email']) ? sanitize_input($_POST['email']) : '';
            $phone = isset($_POST['phone']) ? sanitize_input($_POST['phone']) : '';
            $empresa = isset($_POST['empresa']) ? sanitize_input($_POST['empresa']) : '';
            $unidades = isset($_POST['unidades']) ? sanitize_input($_POST['unidades']) : '';
            $comments = isset($_POST['comments']) ? sanitize_input($_POST['comments']) : '';
            $plan = isset($_POST['plan']) ? sanitize_input($_POST['plan']) : 'No especificado';

            if (empty($name)) {
                $errors[] = "El nombre es requerido";
            }

            if (empty($email) || !validate_email($email)) {
                $errors[] = "Un email válido es requerido";
            }

            if (empty($phone)) {
                $errors[] = "El teléfono es requerido";
            }

            if (empty($empresa)) {
                $errors[] = "El nombre de la empresa es requerido";
            }

            if (empty($unidades)) {
                $errors[] = "El número de unidades es requerido";
            }

            if (empty($errors)) {
                $subject = "Solicitud para comenzar con ResiHub" . ($plan !== 'No especificado' ? " - Plan: $plan" : "");
                $email_body = "
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background-color: #10b981; color: white; padding: 20px; text-align: center; }
                        .content { background-color: #f9fafb; padding: 20px; }
                        .field { margin-bottom: 15px; }
                        .label { font-weight: bold; color: #10b981; }
                        .value { margin-top: 5px; padding: 10px; background-color: white; border-left: 3px solid #10b981; }
                        .plan-badge { display: inline-block; background-color: rgba(255,255,255,0.2); color: white; padding: 5px 15px; border-radius: 20px; margin-top: 10px; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h2>Nueva Solicitud para Comenzar</h2>
                            " . ($plan !== 'No especificado' ? "<div class='plan-badge'>Plan: $plan</div>" : "") . "
                        </div>
                        <div class='content'>
                            <div class='field'>
                                <div class='label'>Nombre:</div>
                                <div class='value'>$name</div>
                            </div>
                            <div class='field'>
                                <div class='label'>Email:</div>
                                <div class='value'>$email</div>
                            </div>
                            <div class='field'>
                                <div class='label'>Teléfono:</div>
                                <div class='value'>$phone</div>
                            </div>
                            <div class='field'>
                                <div class='label'>Empresa:</div>
                                <div class='value'>$empresa</div>
                            </div>
                            <div class='field'>
                                <div class='label'>Número de Unidades:</div>
                                <div class='value'>$unidades</div>
                            </div>
                            " . (!empty($comments) ? "
                            <div class='field'>
                                <div class='label'>Comentarios:</div>
                                <div class='value'>" . nl2br($comments) . "</div>
                            </div>
                            " : "") . "
                        </div>
                    </div>
                </body>
                </html>
                ";

                $headers .= "From: $from_name <$from_email>" . "\r\n";
                $headers .= "Reply-To: $email" . "\r\n";
            }
            break;

        case 'get-resihub':
            // Formulario de Obtener ResiHub (get-resihub.html)
            $name = isset($_POST['name']) ? sanitize_input($_POST['name']) : '';
            $email = isset($_POST['email']) ? sanitize_input($_POST['email']) : '';
            $phone = isset($_POST['phone']) ? sanitize_input($_POST['phone']) : '';
            $property = isset($_POST['property']) ? sanitize_input($_POST['property']) : '';
            $property_type = isset($_POST['property_type']) ? sanitize_input($_POST['property_type']) : '';
            $units = isset($_POST['units']) ? sanitize_input($_POST['units']) : '';
            $location = isset($_POST['location']) ? sanitize_input($_POST['location']) : '';
            $plan = isset($_POST['plan']) ? sanitize_input($_POST['plan']) : 'profesional';
            $comments = isset($_POST['comments']) ? sanitize_input($_POST['comments']) : '';
            $current_system = isset($_POST['current_system']) ? sanitize_input($_POST['current_system']) : '';

            // Mapear tipos de propiedad a nombres legibles
            $property_types_map = [
                'condominio' => 'Condominio',
                'apartamentos' => 'Edificio de Apartamentos',
                'villas' => 'Villas / Casas',
                'locales' => 'Locales Comerciales',
                'mixto' => 'Uso Mixto',
                'otro' => 'Otro'
            ];
            $property_type_label = isset($property_types_map[$property_type]) ? $property_types_map[$property_type] : $property_type;

            // Mapear sistemas actuales a nombres legibles
            $current_systems_map = [
                'excel' => 'Excel / Hojas de cálculo',
                'otro-software' => 'Otro software de gestión',
                'papel' => 'Registros en papel',
                'ninguno' => 'No usa ningún sistema'
            ];
            $current_system_label = isset($current_systems_map[$current_system]) ? $current_systems_map[$current_system] : $current_system;

            // Mapear planes a nombres legibles
            $plans_map = [
                'basico' => 'Básico (hasta 50 unidades)',
                'profesional' => 'Profesional (hasta 200 unidades)',
                'enterprise' => 'Enterprise (unidades ilimitadas)'
            ];
            $plan_label = isset($plans_map[$plan]) ? $plans_map[$plan] : $plan;

            if (empty($name)) {
                $errors[] = "El nombre es requerido";
            }

            if (empty($email) || !validate_email($email)) {
                $errors[] = "Un email válido es requerido";
            }

            if (empty($phone)) {
                $errors[] = "El teléfono es requerido";
            }

            if (empty($property)) {
                $errors[] = "El nombre de la propiedad es requerido";
            }

            if (empty($property_type)) {
                $errors[] = "El tipo de propiedad es requerido";
            }

            if (empty($units)) {
                $errors[] = "El número de unidades es requerido";
            }

            if (empty($location)) {
                $errors[] = "La ubicación es requerida";
            }

            if (empty($errors)) {
                $subject = "Nueva Solicitud de ResiHub - $property ($plan_label)";
                $email_body = "
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background: linear-gradient(135deg, #8b5cf6, #a855f7, #d946ef); color: white; padding: 25px; text-align: center; border-radius: 10px 10px 0 0; }
                        .content { background-color: #f9fafb; padding: 25px; border-radius: 0 0 10px 10px; }
                        .section { margin-bottom: 20px; padding: 15px; background-color: white; border-radius: 8px; border-left: 4px solid #8b5cf6; }
                        .section-title { font-size: 14px; font-weight: bold; color: #8b5cf6; margin-bottom: 10px; text-transform: uppercase; }
                        .field { margin-bottom: 12px; }
                        .label { font-weight: bold; color: #374151; font-size: 13px; }
                        .value { margin-top: 3px; padding: 8px 12px; background-color: #f3f4f6; border-radius: 5px; color: #1f2937; }
                        .plan-badge { display: inline-block; background-color: rgba(255,255,255,0.25); color: white; padding: 8px 20px; border-radius: 25px; margin-top: 10px; font-weight: bold; }
                        .highlight { background-color: #fef3c7; border-left-color: #f59e0b; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h2 style='margin: 0 0 5px 0;'>Nueva Solicitud de ResiHub</h2>
                            <p style='margin: 0; opacity: 0.9;'>get.resihubapp.com</p>
                            <div class='plan-badge'>$plan_label</div>
                        </div>
                        <div class='content'>
                            <div class='section'>
                                <div class='section-title'>Información Personal</div>
                                <div class='field'>
                                    <div class='label'>Nombre Completo:</div>
                                    <div class='value'>$name</div>
                                </div>
                                <div class='field'>
                                    <div class='label'>Email:</div>
                                    <div class='value'>$email</div>
                                </div>
                                <div class='field'>
                                    <div class='label'>Teléfono:</div>
                                    <div class='value'>$phone</div>
                                </div>
                            </div>

                            <div class='section highlight'>
                                <div class='section-title'>Información de la Propiedad</div>
                                <div class='field'>
                                    <div class='label'>Nombre del Condominio/Propiedad:</div>
                                    <div class='value'>$property</div>
                                </div>
                                <div class='field'>
                                    <div class='label'>Tipo de Propiedad:</div>
                                    <div class='value'>$property_type_label</div>
                                </div>
                                <div class='field'>
                                    <div class='label'>Número de Unidades:</div>
                                    <div class='value'>$units</div>
                                </div>
                                <div class='field'>
                                    <div class='label'>Ubicación:</div>
                                    <div class='value'>$location</div>
                                </div>
                            </div>

                            " . (!empty($current_system) ? "
                            <div class='section'>
                                <div class='section-title'>Sistema Actual</div>
                                <div class='field'>
                                    <div class='label'>Sistema que usa actualmente:</div>
                                    <div class='value'>$current_system_label</div>
                                </div>
                            </div>
                            " : "") . "

                            " . (!empty($comments) ? "
                            <div class='section'>
                                <div class='section-title'>Comentarios Adicionales</div>
                                <div class='field'>
                                    <div class='value'>" . nl2br($comments) . "</div>
                                </div>
                            </div>
                            " : "") . "
                        </div>
                    </div>
                </body>
                </html>
                ";

                $headers .= "From: $from_name <$from_email>" . "\r\n";
                $headers .= "Reply-To: $email" . "\r\n";
            }
            break;

        case 'sales':
            // Formulario de ventas
            $name = isset($_POST['name']) ? sanitize_input($_POST['name']) : '';
            $email = isset($_POST['email']) ? sanitize_input($_POST['email']) : '';
            $phone = isset($_POST['phone']) ? sanitize_input($_POST['phone']) : '';
            $empresa = isset($_POST['empresa']) ? sanitize_input($_POST['empresa']) : '';
            $message = isset($_POST['message']) ? sanitize_input($_POST['message']) : '';
            $plan = isset($_POST['plan']) ? sanitize_input($_POST['plan']) : 'No especificado';

            if (empty($name)) {
                $errors[] = "El nombre es requerido";
            }

            if (empty($email) || !validate_email($email)) {
                $errors[] = "Un email válido es requerido";
            }

            if (empty($phone)) {
                $errors[] = "El teléfono es requerido";
            }

            if (empty($empresa)) {
                $errors[] = "El nombre de la empresa es requerido";
            }

            if (empty($message)) {
                $errors[] = "El mensaje es requerido";
            }

            if (empty($errors)) {
                $subject = "Contacto de Ventas - Plan: $plan";
                $email_body = "
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background-color: #7c3aed; color: white; padding: 20px; text-align: center; }
                        .content { background-color: #f9fafb; padding: 20px; }
                        .field { margin-bottom: 15px; }
                        .label { font-weight: bold; color: #7c3aed; }
                        .value { margin-top: 5px; padding: 10px; background-color: white; border-left: 3px solid #7c3aed; }
                        .plan-badge { display: inline-block; background-color: #7c3aed; color: white; padding: 5px 15px; border-radius: 20px; margin-bottom: 10px; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h2>Nuevo Contacto de Ventas</h2>
                            <div class='plan-badge'>Plan: $plan</div>
                        </div>
                        <div class='content'>
                            <div class='field'>
                                <div class='label'>Nombre:</div>
                                <div class='value'>$name</div>
                            </div>
                            <div class='field'>
                                <div class='label'>Email:</div>
                                <div class='value'>$email</div>
                            </div>
                            <div class='field'>
                                <div class='label'>Teléfono:</div>
                                <div class='value'>$phone</div>
                            </div>
                            <div class='field'>
                                <div class='label'>Empresa:</div>
                                <div class='value'>$empresa</div>
                            </div>
                            <div class='field'>
                                <div class='label'>Mensaje:</div>
                                <div class='value'>" . nl2br($message) . "</div>
                            </div>
                        </div>
                    </div>
                </body>
                </html>
                ";

                $headers .= "From: $from_name <$from_email>" . "\r\n";
                $headers .= "Reply-To: $email" . "\r\n";
            }
            break;

        default:
            $errors[] = "Tipo de formulario no válido";
    }

    // Enviar el email si no hay errores
    if (empty($errors)) {
        if (mail($to_email, $subject, $email_body, $headers)) {
            $response['success'] = true;
            $response['message'] = "¡Gracias! Hemos recibido tu mensaje. Te contactaremos pronto.";
        } else {
            $response['success'] = false;
            $response['message'] = "Hubo un error al enviar el mensaje. Por favor, intenta nuevamente.";
        }
    } else {
        $response['success'] = false;
        $response['message'] = "Errores de validación";
        $response['errors'] = $errors;
    }

    // Devolver respuesta JSON
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
} else {
    // Si no es POST, redirigir a la página principal
    header('Location: index.html');
    exit;
}
?>
