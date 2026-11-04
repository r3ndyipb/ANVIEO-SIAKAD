<?php

namespace Tests\Unit;

use App\Support\LecturerName;
use PHPUnit\Framework\TestCase;

class LecturerNameTest extends TestCase
{
    public function test_it_formats_names_with_both_academic_titles(): void
    {
        $this->assertSame('Dr. Nama Dosen, M.Kom.', LecturerName::format('Nama Dosen', 'Dr.', 'M.Kom.'));
        $this->assertSame('Nama Dosen, M.Kom.', LecturerName::format('Nama Dosen', null, ', M.Kom.'));
        $this->assertSame('Nama Dosen', LecturerName::format(' Nama Dosen ', ' ', null));
    }
}
