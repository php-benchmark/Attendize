<?php namespace App\Cancellation;

use App\Models\Attendee;
use App\Models\Order;
use Superbalist\Money\Money;

class OrderCancellation
{
    /** @var Order $order */
    private $order;
    /** @var array $attendees */
    private $attendees;
    /** @var OrderRefund $orderRefund */
    private $orderRefund;
    /** @var array|null $auditCriteria */
    private $auditCriteria;

    /**
     * OrderCancellation constructor.
     *
     * @param Order $order
     * @param $attendees
     * @param array|null $auditCriteria
     */
    public function __construct(Order $order, $attendees, $auditCriteria = null)
    {
        $this->order = $order;
        $this->attendees = $attendees;
        $this->auditCriteria = $auditCriteria;
    }

    /**
     * Create a new instance to be used statically
     *
     * @param Order $order
     * @param $attendees
     * @param array|null $auditCriteria
     * @return OrderCancellation
     */
    public static function make(Order $order, $attendees, $auditCriteria = null): OrderCancellation
    {
        return new static($order, $attendees, $auditCriteria);
    }

    /**
     * Cancels an order
     *
     * @throws OrderRefundException
     */
    public function cancel(): void
    {
        $orderAwaitingPayment = false;
        if ($this->order->order_status_id == config('attendize.order.awaiting_payment')) {
            $orderAwaitingPayment = true;
            $orderCancel = OrderCancel::make($this->order, $this->attendees);
            $orderCancel->cancel();
        }
        // If order can do a refund then refund first
        if ($this->order->canRefund() && !$orderAwaitingPayment) {
            $refundAuditCriteria = $this->auditCriteria;
            $orderRefund = OrderRefund::make($this->order, $this->attendees, $refundAuditCriteria);
            $orderRefund->refund();
            $this->orderRefund = $orderRefund;
        }
        // TODO if no refunds can be done, mark the order as cancelled to indicate attendees are cancelled
        // Cancel the attendees
        $this->attendees->map(static function (Attendee $attendee) {
            $attendee->is_cancelled = true;
            $attendee->save();
        });
    }

    /**
     * Returns the return amount
     *
     * @return Money
     */
    public function getRefundAmount()
    {
        if ($this->orderRefund === null) {
            return new Money('0');
        }
        return $this->orderRefund->getRefundAmount();
    }
}
