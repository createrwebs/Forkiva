<?php

namespace Modules\Printer\Tests\Unit;

use Modules\Printer\Enum\PrintContentType;
use Modules\Printer\Enum\PrinterPaperSize;
use Modules\Printer\Factories\PrintContents\PrintLabelContentFactory;
use PHPUnit\Framework\TestCase;

class PrintLabelContentFactoryTest extends TestCase
{
    public function test_label_content_type_and_paper_size_exist(): void
    {
        $this->assertSame('label', PrintContentType::Label->value);
        $this->assertSame('50x30mm', PrinterPaperSize::Label50x30mm->value);
    }

    public function test_expands_order_items_into_individual_cup_stickers(): void
    {
        $factory = new PrintLabelContentFactory;

        $stickers = $factory->expandLabels([
            'order_reference_no' => 'ORD-0042',
            'order_type' => 'takeaway',
            'created_at' => '2026-11-22 14:30',
            'items' => [
                [
                    'name' => 'ชาไทยเย็น',
                    'quantity' => 2,
                    'options' => ['หวาน 50%', 'เพิ่มไข่มุก'],
                ],
                [
                    'name' => 'อเมริกาโน่เย็น',
                    'quantity' => 1,
                    'options' => ['ไม่หวาน'],
                ],
            ],
        ]);

        $this->assertCount(3, $stickers);
        $this->assertSame('1/3', $stickers[0]['sequence']);
        $this->assertSame('ชาไทยเย็น', $stickers[0]['item_name']);
        $this->assertSame(['หวาน 50%', 'เพิ่มไข่มุก'], $stickers[0]['options']);
        $this->assertSame('2/3', $stickers[1]['sequence']);
        $this->assertSame('3/3', $stickers[2]['sequence']);
        $this->assertSame('อเมริกาโน่เย็น', $stickers[2]['item_name']);
    }
}
