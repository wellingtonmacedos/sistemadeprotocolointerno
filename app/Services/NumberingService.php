<?php

namespace App\Services;

use App\Models\NumberingSequence;
use Illuminate\Support\Facades\DB;

/**
 * Geração de numeração sequencial por Câmara, escopo, modalidade e exercício,
 * garantindo ausência de duplicidade via transação + bloqueio.
 */
class NumberingService
{
    public function next(int $institutionId, string $scope, string $modality, int $year): int
    {
        $attempts = 0;

        while (true) {
            try {
                return DB::transaction(function () use ($institutionId, $scope, $modality, $year) {
                    $sequence = NumberingSequence::query()
                        ->where('institution_id', $institutionId)
                        ->where('scope', $scope)
                        ->where('modality', $modality)
                        ->where('year', $year)
                        ->lockForUpdate()
                        ->first();

                    if (! $sequence) {
                        $sequence = NumberingSequence::create([
                            'institution_id' => $institutionId,
                            'scope' => $scope,
                            'modality' => $modality,
                            'year' => $year,
                            'last_number' => 0,
                        ]);
                    }

                    $sequence->last_number = $sequence->last_number + 1;
                    $sequence->save();

                    return (int) $sequence->last_number;
                });
            } catch (\Illuminate\Database\QueryException $e) {
                if (++$attempts >= 3) {
                    throw $e;
                }
                usleep(50000);
            }
        }
    }

    public function format(int $number, int $year, int $pad = 6): string
    {
        return str_pad((string) $number, $pad, '0', STR_PAD_LEFT).'/'.$year;
    }
}
