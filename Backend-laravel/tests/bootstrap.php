<?php

/**
 * Bootstrap de PHPUnit — se ejecuta antes que cualquier test.
 *
 * Configura OPENSSL_CONF automáticamente cuando el entorno no tiene
 * un openssl.cnf global (Windows sin XAMPP/Laragon, etc.).
 * Esto permite que TANTO GeneraParRsaParaTests COMO CertificadoService
 * encuentren la configuración vía getenv('OPENSSL_CONF').
 */
$cnfLocal = __DIR__ . DIRECTORY_SEPARATOR . 'openssl.cnf';

$confActual = getenv('OPENSSL_CONF');
if (($confActual === false || $confActual === '' || !file_exists($confActual)) && file_exists($cnfLocal)) {
    putenv('OPENSSL_CONF=' . $cnfLocal);
    $_ENV['OPENSSL_CONF']    = $cnfLocal;
    $_SERVER['OPENSSL_CONF'] = $cnfLocal;
}

require __DIR__ . '/../vendor/autoload.php';
