<?php

namespace Tests\Unit;

use App\Support\ChatMessageText;
use PHPUnit\Framework\TestCase;

class ChatMessageTextTest extends TestCase
{
    public function test_it_encodes_supplementary_unicode_for_legacy_database_columns_and_decodes_it(): void
    {
        $emoji = '👍🏽😊 &#128522;';
        $stored = ChatMessageText::encodeForStorage($emoji);

        $this->assertSame('&#128077;&#127997;&#128522; &amp;#128522;', $stored);
        $this->assertSame($emoji, ChatMessageText::decodeFromStorage($stored));
    }

    public function test_it_renders_only_supported_message_markup_and_escapes_other_html(): void
    {
        $html = ChatMessageText::toSafeHtml('Halo <b>semua</b><br>👍🏽<script>alert(1)</script>');

        $this->assertSame(
            'Halo <strong>semua</strong><br>👍🏽&lt;script&gt;alert(1)&lt;/script&gt;',
            (string) $html
        );
    }
}
