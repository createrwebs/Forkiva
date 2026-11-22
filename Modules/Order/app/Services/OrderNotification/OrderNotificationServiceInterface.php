<?php

namespace Modules\Order\Services\OrderNotification;

use Modules\Order\Events\OrderUpdateStatus;
use Modules\Order\Events\OrderVoided;
use Modules\Order\Models\Order;
use Modules\SeatingPlan\Models\TableMerge;

interface OrderNotificationServiceInterface
{
    /**
     * Send notification for a newly created order.
     *
     * Recipients are resolved by configured workflow roles for the
     * `order_created` template.
     *
     * @param  Order  $order  The order model that was just created.
     */
    public function sendCreated(Order $order): void;

    /**
     * Send notification after an order is updated.
     *
     * Recipients are resolved by configured workflow roles for the
     * `order_updated` template.
     *
     * @param  Order  $order  The order model that was updated.
     */
    public function sendUpdated(Order $order): void;

    /**
     * Send the kitchen-ready notification for an order status transition.
     *
     * This workflow is emitted only when the order reaches the ready state.
     */
    public function sendKitchenItemReady(OrderUpdateStatus $event): void;

    /**
     * Send notification when an order status changes.
     *
     * Cancelled and refunded states are handled by the void workflow and
     * should be ignored by this method.
     *
     * @param  OrderUpdateStatus  $event  The order status-change event.
     */
    public function sendStatusChanged(OrderUpdateStatus $event): void;

    /**
     * Send notification when an order is cancelled, refunded, or voided.
     *
     * The notification template is chosen from the event status:
     * `order_cancelled`, `order_refunded`, or `order_voided`.
     *
     * @param  OrderVoided  $event  The voiding event containing order and actor data.
     */
    public function sendVoided(OrderVoided $event): void;

    /**
     * Send notification when an order payment is completed.
     *
     * @param  Order  $order  The order with payment and related data loaded.
     */
    public function sendPaymentCompleted(Order $order): void;

    /**
     * Send payment-completed notifications for all orders in a merged table group.
     *
     * Each order in the merge group is dispatched through the
     * `sendPaymentCompleted()` workflow.
     *
     * @param  TableMerge  $tableMerge  The merged table context containing orders.
     */
    public function sendMergedPaymentCompleted(TableMerge $tableMerge): void;
}
