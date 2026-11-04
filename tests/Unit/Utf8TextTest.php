<?php

namespace Tests\Unit;

use App\Support\Utf8Text;
use PHPUnit\Framework\TestCase;

class Utf8TextTest extends TestCase
{
    public function test_it_repairs_windows_1252_mojibake_emojis_and_preserves_valid_unicode(): void
    {
        $broken = "baik Pak\u{00F0}\u{0178}\u{2122}\u{008F}\u{00F0}\u{0178}\u{008F}\u{00BB}";
        $expected = "baik Pak\u{1F64F}\u{1F3FB}";
        $valid = "baik Pak\u{1F64F}\u{1F3FB}";

        $this->assertSame($expected, Utf8Text::repairMojibake($broken));
        $this->assertSame($valid, Utf8Text::repairMojibake($valid));
        $this->assertSame('Teks biasa', Utf8Text::repairMojibake('Teks biasa'));
    }
}
