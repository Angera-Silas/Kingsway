<?php
namespace App\API\Controllers;

use App\API\Services\catalog\CatalogCommerceService;
use App\Database\Database;

/** Authenticated staff catalogue shopping and authorised store oversight. */
final class CommerceController extends BaseController
{
    private CatalogCommerceService $commerce;
    public function __construct(){parent::__construct();$this->commerce=new CatalogCommerceService(Database::getInstance()->getConnection());}
    private function staffId(): int { $id=(int)($this->user['id']??$this->user['user_id']??0);if(!$id)throw new \RuntimeException('Authentication required');return $id; }
    private function manager(): ?array { if(!$this->userHasAny([], [3,4,14], ['director','school administrator','uniform store manager']))return $this->forbidden('Catalogue sales oversight is restricted');return null; }
    private function storeManager(): ?array { if(!$this->userHasAny([], [4,14], ['school administrator','uniform store manager']))return $this->forbidden('Uniform Store management is required');return null; }

    public function getProduct($id=null,$data=[],$segments=[]){if(!$id)return $this->badRequest('Product ID is required');return $this->success($this->commerce->product((int)$id,'staff'));}
    public function getCart($id=null,$data=[],$segments=[]){return $this->success($this->commerce->cart('staff',$this->staffId()));}
    public function getPaymentOptions($id=null,$data=[],$segments=[]){return $this->success(['options'=>$this->commerce->paymentOptions(false)]);}
    public function postCart($id=null,$data=[],$segments=[]){try{return $this->success($this->commerce->addCart('staff',$this->staffId(),$data),'Added to cart');}catch(\Throwable $e){return $this->badRequest($e->getMessage());}}
    public function putCart($id=null,$data=[],$segments=[]){try{return $this->success($this->commerce->updateCart('staff',$this->staffId(),(int)$id,(int)($data['quantity']??0)),'Cart updated');}catch(\Throwable $e){return $this->badRequest($e->getMessage());}}
    public function deleteCart($id=null,$data=[],$segments=[]){try{return $this->success($this->commerce->removeCart('staff',$this->staffId(),(int)$id),'Item removed');}catch(\Throwable $e){return $this->badRequest($e->getMessage());}}
    public function getWishlist($id=null,$data=[],$segments=[]){return $this->success(['items'=>$this->commerce->wishlist('staff',$this->staffId())]);}
    public function postWishlist($id=null,$data=[],$segments=[]){try{return $this->success(['items'=>$this->commerce->addWishlist('staff',$this->staffId(),(int)($data['product_id']??0))],'Saved to wishlist');}catch(\Throwable $e){return $this->badRequest($e->getMessage());}}
    public function deleteWishlist($id=null,$data=[],$segments=[]){return $this->success(['items'=>$this->commerce->removeWishlist('staff',$this->staffId(),(int)$id)],'Removed from wishlist');}
    public function postWishlistMove($id=null,$data=[],$segments=[]){try{$product=(int)($data['product_id']??0);$cart=$this->commerce->addCart('staff',$this->staffId(),$data);$this->commerce->removeWishlist('staff',$this->staffId(),$product);return $this->success($cart,'Moved to cart');}catch(\Throwable $e){return $this->badRequest($e->getMessage());}}
    public function postCheckout($id=null,$data=[],$segments=[]){try{return $this->created($this->commerce->checkout('staff',$this->staffId(),$data,$this->staffId()),'Order placed');}catch(\Throwable $e){return $this->badRequest($e->getMessage());}}
    public function getOrders($id=null,$data=[],$segments=[]){return $this->success(['orders'=>$this->commerce->orders('staff',$this->staffId())]);}
    public function deleteOrders($id=null,$data=[],$segments=[]){try{return $this->success($this->commerce->cancel((int)$id,'staff',$this->staffId(),$this->staffId()),'Order cancelled');}catch(\Throwable $e){return $this->badRequest($e->getMessage());}}
    public function postOrderPaymentRetry($id=null,$data=[],$segments=[]){try{return $this->success($this->commerce->retryPayment((int)$id,'staff',$this->staffId(),$data,$this->staffId()),'Payment request restarted');}catch(\Throwable $e){return $this->badRequest($e->getMessage());}}
    public function postReviews($id=null,$data=[],$segments=[]){try{return $this->created($this->commerce->saveReview('staff',$this->staffId(),$data),'Review submitted for moderation');}catch(\Throwable $e){return $this->badRequest($e->getMessage());}}
    public function getManagement($id=null,$data=[],$segments=[]){if($g=$this->manager())return $g;return $this->success($this->commerce->management($data));}
    public function getPointOfSaleOptions($id=null,$data=[],$segments=[]){if($g=$this->storeManager())return $g;return $this->success($this->commerce->pointOfSaleOptions());}
    public function postPointOfSale($id=null,$data=[],$segments=[]){if($g=$this->storeManager())return $g;try{return $this->created($this->commerce->pointOfSale($data,$this->staffId()),'Counter sale completed');}catch(\Throwable $e){return $this->badRequest($e->getMessage());}}
    public function putOrdersStatus($id=null,$data=[],$segments=[]){if($g=$this->storeManager())return $g;try{return $this->success($this->commerce->updateOrderStatus((int)$id,(string)($data['status']??''),$this->staffId(),$data['note']??null),'Order updated');}catch(\Throwable $e){return $this->badRequest($e->getMessage());}}
    public function putReviewsModerate($id=null,$data=[],$segments=[]){if($g=$this->storeManager())return $g;try{return $this->success($this->commerce->moderateReview((int)$id,(string)($data['status']??''),$this->staffId()),'Review moderated');}catch(\Throwable $e){return $this->badRequest($e->getMessage());}}
}
