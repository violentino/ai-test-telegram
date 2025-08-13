<?php
namespace App\Repositories;

use App\Database\Connection;
use PDO;

class OrderRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Connection::getPdo();
    }

    public function createFromCart(int $tgUserId, string $paymentMethod, array $cartItems, string $currency, ?string $tonInvoiceId = null, ?string $stripeSessionId = null, ?float $totalTon = null, string $shippingMethod = 'pickup', ?string $shippingAddress = null): int
    {
        $totalFiat = 0.0;
        foreach ($cartItems as $item) {
            $totalFiat += (float)$item['price'] * (int)$item['quantity'];
        }
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("INSERT INTO orders(tg_user_id, status, payment_method, total_fiat, total_ton, currency, shipping_method, shipping_address, ton_invoice_id, stripe_session_id) VALUES(:uid,'pending',:pm,:fiat,:ton,:cur,:ship,:addr,:tid,:sid)");
            $stmt->execute([
                ':uid' => $tgUserId,
                ':pm' => $paymentMethod,
                ':fiat' => $totalFiat,
                ':ton' => $totalTon,
                ':cur' => $currency,
                ':ship' => $shippingMethod,
                ':addr' => $shippingAddress,
                ':tid' => $tonInvoiceId,
                ':sid' => $stripeSessionId,
            ]);
            $orderId = (int)$this->pdo->lastInsertId();
            $oi = $this->pdo->prepare('INSERT INTO order_items(order_id, product_id, quantity, price_fiat, size, color) VALUES(:oid,:pid,:q,:price,:size,:color)');
            foreach ($cartItems as $item) {
                $oi->execute([
                    ':oid' => $orderId,
                    ':pid' => (int)$item['product_id'] ?? (int)$item['productId'],
                    ':q' => (int)$item['quantity'],
                    ':price' => (float)$item['price'],
                    ':size' => $item['size'] ?? ($item['variant']['size'] ?? null),
                    ':color' => $item['color'] ?? ($item['variant']['color'] ?? null),
                ]);
            }
            $this->pdo->commit();
            return $orderId;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function markPaidByStripeSession(string $sessionId): void
    {
        $this->pdo->prepare("UPDATE orders SET status='paid' WHERE stripe_session_id = :sid")
            ->execute([':sid' => $sessionId]);
    }

    public function markPaidByTonInvoice(string $invoiceId, string $txHash): void
    {
        $stmt = $this->pdo->prepare("UPDATE orders SET status='paid', ton_tx_hash = :tx WHERE ton_invoice_id = :iid");
        $stmt->execute([':iid' => $invoiceId, ':tx' => $txHash]);
    }

    public function findByTonInvoice(string $invoiceId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM orders WHERE ton_invoice_id = :iid');
        $stmt->execute([':iid' => $invoiceId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByStripeSession(string $sessionId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM orders WHERE stripe_session_id = :sid');
        $stmt->execute([':sid' => $sessionId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}