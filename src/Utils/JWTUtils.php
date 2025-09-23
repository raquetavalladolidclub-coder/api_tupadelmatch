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
        $expire = $issuedAt + $_ENV['JWT_EXPIRE'];
        
        $payload = [
            'iat' => $issuedAt,
            'exp' => $expire,
            'sub' => $userId,
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