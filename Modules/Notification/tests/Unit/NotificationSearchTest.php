<?php

namespace Modules\Notification\Tests\Unit;

use Modules\Notification\Support\NotificationSearch;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class NotificationSearchTest extends TestCase
{
    #[DataProvider('queries')]
    public function test_it_builds_safe_required_prefix_terms(string $search, string $expected): void
    {
        $this->assertSame($expected, NotificationSearch::booleanQuery($search));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function queries(): array
    {
        return [
            'normalizes and deduplicates terms' => [' Email address! email ', '+email* +address*'],
            'preserves Arabic search terms' => ['تحديث البريد', '+تحديث* +البريد*'],
            'drops terms below the index token floor' => ['a of', ''],
            'removes boolean query operators' => ['+invoice -refund', '+invoice* +refund*'],
        ];
    }
}
