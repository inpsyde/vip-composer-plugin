<?php

declare(strict_types=1);

return [
    ':default:' => [
        'additionalQueryVars' => static fn (string $sourceHost, array $config): array => [
            'utm_campaign' => sprintf('internal-redirect-%s', Inpsyde\Vip\determineVipEnv()),
            'utm_source' => $sourceHost,
            'utm_medium' => 'Referral',
            'utm_content' => sprintf('%s%s', $sourceHost, Inpsyde\Vip\currentUrlPath()),
        ],
    ],
    'env:staging' => static fn (): array => [
        ':default:' => static fn (): array => [
            'additionalQueryVars' => ['env' => 'staging'],
        ],
    ],
    'www.example.com' => 'example.com',
    'example.dev' => static function (): string|array {
        if (($_GET['utm_campaign'] ?? '') === 'product') {
            return [
                'target' => static fn (string $host): string => "//www.{$host}/product-campaign",
                'preservePath' => false,
                'preserveQuery' => false,
                'additionalQueryVars' => [],
            ];
        }
        return "www.example.dev";
    },
];
