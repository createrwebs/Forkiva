<?php

return [
    'inventory' => 'คลังสินค้า',

    'analytics' => [
        'top_suppliers' => 'ซัพพลายเออร์ชั้นนำตามยอดซื้อ',
        'ingredient_purchases' => 'สรุปการซื้อวัตถุดิบ',
        'stock_movements' => 'สรุปการเคลื่อนไหวสต็อก',
        'wastage_and_spoilage' => 'ของเสียและเน่าเสีย',
        'purchase_status' => 'สรุปสถานะใบสั่งซื้อ',
        'purchase_amount' => 'ยอดซื้อ',
        'quantity' => 'จำนวน',
        'no_data_available' => 'ไม่มีข้อมูล',
        'from' => 'ตั้งแต่',
        'to' => 'ถึง',
        'branch' => 'สาขา',
        'filters' => 'ตัวกรอง',
        'low_stock_ingredients' => 'วัตถุดิบสต็อกต่ำ',
        'alert_quantity' => 'จำนวนแจ้งเตือน',
        'purchased_quantity' => 'จำนวนที่ซื้อ',
        'fast_moving_ingredients' => 'วัตถุดิบเคลื่อนไหวเร็ว',
        'most_wasted_ingredients' => 'วัตถุดิบที่เสียมากที่สุด',
        'page' => [
            'title' => 'วิเคราะห์คลังสินค้า',
            'subtitle' => 'สุขภาพสต็อก การซื้อ และการเคลื่อนไหวในมุมมองเดียว',
        ],
        'date' => 'วันที่',
        'all_branches' => 'ทุกสาขา',
        'assigned_branch' => 'สาขาที่กำหนด',
        'last_updated' => 'อัปเดต {time}',
        'previous_period' => 'เทียบกับช่วงก่อนหน้า',
        'total' => 'รวม',
        'reorder_level' => 'จุดสั่งซื้อซ้ำ',
        'top_count' => '{count} อันดับแรก',
        'periods' => [
            'last_7_days' => '7 วันที่แล้ว',
            'last_30_days' => '30 วันที่แล้ว',
            'last_90_days' => '90 วันที่แล้ว',
            'this_year' => 'ปีนี้',
            'custom' => 'กำหนดช่วงเอง',
        ],
        'actions' => [
            'refresh' => 'รีเฟรช',
            'customize' => 'ปรับแต่งแดชบอร์ด',
            'apply' => 'ใช้ช่วง',
            'reset' => 'รีเซ็ตเลย์เอาต์',
            'done' => 'เสร็จสิ้น',
            'minimize' => 'ย่อวิดเจ็ต',
            'expand' => 'ขยายวิดเจ็ต',
        ],
        'categories' => [
            'health' => 'สุขภาพคลังสินค้า',
            'movement' => 'การเคลื่อนไหวสต็อก',
            'purchasing' => 'การซื้อ',
            'risk' => 'ความเสี่ยงสต็อก',
        ],
        'customizer' => [
            'title' => 'ปรับแต่งการวิเคราะห์คลังสินค้า',
            'subtitle' => 'เลือก จัดเรียง และย่อวิดเจ็ตที่สำคัญกับคุณ',
            'search' => 'ค้นหาวิดเจ็ต',
            'visible' => 'แสดง',
            'hidden' => 'ซ่อน',
            'drag_hint' => 'ลากวิดเจ็ตเพื่อเปลี่ยนลำดับการแสดง',
            'empty' => 'ไม่พบวิดเจ็ตที่ตรงกับการค้นหา',
            'drag_label' => 'ย้าย {widget} ลากไปที่ใดก็ได้ หรือใช้ปุ่มลูกศร Home และ End',
            'drop_hint' => 'วางวิดเจ็ตในตำแหน่งใหม่',
            'position' => '{widget} ย้ายไปที่คอลัมน์ {column} แถว {row}',
        ],
        'empty' => [
            'title' => 'แดชบอร์ดคลังสินค้าของคุณว่างเปล่า',
            'subtitle' => 'เลือกวิดเจ็ตที่คุณต้องการติดตาม',
        ],
        'errors' => [
            'load' => 'ไม่สามารถโหลดการวิเคราะห์คลังสินค้าได้',
            'load_filters' => 'ไม่สามารถโหลดตัวกรองการวิเคราะห์คลังสินค้าได้',
        ],
        'widgets' => [
            'inventory_value' => [
                'title' => 'มูลค่าคลังสินค้า',
                'description' => 'มูลค่าสต็อกคงเหลือปัจจุบัน',
            ],
            'low_stock_items' => [
                'title' => 'รายการสต็อกต่ำ',
                'description' => 'วัตถุดิบที่ต่ำกว่าหรือเท่ากับจุดสั่งซื้อซ้ำ',
            ],
            'purchase_spend' => [
                'title' => 'ยอดใช้จ่ายซื้อ',
                'description' => 'มูลค่าใบสั่งซื้อในช่วงที่เลือก',
            ],
            'waste_rate' => [
                'title' => 'อัตราของเสีย',
                'description' => 'ของเสียและเน่าเสียเป็นสัดส่วนของการใช้สต็อก',
            ],
            'fast_moving_ingredients' => [
                'title' => 'วัตถุดิบเคลื่อนไหวเร็ว',
                'description' => 'วัตถุดิบชั้นนำตามปริมาณเบิกและใช้ในช่วงเวลา',
            ],
            'stock_movement' => [
                'title' => 'การเคลื่อนไหวสต็อก',
                'description' => 'จำนวนที่รับ เบิก ปรับปรุง และโอน',
            ],
            'top_suppliers' => [
                'title' => 'ซัพพลายเออร์ชั้นนำ',
                'description' => 'ซัพพลายเออร์จัดอันดับตามยอดซื้อ',
            ],
            'low_stock_ingredients' => [
                'title' => 'วัตถุดิบสต็อกต่ำ',
                'description' => 'รายการที่ต้องสนใจเติมสต็อก',
            ],
            'purchase_status' => [
                'title' => 'สถานะการซื้อ',
                'description' => 'ใบสั่งซื้อจัดกลุ่มตามสถานะเวิร์กโฟลว์',
            ],
            'most_wasted_ingredients' => [
                'title' => 'วัตถุดิบที่เสียมากที่สุด',
                'description' => 'วัตถุดิบจัดอันดับตามจำนวนของเสียและเน่าเสีย',
            ],
            'ingredient_purchases' => [
                'title' => 'การซื้อวัตถุดิบ',
                'description' => 'วัตถุดิบที่ซื้อจัดอันดับตามจำนวน',
            ],
            'wastage_spoilage' => [
                'title' => 'ของเสียและเน่าเสีย',
                'description' => 'สัดส่วนการเคลื่อนไหวของเสียและเน่าเสีย',
            ],
        ],
    ],
];