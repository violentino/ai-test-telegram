<?php
namespace App\Services;

class TelegramInit
{
    public static function getUserIdFromInitData(?string $initData, string $botToken): ?int
    {
        if (empty($initData)) return null;
        // Parse query string like format
        parse_str($initData, $data);
        if (!isset($data['hash'])) return null;
        $hash = $data['hash'];
        unset($data['hash']);
        ksort($data);
        $checkString = '';
        foreach ($data as $key => $value) {
            $checkString .= $key . '=' . $value . "\n";
        }
        $secretKey = hash_hmac('sha256', $botToken, 'WebAppData', true);
        $calcHash = bin2hex(hash_hmac('sha256', rtrim($checkString, "\n"), $secretKey, true));
        if (strcasecmp($calcHash, $hash) !== 0) {
            return null;
        }
        if (!empty($data['user'])) {
            $user = json_decode($data['user'], true);
            if (!empty($user['id'])) return (int)$user['id'];
        }
        return null;
    }
}