<?php

namespace App\Services;

use App\Models\User;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DatabaseActor
{
    private const MESSAGES = [
        'app_auth' => 'Sign in to continue.',
        'app_admin' => 'Campus administrator access is required.',
        'app_dates' => 'Choose a valid reservation window.',
        'app_state' => 'This action is no longer available for that request.',
        'app_room_unavailable' => 'Ruangan pilihan sedang tidak tersedia.',
        'app_capacity' => 'Jumlah peserta dan panitia melebihi kapasitas ruangan.',
        'app_facilities' => 'Ruangan pilihan tidak memiliki semua fasilitas yang diminta.',
        'app_room_conflict' => 'Ruangan sudah digunakan pada jam tersebut.',
        'app_equipment_conflict' => 'Stok peralatan tidak mencukupi pada jadwal ini.',
        'app_items' => 'Pilih ruangan atau setidaknya satu peralatan.',
        'app_plan' => 'The room plan does not match this booking.',
        'app_plan_changed' => 'The room geometry or furniture changed. Review the plan and try again.',
        'app_reason' => 'Enter a rejection reason between 3 and 1,000 characters.',
        'app_room_allocated' => 'This change would affect an approved room reservation or plan.',
        'app_equipment_allocated' => 'Approved bookings or active loans still require this stock.',
        'app_pickup_time' => 'Record pickup during the approved reservation window.',
        'app_return_counts' => 'The sum of missing and damaged quantities must fit the borrowed quantity.',
        '23505' => 'This resource or selection already exists.',
        '23503' => 'A selected resource no longer exists. Refresh and try again.',
        '23514' => 'One or more values are outside the permitted range.',
    ];

    public function run(User $actor, Closure $operation): mixed
    {
        try {
            return DB::transaction(function () use ($actor, $operation) {
                // The verified Laravel identity is available only to this database transaction.
                DB::selectOne("select set_config('request.jwt.claim.sub', ?, true), set_config('request.jwt.claims', ?, true)", [
                    $actor->id,
                    json_encode(['sub' => $actor->id], JSON_THROW_ON_ERROR),
                ]);

                return $operation();
            }, 3);
        } catch (QueryException $exception) {
            preg_match('/\bapp_[a-z_]+\b/', $exception->errorInfo[2] ?? '', $match);
            $code = $match[0] ?? $exception->getCode();

            if (isset(self::MESSAGES[$code])) {
                throw ValidationException::withMessages(['operation' => self::MESSAGES[$code]]);
            }

            throw $exception;
        }
    }
}
