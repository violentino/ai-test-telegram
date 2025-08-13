<?php
namespace App\Repositories;

use App\Database\Connection;
use PDO;

class ProductRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Connection::getPdo();
    }

    public function list(array $filters = []): array
    {
        $sql = "SELECT p.* FROM products p";
        $where = [];
        $params = [];
        if (!empty($filters['category'])) { $where[] = 'p.category = :category'; $params[':category'] = $filters['category']; }
        if (!empty($filters['gender'])) { $where[] = 'p.gender = :gender'; $params[':gender'] = $filters['gender']; }
        if (!empty($filters['size']) || !empty($filters['color'])) {
            $sql .= " JOIN product_variants v ON v.product_id = p.id";
            if (!empty($filters['size'])) { $where[] = 'v.size = :size'; $params[':size'] = $filters['size']; }
            if (!empty($filters['color'])) { $where[] = 'v.color = :color'; $params[':color'] = $filters['color']; }
        }
        if ($where) { $sql .= ' WHERE ' . implode(' AND ', $where); }
        $sql .= ' GROUP BY p.id ORDER BY p.id DESC LIMIT 200';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        return array_map(fn($r) => $this->hydrate($r), $rows);
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM products WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if (!$row) return null;
        return $this->hydrate($row);
    }

    private function hydrate(array $row): array
    {
        $images = $this->fetchImages((int)$row['id']);
        $variants = $this->fetchVariants((int)$row['id']);
        $sizes = array_values(array_unique(array_filter(array_map(fn($v) => $v['size'], $variants))));
        $colors = array_values(array_unique(array_filter(array_map(fn($v) => $v['color'], $variants))));
        return [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'description' => $row['description'],
            'category' => $row['category'],
            'gender' => $row['gender'],
            'price' => (float)$row['price'],
            'currency' => $row['currency'],
            'images' => $images,
            'sizes' => $sizes,
            'colors' => $colors,
            'characteristics' => [],
        ];
    }

    private function fetchImages(int $productId): array
    {
        $stmt = $this->pdo->prepare('SELECT url FROM product_images WHERE product_id = :pid ORDER BY sort_order ASC, id ASC');
        $stmt->execute([':pid' => $productId]);
        return array_map(fn($r) => $r['url'], $stmt->fetchAll());
    }

    private function fetchVariants(int $productId): array
    {
        $stmt = $this->pdo->prepare('SELECT size, color, stock FROM product_variants WHERE product_id = :pid');
        $stmt->execute([':pid' => $productId]);
        return $stmt->fetchAll();
    }

    public function create(array $data, array $images, array $variants): int
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('INSERT INTO products(name, description, category, gender, price, currency) VALUES(:name,:description,:category,:gender,:price,:currency)');
            $stmt->execute([
                ':name' => $data['name'],
                ':description' => $data['description'] ?? null,
                ':category' => $data['category'],
                ':gender' => $data['gender'],
                ':price' => $data['price'],
                ':currency' => $data['currency'] ?? ($_ENV['CURRENCY'] ?? 'USD'),
            ]);
            $productId = (int)$this->pdo->lastInsertId();
            $this->saveImages($productId, $images);
            $this->saveVariants($productId, $variants);
            $this->pdo->commit();
            return $productId;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function update(int $id, array $data, ?array $images = null, ?array $variants = null): void
    {
        $stmt = $this->pdo->prepare('UPDATE products SET name=:name, description=:description, category=:category, gender=:gender, price=:price, currency=:currency WHERE id=:id');
        $stmt->execute([
            ':id' => $id,
            ':name' => $data['name'],
            ':description' => $data['description'] ?? null,
            ':category' => $data['category'],
            ':gender' => $data['gender'],
            ':price' => $data['price'],
            ':currency' => $data['currency'] ?? ($_ENV['CURRENCY'] ?? 'USD'),
        ]);
        if ($images !== null) {
            $this->pdo->prepare('DELETE FROM product_images WHERE product_id = :id')->execute([':id' => $id]);
            $this->saveImages($id, $images);
        }
        if ($variants !== null) {
            $this->pdo->prepare('DELETE FROM product_variants WHERE product_id = :id')->execute([':id' => $id]);
            $this->saveVariants($id, $variants);
        }
    }

    public function delete(int $id): void
    {
        $this->pdo->prepare('DELETE FROM products WHERE id=:id')->execute([':id' => $id]);
    }

    private function saveImages(int $productId, array $images): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO product_images(product_id, url, sort_order) VALUES(:pid,:url,:ord)');
        $order = 0;
        foreach ($images as $url) {
            $stmt->execute([':pid' => $productId, ':url' => $url, ':ord' => $order++]);
        }
    }

    private function saveVariants(int $productId, array $variants): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO product_variants(product_id, size, color, stock) VALUES(:pid,:size,:color,:stock)');
        foreach ($variants as $v) {
            $stmt->execute([
                ':pid' => $productId,
                ':size' => $v['size'] ?? null,
                ':color' => $v['color'] ?? null,
                ':stock' => (int)($v['stock'] ?? 0),
            ]);
        }
    }
}