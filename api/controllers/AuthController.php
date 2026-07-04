<?php
namespace App\API\Controllers;

use App\API\Modules\auth\AuthAPI;
use Exception;

class AuthController extends BaseController
{
    private $api;

    public function __construct()
    {
        parent::__construct();
        $this->api = new AuthAPI();
    }

    public function index()
    {
        return $this->success(['message' => 'Auth API is running']);
    }

    // POST /api/auth/login
    // POST /api/auth/login
    public function postLogin($id = null, $data = [], $segments = [])
    {
        $result = $this->api->login($data);
        return $this->handleResponse($result);
    }

    // POST /api/auth/logout
    public function postLogout($id = null, $data = [], $segments = [])
    {
        $result = $this->api->logout($data);
        return $this->handleResponse($result);
    }

    // POST /api/auth/forgot-password
    public function postForgotPassword($id = null, $data = [], $segments = [])
    {
        $result = $this->api->forgotPassword($data);
        return $this->handleResponse($result);
    }

    // GET /api/auth/reset-password
    public function getResetPassword($id = null, $data = [], $segments = [])
    {
        $result = $this->api->verifyResetToken($data);
        return $this->handleResponse($result);
    }

    // POST /api/auth/reset-password
    public function postResetPassword($id = null, $data = [], $segments = [])
    {
        $result = $this->api->resetPassword($data);
        return $this->handleResponse($result);
    }

    // POST /api/auth/refresh-token
    public function postRefreshToken($id = null, $data = [], $segments = [])
    {
        $result = $this->api->exchangeRefreshToken($data);
        return $this->handleResponse($result);
    }

    // POST /api/auth/logout
    // Revoke refresh token on logout
    public function postLogoutRefresh($id = null, $data = [], $segments = [])
    {
        $result = $this->api->revokeRefreshToken($data);
        return $this->handleResponse($result);
    }
}
