<?php

namespace Tests\Feature\Payments;

use App\Support\Money\Money;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class MoneyFormattingTest extends TestCase
{
    public function test_amounts_format_in_usd(): void
    {
        $this->assertSame('USD', site_currency()->code);
        $this->assertSame('$1,234.50', money('1234.5'));
        $this->assertSame('-$3.00', money('-3'));
        $this->assertSame('$0.0025', money('0.0025', 2, 4));
        $this->assertSame('$0.10', money('0.1000', 2, 4));
        $this->assertSame('$0.00', money('-0.0000001'));
        $this->assertSame('0.6452', Money::round('0.64516129', 4));
    }

    public function test_no_naira_symbol_or_naira_formatter_is_left_in_views_or_scripts(): void
    {
        $offenders = [];
        foreach ([resource_path('views'), resource_path('js')] as $dir) {
            foreach (File::allFiles($dir) as $file) {
                $contents = $file->getContents();
                if (str_contains($contents, '₦') || str_contains($contents, '&#8358;') || str_contains($contents, "'en-NG'")) {
                    $offenders[] = $file->getRelativePathname();
                }
            }
        }

        $this->assertSame([], $offenders);
    }
}
