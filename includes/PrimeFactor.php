<?php
namespace Synergetic\Telegram;
/** Bounded Pollard-rho factorization of Telegram's public 64-bit PQ challenge. */
final class PrimeFactor {
    public static function factor(int $number): int {
        if ($number < 4) { throw new Fault('Invalid Telegram PQ challenge.', 502); }
        foreach ([2, 3, 5, 7, 11, 13, 17, 19, 23] as $prime) { if ($number % $prime === 0) { return $prime; } }
        $n = gmp_init($number); $deadline = hrtime(true) + 8_000_000_000;
        for ($attempt = 0; $attempt < 12; $attempt++) {
            $x = gmp_init(random_int(2, $number - 2)); $y = $x; $c = gmp_init(random_int(1, $number - 1));
            $next = static fn($v) => gmp_mod(gmp_add(gmp_mul($v, $v), $c), $n);
            for ($iteration = 0; $iteration < 250000; $iteration++) {
                if (($iteration & 255) === 0 && hrtime(true) >= $deadline) { throw new Fault('Telegram PQ factorization exceeded its PHP CPU budget.', 503); }
                $x = $next($x); $y = $next($next($y)); $d = gmp_gcd(gmp_abs(gmp_sub($x, $y)), $n);
                if (gmp_cmp($d, 1) === 0) { continue; }
                if (gmp_cmp($d, $n) < 0) { return gmp_intval($d); }
                break;
            }
        }
        throw new Fault('Telegram PQ factorization could not complete within its PHP budget.', 503);
    }
}
