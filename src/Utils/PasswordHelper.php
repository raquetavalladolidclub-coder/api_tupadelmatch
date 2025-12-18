<?php
namespace PadelClub\Utils;

class PasswordHelper
{
    /**
     * Normaliza una contraseña para evitar problemas con caracteres especiales
     */
    public static function normalizePassword($password)
    {
        // Convertir a UTF-8 consistentemente
        if (!mb_check_encoding($password, 'UTF-8')) {
            $password = mb_convert_encoding($password, 'UTF-8');
        }
        
        // Normalizar caracteres Unicode
        if (class_exists('Normalizer')) {
            $password = \Normalizer::normalize($password, \Normalizer::FORM_C);
        }
        
        return $password;
    }
    
    /**
     * Crea hash de contraseña de manera segura
     */
    public static function hash($password)
    {
        $normalized = self::normalizePassword($password);
        return password_hash($normalized, PASSWORD_BCRYPT);
    }
    
    /**
     * Verifica contraseña con múltiples intentos
     */
    public static function verify($password, $hash)
    {
        // Intentar con la contraseña tal cual
        if (password_verify($password, $hash)) {
            return true;
        }
        
        // Intentar con la contraseña normalizada
        $normalized = self::normalizePassword($password);
        if (password_verify($normalized, $hash)) {
            return true;
        }
        
        // Intentar con trim (por si hay espacios)
        $trimmed = trim($password);
        if ($trimmed !== $password && password_verify($trimmed, $hash)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Verifica si una contraseña necesita rehash (cuando cambian las opciones)
     */
    public static function needsRehash($hash)
    {
        return password_needs_rehash($hash, PASSWORD_BCRYPT);
    }
}