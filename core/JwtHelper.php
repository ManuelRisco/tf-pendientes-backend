<?php

/**
 * JWT sencillo sin librerías externas.
 * Algoritmo: HS256
 */
class JwtHelper {

    // ⚠️Cambia este secreto por uno fuerte y guárdalo en variable de entorno
    private static string $secret = 'TU_CLAVE_SECRETA_MUY_LARGA_Y_SEGURA_2024';
    private static int    $ttl    = 28800; // 8 horas en segundos

    // -----------------------------------------------------------------------
    private static function base64UrlEncode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string {
        $padded = str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT);
        return base64_decode($padded);
    }

    private static function sign(string $header, string $payload): string {
        return self::base64UrlEncode(
            hash_hmac('sha256', "$header.$payload", self::$secret, true)
        );
    }

    // -----------------------------------------------------------------------

    public static function generate(array $payload): string {
        $header  = self::base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload['iat'] = time();
        $payload['exp'] = time() + self::$ttl;
        $body    = self::base64UrlEncode(json_encode($payload));
        $sig     = self::sign($header, $body);
        return "$header.$body.$sig";
    }

    /**
     * @throws RuntimeException si el token es inválido o expiró
     */
    public static function verify(string $token): array {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new RuntimeException('Token malformado');
        }

        [$header, $body, $sig] = $parts;
        $expected = self::sign($header, $body);

        if (!hash_equals($expected, $sig)) {
            throw new RuntimeException('Firma inválida');
        }

        $payload = json_decode(self::base64UrlDecode($body), true);
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            throw new RuntimeException('Token expirado');
        }

        return $payload;
    }

    /**
     * Extrae el payload del Authorization header.
     * Lanza RuntimeException si no hay token o es inválido.
     */
    public static function fromRequest(): array {
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if (empty($auth) && function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            $auth    = $headers['Authorization'] ?? '';
        }

        if (!str_starts_with($auth, 'Bearer ')) {
            throw new RuntimeException('Token no proporcionado');
        }

        return self::verify(substr($auth, 7));
    }
}
