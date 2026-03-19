<?php
declare(strict_types=1);

namespace App\Helpers;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class MailHelper
{
    public static function enviar(string $tipo, string $destinatario, string $asunto, string $cuerpoHTML): array
    {
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = $_ENV['MAIL_HOST'];
            $mail->SMTPAuth = true;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = $_ENV['MAIL_PORT'];
            $mail->CharSet = 'UTF-8';

            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];

            if ($tipo === 'bienvenida') {
                $mail->Username = $_ENV['MAIL_BIENVENIDA_USER'];
                $mail->Password = $_ENV['MAIL_BIENVENIDA_PASS'];
                $mail->setFrom($_ENV['MAIL_BIENVENIDA_USER'], 'ERP Contable - Atlas');
            } elseif ($tipo === 'reportes') {
                $mail->Username = $_ENV['MAIL_REPORTES_USER'];
                $mail->Password = $_ENV['MAIL_REPORTES_PASS'];
                $mail->setFrom($_ENV['MAIL_REPORTES_USER'], 'Reportes ERP - Atlas');
            }

            $mail->addAddress($destinatario);
            $mail->isHTML(true);
            $mail->Subject = $asunto;
            $mail->Body = $cuerpoHTML;

            $mail->send();
            return ['success' => true, 'error' => null];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $mail->ErrorInfo];
        }
    }
}