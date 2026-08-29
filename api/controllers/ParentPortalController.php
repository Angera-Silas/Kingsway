<?php
declare(strict_types=1);

namespace App\API\Controllers;

use App\API\Modules\parent\ParentPortalManager;
use App\Database\Database;
use App\API\Services\payments\UniformCatalogService;

/**
 * ParentPortalController — thin endpoint exposer for the parent portal.
 *
 * All data access, session/OTP decisions, fee/report-card/attendance reads and
 * messaging orchestration live in ParentPortalManager (normalised schema only).
 * This controller only: reads input, validates required fields, delegates and
 * formats the response via handleApiResponse().
 *
 * Uses ParentAuthMiddleware instead of staff JWT auth; the middleware sets
 * $_SERVER['parent_auth'] (parent_id/user_id/session_id/session_token).
 *
 * ROUTES (all under /api/parent-portal/):
 * POST /api/parent-portal/login                    → postLogin()
 * POST /api/parent-portal/login-otp-request        → postLoginOtpRequest()
 * POST /api/parent-portal/login-otp-verify         → postLoginOtpVerify()
 * POST /api/parent-portal/logout                   → postLogout()
 * GET  /api/parent-portal/dashboard                → getDashboard()
 * GET  /api/parent-portal/student-fees/{id}        → getStudentFees($id)
 * GET  /api/parent-portal/student-payment-history/{id} → getStudentPaymentHistory($id)
 * GET  /api/parent-portal/student-statement/{id}   → getStudentStatement($id)
 * GET  /api/parent-portal/fee-balance/{id}         → getFeeBalance($id)
 */
class ParentPortalController extends BaseController
{
    private ParentPortalManager $parent;

    public function __construct()
    {
        parent::__construct();
        $this->parent = new ParentPortalManager();
    }

    // ============================================================
    // AUTH ENDPOINTS (no ParentAuthMiddleware required)
    // ============================================================

    /**
     * POST /api/parent-portal/login
     * Body: {email, password}
     */
    public function postLogin($id = null, $data = [], $segments = [])
    {
        return $this->handleApiResponse($this->parent->postLogin($data));
    }

    /**
     * POST /api/parent-portal/login-otp-request
     * Body: {phone}
     */
    public function postLoginOtpRequest($id = null, $data = [], $segments = [])
    {
        return $this->handleApiResponse($this->parent->postLoginOtpRequest($data));
    }

    /**
     * POST /api/parent-portal/login-otp-verify
     * Body: {otp_session_id, otp_code}
     */
    public function postLoginOtpVerify($id = null, $data = [], $segments = [])
    {
        return $this->handleApiResponse($this->parent->postLoginOtpVerify($data));
    }

    /**
     * POST /api/parent-portal/logout
     */
    public function postLogout($id = null, $data = [], $segments = [])
    {
        return $this->handleApiResponse($this->parent->postLogout());
    }

    // ============================================================
    // AUTHENTICATED ENDPOINTS (require ParentAuthMiddleware)
    // ============================================================

    /**
     * GET /api/parent-portal/dashboard
     */
    public function getDashboard($id = null, $data = [], $segments = [])
    {
        return $this->handleApiResponse($this->parent->getDashboard());
    }

    /** GET /api/parent-portal/community */
    public function getCommunity($id = null, $data = [], $segments = [])
    { return $this->handleApiResponse($this->parent->getCommunity()); }

    /** GET /api/parent-portal/uniform-catalog */
    public function getUniformCatalog($id = null, $data = [], $segments = [])
    { return $this->handleApiResponse(['success'=>true,'data'=>['products'=>(new UniformCatalogService(Database::getInstance()->getConnection()))->list($data)]]); }

    /** GET /api/parent-portal/uniform-cart */
    public function getUniformCart($id = null, $data = [], $segments = [])
    { $parentId=(int)(($_SERVER['parent_auth']['parent_id']??0));return $this->handleApiResponse(['success'=>true,'data'=>(new UniformCatalogService(Database::getInstance()->getConnection()))->cart($parentId)]); }

    /** POST /api/parent-portal/uniform-cart */
    public function postUniformCart($id = null, $data = [], $segments = [])
    { try{$parentId=(int)(($_SERVER['parent_auth']['parent_id']??0));return $this->handleApiResponse(['success'=>true,'data'=>(new UniformCatalogService(Database::getInstance()->getConnection()))->addToCart($parentId,(int)($data['product_id']??0),(int)($data['size_id']??0),(int)($data['quantity']??0))]);}catch(\Throwable $e){return $this->badRequest($e->getMessage());} }

    /** POST /api/parent-portal/uniform-wishlist */
    public function postUniformWishlist($id = null, $data = [], $segments = [])
    { try{$parentId=(int)(($_SERVER['parent_auth']['parent_id']??0));return $this->handleApiResponse(['success'=>true,'data'=>(new UniformCatalogService(Database::getInstance()->getConnection()))->wishlist($parentId,(int)($data['product_id']??0))]);}catch(\Throwable $e){return $this->badRequest($e->getMessage());} }

