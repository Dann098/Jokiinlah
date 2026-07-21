<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class CodeGenerator
{
    public function generate(string $sequenceKey, string $prefix): string
    {
        return DB::transaction(function () use ($sequenceKey, $prefix): string {
            $date = CarbonImmutable::now(config('jokiinlah.display_timezone'))->toDateString();

            DB::table('code_sequences')->insertOrIgnore([
                'sequence_key' => $sequenceKey,
                'sequence_date' => $date,
                'last_number' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $sequence = DB::table('code_sequences')
                ->where('sequence_key', $sequenceKey)
                ->where('sequence_date', $date)
                ->lockForUpdate()
                ->firstOrFail();

            $number = $sequence->last_number + 1;
            DB::table('code_sequences')->where('id', $sequence->id)->update([
                'last_number' => $number,
                'updated_at' => now(),
            ]);

            return sprintf('%s-%s-%04d', $prefix, str_replace('-', '', $date), $number);
        }, 3);
    }
}
