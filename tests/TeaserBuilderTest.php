<?php
declare(strict_types=1);

namespace PrestaShop\Module\FreeShippingTeaser\Tests;

use PHPUnit\Framework\TestCase;
use PrestaShop\Module\FreeShippingTeaser\TeaserBuilder;

class TeaserBuilderTest extends TestCase
{
    private TeaserBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new TeaserBuilder();
    }

    public function testRemainingIsThresholdMinusCartTotal(): void
    {
        $result = $this->builder->build(100.0, 65.10, '$', 'Spend {amount} more', 'Ships FREE!');
        $this->assertEqualsWithDelta(34.90, $result['remaining'], 0.001);
    }

    public function testRemainingIsZeroWhenCartExceedsThreshold(): void
    {
        $result = $this->builder->build(100.0, 150.0, '$', 'Spend {amount} more', 'Ships FREE!');
        $this->assertSame(0.0, $result['remaining']);
    }

    public function testPercentReflectsCartProgress(): void
    {
        $result = $this->builder->build(100.0, 65.0, '$', 'Spend {amount} more', 'Ships FREE!');
        $this->assertSame(65, $result['percent']);
    }

    public function testPercentIsCappedAt100(): void
    {
        $result = $this->builder->build(100.0, 150.0, '$', 'Spend {amount} more', 'Ships FREE!');
        $this->assertSame(100, $result['percent']);
    }

    public function testQualifiedWhenCartTotalMeetsThreshold(): void
    {
        $result = $this->builder->build(100.0, 100.0, '$', 'Spend {amount} more', 'Ships FREE!');
        $this->assertTrue($result['qualified']);
    }

    public function testQualifiedWhenCartTotalExceedsThreshold(): void
    {
        $result = $this->builder->build(100.0, 120.0, '$', 'Spend {amount} more', 'Ships FREE!');
        $this->assertTrue($result['qualified']);
    }

    public function testNotQualifiedWhenBelowThreshold(): void
    {
        $result = $this->builder->build(100.0, 99.99, '$', 'Spend {amount} more', 'Ships FREE!');
        $this->assertFalse($result['qualified']);
    }

    public function testAmountTokenReplacedInTeaserText(): void
    {
        $result = $this->builder->build(100.0, 65.10, '$', 'Spend {amount} more', 'Ships FREE!');
        $this->assertStringContainsString('$34.90', $result['teaser_text']);
    }

    public function testThresholdTokenReplacedInTeaserText(): void
    {
        $result = $this->builder->build(100.0, 65.10, '$', 'Free at {threshold}', 'Ships FREE!');
        $this->assertStringContainsString('$100.00', $result['teaser_text']);
    }

    public function testThresholdTokenReplacedInSuccessText(): void
    {
        $result = $this->builder->build(100.0, 100.0, '$', 'Spend {amount} more', 'Reached {threshold}!');
        $this->assertStringContainsString('$100.00', $result['success_text']);
    }

    public function testCurrencyTokenReplaced(): void
    {
        $result = $this->builder->build(100.0, 50.0, '€', 'Currency: {currency}', 'Done');
        $this->assertStringContainsString('€', $result['teaser_text']);
    }

    public function testOutputContainsAllRequiredKeys(): void
    {
        $result = $this->builder->build(100.0, 50.0, '$', 'Spend {amount} more', 'Ships FREE!');
        foreach (['threshold', 'cart_total', 'remaining', 'currency_sign', 'percent', 'qualified', 'teaser_text', 'success_text'] as $key) {
            $this->assertArrayHasKey($key, $result);
        }
    }
}