    /** POST /api/parent-portal/uniform-checkout-payment */
    public function postUniformCheckoutPayment($id = null, $data = [], $segments = [])
    { try{$data['parent_id']=(int)(($_SERVER['parent_auth']['parent_id']??0));$service=new \App\API\Services\payments\UniformPaymentService(Database::getInstance()->getConnection());return $this->handleApiResponse(['success'=>true,'data'=>$service->initiateAccumulated($data,(int)($data['parent_id']??0))]);}catch(\Throwable $e){return $this->badRequest($e->getMessage());} }

    /**
     * GET /api/parent-portal/student-fees/{id}
     */
    public function getStudentFees($id = null, $data = [], $segments = [])
    {
        if (!$id) {
            return $this->badRequest('student_id required');
        }
        return $this->handleApiResponse($this->parent->getStudentFees((int)$id));
    }

    /**
     * GET /api/parent-portal/student-payment-history/{id}
     */
    public function getStudentPaymentHistory($id = null, $data = [], $segments = [])
    {
        if (!$id) {
            return $this->badRequest('student_id required');
        }
        return $this->handleApiResponse($this->parent->getStudentPaymentHistory((int)$id));
    }

    /**
     * GET /api/parent-portal/student-statement/{id}
     */
    public function getStudentStatement($id = null, $data = [], $segments = [])
    {
        if (!$id) {
            return $this->badRequest('student_id required');
        }
        return $this->handleApiResponse($this->parent->getStudentStatement((int)$id));
    }

    /**
     * GET /api/parent-portal/fee-balance/{id}
     */
    public function getFeeBalance($id = null, $data = [], $segments = [])
    {
        if (!$id) {
            return $this->badRequest('student_id required');
        }
        return $this->handleApiResponse($this->parent->getFeeBalance((int)$id));
    }

    /**
     * GET /api/parent-portal/student-attendance/{id}
     */
    public function getStudentAttendance($id = null, $data = [], $segments = [])
    {
        if (!$id) {
            return $this->badRequest('student_id required');
        }
        return $this->handleApiResponse($this->parent->getStudentAttendance((int)$id));
    }

    /** GET /api/parent-portal/student-transport/{id} */
    public function getStudentTransport($id = null, $data = [], $segments = [])
    {
        if (!$id) return $this->badRequest('student_id required');
        return $this->handleApiResponse($this->parent->getStudentTransport((int) $id));
    }

    /**
     * GET /api/parent-portal/student-performance/{id}
     */
    public function getStudentPerformance($id = null, $data = [], $segments = [])
    {
        if (!$id) {
            return $this->badRequest('student_id required');
        }
        return $this->handleApiResponse($this->parent->getStudentPerformance((int)$id));
    }

    /**
     * GET /api/parent-portal/student-report-card/{id}
     */
    public function getStudentReportCard($id = null, $data = [], $segments = [])
    {
        if (!$id) {
            return $this->badRequest('student_id required');
        }
        return $this->handleApiResponse($this->parent->getStudentReportCard((int)$id));
    }

    /** GET /api/parent-portal/student-learning-plan/{id} */
    public function getStudentLearningPlan($id = null, $data = [], $segments = [])
    {
        if (!$id) return $this->badRequest('student_id required');
        return $this->handleApiResponse($this->parent->getStudentLearningPlan((int)$id));
    }

    /**
     * GET /api/parent-portal/messages/{studentId?}
     */
    public function getMessages($id = null, $data = [], $segments = [])
    {
        $studentId = $id ? (int)$id : null;
        return $this->handleApiResponse($this->parent->getMessages($studentId));
    }

    /**
     * POST /api/parent-portal/send-message
     * Body: {student_id, subject, message}
     */
    public function postSendMessage($id = null, $data = [], $segments = [])
    {
        return $this->handleApiResponse($this->parent->postSendMessage($data));
    }

    /**
     * GET /api/parent-portal/portfolio/{studentId}
     */
    public function getPortfolio($id = null, $data = [], $segments = [])
    {
        if (!$id) {
            return $this->badRequest('student_id required');
        }
        return $this->handleApiResponse($this->parent->getPortfolio((int)$id));
    }

    /**
     * GET /api/parent-portal/grading-scale
     */
    public function getGradingScale($id = null, $data = [], $segments = [])
    {
        return $this->handleApiResponse($this->parent->getGradingScale());
    }

    /**
     * POST /api/parent-portal/initiate-mpesa-payment
     * Body: {student_id, phone?, amount?}
     */
    public function postInitiateMpesaPayment($id = null, $data = [], $segments = [])
    {
        return $this->handleApiResponse($this->parent->postInitiateMpesaPayment($data));
    }

    /**
     * GET /api/parent-portal/mpesa-status/{checkoutRequestId}
     */
    public function getMpesaStatus($id = null, $data = [], $segments = [])
    {
        $checkoutId = $id ?? ($data['checkout_request_id'] ?? '');
        if (!$checkoutId) {
            return $this->badRequest('checkout_request_id required');
        }
        return $this->handleApiResponse($this->parent->getMpesaStatus((string)$checkoutId));
    }
}
