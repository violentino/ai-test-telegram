<?php
namespace App\Repositories;

use App\Database\Connection;
use PDO;

class CartRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Connection::getPdo();
    }

    private function getOrCreateCartId(int $tgUserId): int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM carts WHERE tg_user_id = :uid');
        $stmt->execute([':uid' => $tgUserId]);
        $id = $stmt->fetchColumn();
        if ($id) return (int)$id;
        $this->pdo->prepare('INSERT INTO carts(tg_user_id) VALUES(:uid)')->execute([':uid' => $tgUserId]);
        return (int)$this->pdo->lastInsertId();
    }

    public function addItem(int $tgUserId, int $productId, int $quantity, ?string $size, ?string $color): void
    {
        $cartId = $this->getOrCreateCartId($tgUserId);
        $stmt = $this->pdo->prepare('SELECT id, quantity FROM cart_items WHERE cart_id=:cid AND product_id=:pid AND size <=> :size AND color <=> :color');
        $stmt->execute([':cid' => $cartId, ':pid' => $productId, ':size' => $size, ':color' => $color]);
        $row = $stmt->fetch();
        if ($row) {
            $newQty = (int)$row['quantity'] + $quantity;
            $this->pdo->prepare('UPDATE cart_items SET quantity=:q WHERE id=:id')->execute([':q' => $newQty, ':id' => (int)$row['id']]);
        } else {
            $this->pdo->prepare('INSERT INTO cart_items(cart_id, product_id, quantity, size, color) VALUES(:cid,:pid,:q,:size,:color)')->execute([
                ':cid' => $cartId, ':pid' => $productId, ':q' => $quantity, ':size' => $size, ':color' => $color
            ]);
        }
    }

    public function removeProduct(int $tgUserId, int $productId): void
    {
        $stmt = $this->pdo->prepare('SELECT id FROM carts WHERE tg_user_id = :uid');
        $stmt->execute([':uid' => $tgUserId]);
        $cartId = $stmt->fetchColumn();
        if ($cartId) {
            $this->pdo->prepare('DELETE FROM cart_items WHERE cart_id=:cid AND product_id=:pid')->execute([':cid' => (int)$cartId, ':pid' => $productId]);
        }
    }

    public function getItems(int $tgUserId): array
    {
        $stmt = $this->pdo->prepare('SELECT c.id as cart_id FROM carts c WHERE c.tg_user_id = :uid');
        $stmt->execute([':uid' => $tgUserId]);
        $cartId = $stmt->fetchColumn();
        if (!$cartId) return [];
        $stmt = $this->pdo->prepare('SELECT ci.*, p.name, p.price, p.currency FROM cart_items ci JOIN products p ON p.id = ci.product_id WHERE ci.cart_id = :cid');
        $stmt->execute([':cid' => (int)$cartId]);
        return $stmt->fetchAll();
    }

    public function clear(int $tgUserId): void
    {
        $stmt = $this->pdo->prepare('SELECT id FROM carts WHERE tg_user_id = :uid');
        $stmt->execute([':uid' => $tgUserId]);
        $cartId = $stmt->fetchColumn();
        if ($cartId) {
            $this->pdo->prepare('DELETE FROM cart_items WHERE cart_id=:cid')->execute([':cid' => (int)$cartId]);
        }
    }
}