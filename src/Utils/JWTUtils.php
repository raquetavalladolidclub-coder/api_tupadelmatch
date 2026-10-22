<?php
namespace PadelClub\Utils;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Exception;

class JWTUtils
{
    private static $secretKey;
    private static $algorithm = 'HS256';
    
    public static function init()
    {
        self::$secretKey = $_ENV['JWT_SECRET'];
    }
    
    public static function generateToken($userId, $email)
    {
        $issuedAt = time();

        // Sesión mínima de 30 días. Antes, con JWT_EXPIRE=3600 (1 hora),
        // la app obligaba a volver a iniciar sesión continuamente.
        $segundos = (int) ($_ENV['JWT_EXPIRE'] ?? 0);
        $segundos = max($segundos, 60 * 60 * 24 * 30);
        $expire = $issuedAt + $segundos;
        
        $payload = [
            'iat'   => $issuedAt,
            'exp'   => $expire,
            'sub'   => $userId,
            'email' => $email
        ];
        
        return JWT::encode($payload, self::$secretKey, self::$algorithm);
    }
    
    public static function validateToken($token)
    {
        try {
            $decoded = JWT::decode($token, new Key(self::$secretKey, self::$algorithm));
            return (array) $decoded;
        } catch (Exception $e) {
            return false;
        }
    }
    
    public static function getUserIdFromToken($token)
    {
        $data = self::validateToken($token);
        return $data ? $data['sub'] : null;
    }
}