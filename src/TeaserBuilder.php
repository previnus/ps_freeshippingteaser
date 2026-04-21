<?php
declare(strict_types=1);

namespace PrestaShop\Module\FreeShippingTeaser;

class TeaserBuilder
{
    public function build(
        float $threshold,
        float $cartTotal,
        string $currencySign,
        string $teaserTemplate,
        string $successTemplate
    ): array {
        $qualified = $cartTotal >= $threshold;
        $remaining = $qualified ? 0.0 : round($threshold - $cartTotal, 2);
        $percent   = $threshold > 0 ? min(100, (int) (($cartTotal / $threshold) * 100)) : 0;

        return [
            'threshold'     => $threshold,
            'cart_total'    => $cartTotal,
            'remaining'     => $remaining,
            'currency_sign' => $currencySign,
            'percent'       => $percent,
            'qualified'     => $qualified,
            'teaser_text'   => $this->replaceTokens($teaserTemplate, $remaining, $threshold, $currencySign),
            'success_text'  => $this->replaceTokens($successTemplate, $remaining, $threshold, $currencySign),
        ];
    }

    private function replaceTokens(
        string $template,
        float $amount,
        float $threshold,
        string $currencySign
    ): string {
        return str_replace(
            ['{amount}', '{threshold}', '{currency}'],
            [
                $currencySign . number_format($amount, 2),
                $currencySign . number_format($threshold, 2),
                $currencySign,
            ],
            $template
        );
    }
}
