<?php

namespace App\Services;

use App\Exceptions\DomainConflict;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class Idempotency
{
    public function execute(string $scope, string $key, array $payload, callable $operation): array
    {
        if (! preg_match('/^[a-zA-Z0-9-]{16,80}$/', $key)) {
            throw new DomainConflict('invalid_idempotency_key', 'A valid Idempotency-Key is required.', 422);
        }
        $fingerprint = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($scope, $key, $fingerprint, $operation) {
            DB::table('idempotency_records')->insertOrIgnore(['scope' => $scope, 'key' => $key, 'fingerprint' => $fingerprint, 'created_at' => now(), 'updated_at' => now()]);
            $row = DB::table('idempotency_records')->where(compact('scope', 'key'))->lockForUpdate()->first();
            if (! hash_equals($row->fingerprint, $fingerprint)) {
                throw new DomainConflict('idempotency_mismatch', 'This request key was already used with different details.');
            }
            if ($row->response) {
                return json_decode(Crypt::decryptString($row->response), true, 512, JSON_THROW_ON_ERROR);
            }
            $result = $operation();
            DB::table('idempotency_records')->where('id', $row->id)->update(['response' => Crypt::encryptString(json_encode($result, JSON_THROW_ON_ERROR)), 'updated_at' => now()]);

            return $result;
        }, 3);
    }
}
