<?php
declare(strict_types=1);

namespace App\API\Services\payments;

use PDO;
use RuntimeException;

/** Uniform store catalogue and parent checkout preparation. */
final class UniformCatalogService
{
    private PDO $db;
    public function __construct(PDO $db) { $this->db = $db; }

    public function list(array $filters = []): array
    {
        $where = "p.status='active' AND p.published=1";
        $args = [];
        if (!empty($filters['staff'])) $where = "1=1"; // internal view shows every status (draft/active/archived) so managers can re-publish
        if (!empty($filters['q'])) { $where .= ' AND (p.title LIKE ? OR i.description LIKE ?)'; $q='%'.trim((string)$filters['q']).'%'; $args[]=$q; $args[]=$q; }
        $s=$this->db->prepare("SELECT p.id,p.item_id,p.slug,p.title,p.description,p.status,p.published,i.code,im.url AS image_url FROM uniform_catalog_products p JOIN inventory_items i ON i.id=p.item_id LEFT JOIN uniform_catalog_images im ON im.product_id=p.id AND im.is_primary=1 WHERE {$where} ORDER BY p.title");
        $s->execute($args); $rows=$s->fetchAll(PDO::FETCH_ASSOC);
        $sizes=$this->db->query("SELECT p.id AS product_id,us.id AS size_id,us.size,us.size_label,us.size_type,us.unit_price,us.quantity_available-us.quantity_reserved AS available FROM uniform_catalog_products p JOIN uniform_sizes us ON us.item_id=p.item_id WHERE us.quantity_available>us.quantity_reserved ORDER BY us.size")->fetchAll(PDO::FETCH_ASSOC);
        $by=[]; foreach($sizes as $size)$by[(int)$size['product_id']][]=$size;
        foreach($rows as &$row)$row['sizes']=$by[(int)$row['id']]??[]; unset($row); return $rows;
    }

    public function saveProduct(array $data, int $userId): array
    {
        $itemId=(int)($data['item_id']??0); $title=trim((string)($data['title']??''));
        if(!$itemId||$title==='')throw new RuntimeException('item_id and title are required');
        $slug=trim((string)($data['slug']??'')) ?: strtolower(preg_replace('/[^a-z0-9]+/i','-', $title)).'-'.$itemId;
        $s=$this->db->prepare("INSERT INTO uniform_catalog_products(item_id,slug,title,description,published,status,created_by) VALUES(?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE title=VALUES(title),description=VALUES(description),published=VALUES(published),status=VALUES(status)");
        $s->execute([$itemId,$slug,$title,$data['description']??null,!empty($data['published'])?1:0,$data['status']??'draft',$userId]);
        $id=(int)$this->db->lastInsertId(); if(!$id){$q=$this->db->prepare('SELECT id FROM uniform_catalog_products WHERE item_id=?');$q->execute([$itemId]);$id=(int)$q->fetchColumn();} return ['id'=>$id,'product'=>$this->get($id)];
    }

    public function addImage(int $productId, string $url, ?string $alt=null, bool $primary=false): array
    { if(!$productId||$url==='')throw new RuntimeException('product_id and image URL are required'); $this->db->beginTransaction(); try { if($primary)$this->db->prepare('UPDATE uniform_catalog_images SET is_primary=0 WHERE product_id=?')->execute([$productId]);$s=$this->db->prepare('INSERT INTO uniform_catalog_images(product_id,url,alt_text,is_primary) VALUES(?,?,?,?)');$s->execute([$productId,$url,$alt,$primary?1:0]);$this->db->commit();return ['id'=>(int)$this->db->lastInsertId(),'url'=>$url]; }catch(\Throwable $e){$this->db->rollBack();throw $e;} }

    public function get(int $id): array { $s=$this->db->prepare('SELECT * FROM uniform_catalog_products WHERE id=?');$s->execute([$id]);return $s->fetch(PDO::FETCH_ASSOC)?:[]; }

    public function wishlist(int $parentId, int $productId): array
    { if(!$parentId||!$productId)throw new RuntimeException('parent_id and product_id are required');$this->db->prepare('INSERT INTO uniform_catalog_wishlists(parent_id,product_id) VALUES(?,?) ON DUPLICATE KEY UPDATE created_at=created_at')->execute([$parentId,$productId]);return ['parent_id'=>$parentId,'product_id'=>$productId]; }

    public function addToCart(int $parentId, int $productId, int $sizeId, int $quantity): array
    { if(!$parentId||!$productId||!$sizeId||$quantity<1)throw new RuntimeException('product, size and positive quantity are required');$check=$this->db->prepare("SELECT p.id FROM uniform_catalog_products p JOIN uniform_sizes s ON s.item_id=p.item_id WHERE p.id=? AND s.id=? AND p.status='active' AND p.published=1 AND s.quantity_available-s.quantity_reserved>=?");$check->execute([$productId,$sizeId,$quantity]);if(!$check->fetchColumn())throw new RuntimeException('Selected uniform size is unavailable');$c=$this->db->prepare("SELECT id FROM uniform_catalog_carts WHERE parent_id=? AND status='open' ORDER BY id DESC LIMIT 1");$c->execute([$parentId]);$cart=(int)$c->fetchColumn();if(!$cart){$this->db->prepare("INSERT INTO uniform_catalog_carts(parent_id) VALUES(?)")->execute([$parentId]);$cart=(int)$this->db->lastInsertId();}$this->db->prepare('INSERT INTO uniform_catalog_cart_items(cart_id,product_id,size_id,quantity) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE quantity=quantity+VALUES(quantity),updated_at=NOW()')->execute([$cart,$productId,$sizeId,$quantity]);return $this->cart($parentId); }

    public function cart(int $parentId): array
    { $c=$this->db->prepare("SELECT id FROM uniform_catalog_carts WHERE parent_id=? AND status='open' ORDER BY id DESC LIMIT 1");$c->execute([$parentId]);$id=(int)$c->fetchColumn();if(!$id)return ['cart_id'=>null,'items'=>[],'total'=>0.0];$s=$this->db->prepare('SELECT ci.*,p.title,s.size,s.size_label,s.unit_price,(ci.quantity*s.unit_price) line_total FROM uniform_catalog_cart_items ci JOIN uniform_catalog_products p ON p.id=ci.product_id JOIN uniform_sizes s ON s.id=ci.size_id WHERE ci.cart_id=?');$s->execute([$id]);$items=$s->fetchAll(PDO::FETCH_ASSOC);$total=0.0;foreach($items as $x)$total+=(float)$x['line_total'];return ['cart_id'=>$id,'items'=>$items,'total'=>$total]; }
}
