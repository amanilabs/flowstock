<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CancelOrderRequest;
use App\Http\Requests\RefundOrderRequest;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Warehouse;
use App\Services\OrderService;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orderService) {}

    /** Requires the view-orders permission. */
    public function index(Request $request)
    {
        $orders = Order::query()
            ->with(['customer', 'warehouse'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('customer_id'), fn ($q) => $q->where('customer_id', $request->integer('customer_id')))
            ->paginate($request->integer('per_page', 15));

        return OrderResource::collection($orders);
    }

    /** Requires the view-orders permission. */
    public function show(Order $order)
    {
        return new OrderResource($order->load(['customer', 'warehouse', 'items.product', 'items.reservation']));
    }

    /** Requires the create-orders permission. */
    public function store(StoreOrderRequest $request)
    {
        $customer = Customer::findOrFail($request->validated('customer_id'));
        $warehouse = Warehouse::findOrFail($request->validated('warehouse_id'));

        $order = $this->orderService->createOrder(
            $customer,
            $warehouse,
            $request->validated('items'),
            $request->validated('notes'),
        );

        return (new OrderResource($order->load(['customer', 'warehouse', 'items.product'])))
            ->response()->setStatusCode(201);
    }

    /** Requires the manage-orders permission. */
    #[Response(status: 409, description: 'Invalid order status transition for the order\'s current state.', type: 'array{message: string}')]
    public function confirm(Order $order)
    {
        return new OrderResource($this->orderService->confirmOrder($order)->load('items.reservation'));
    }

    /** Requires the manage-orders permission. */
    #[Response(status: 409, description: 'Invalid order status transition for the order\'s current state.', type: 'array{message: string}')]
    public function process(Order $order)
    {
        return new OrderResource($this->orderService->markProcessing($order));
    }

    /** Requires the manage-orders permission. */
    #[Response(status: 409, description: 'Invalid order status transition for the order\'s current state.', type: 'array{message: string}')]
    public function ship(Request $request, Order $order)
    {
        return new OrderResource($this->orderService->shipOrder($order, $request->user()->id)->load('items.reservation'));
    }

    /** Requires the manage-orders permission. */
    #[Response(status: 409, description: 'Invalid order status transition for the order\'s current state.', type: 'array{message: string}')]
    public function deliver(Order $order)
    {
        return new OrderResource($this->orderService->markDelivered($order));
    }

    /** Requires the cancel-orders permission. */
    #[Response(status: 409, description: 'Invalid order status transition for the order\'s current state.', type: 'array{message: string}')]
    public function cancel(CancelOrderRequest $request, Order $order)
    {
        return new OrderResource(
            $this->orderService->cancelOrder($order, $request->validated('reason'))->load('items.reservation')
        );
    }

    /** Requires the refund-orders permission. */
    #[Response(status: 409, description: 'Invalid order status transition for the order\'s current state.', type: 'array{message: string}')]
    public function refund(RefundOrderRequest $request, Order $order)
    {
        return new OrderResource($this->orderService->refundOrder($order, $request->validated('reason')));
    }
}
