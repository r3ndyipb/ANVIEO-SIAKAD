<?php

namespace Tests\Feature;

use Tests\TestCase;

class MailConfigurationTest extends TestCase
{
    public function test_smtp_failover_uses_only_configured_delivery_mailers(): void
    {
        $failoverMailers = config('mail.mailers.failover.mailers');

        $this->assertSame('failover', config('mail.mailers.failover.transport'));
        $this->assertSame('smtp', $failoverMailers[0]);
        $this->assertNotContains('log', $failoverMailers);

        foreach ($failoverMailers as $mailer) {
            $this->assertArrayHasKey($mailer, config('mail.mailers'));
            $this->assertSame('smtp', config("mail.mailers.{$mailer}.transport"));
        }
    }
}
