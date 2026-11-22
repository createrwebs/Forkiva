<?php

use Modules\GiftCard\Services\GiftCard\GiftCardServiceInterface;
use Modules\GiftCard\Services\GiftCardBatch\GiftCardBatchServiceInterface;
use Modules\GiftCard\Services\GiftCardBreakage\GiftCardBreakageServiceInterface;
use Modules\GiftCard\Services\GiftCardTransaction\GiftCardTransactionServiceInterface;
use Modules\User\Enums\PermissionAction as Action;

return [
    'permissions' => [
        'gift_cards' => [Action::Index, Action::Show, Action::Create, Action::Edit, Action::Destroy, Action::Analytics, Action::Export],
        'gift_card_transactions' => [Action::Index, Action::Show, Action::Create, Action::ManualAdjust, Action::Reverse, Action::Export],
        'gift_card_batches' => [Action::Index, Action::Show, Action::Create, Action::Destroy, Action::Export],
        'gift_card_breakage_batches' => [Action::Index, Action::Show, Action::Create, Action::Review, Action::Post, Action::Reverse, Action::Export],
    ],

    'export_resources' => [
        'gift_cards' => GiftCardServiceInterface::class,
        'gift_card_transactions' => GiftCardTransactionServiceInterface::class,
        'gift_card_batches' => GiftCardBatchServiceInterface::class,
        'gift_card_breakage_batches' => GiftCardBreakageServiceInterface::class,
    ],
];
