<?php
declare(strict_types=1);

namespace App\API\Modules\students;

use App\API\Controllers\BaseController;

class StudentParentService
{
    private const PARENT_ACCESS_PERMS = ['students_parents_view', 'students_parents_view_all', 'students_parents_view_own', 'students_view', 'students_view_all', 'students_view_own', 'students_edit', 'students_create', 'admission_view', 'finance_view'];
    private const STUDENT_EDIT_PERMS = ['students_edit'];
    private const STUDENT_CREATE_PERMS = ['students_create'];

    private StudentsAPI $api;
    private FamilyGroupsManager $familyGroupsManager;

    public function __construct(StudentsAPI $api, FamilyGroupsManager $familyGroupsManager)
    {
        $this->api = $api;
        $this->familyGroupsManager = $familyGroupsManager;
    }

    public function getParentsGet($id, $data, $segments, BaseController $controller)
    {
        if ($auth = $controller->authorizeStudents(self::PARENT_ACCESS_PERMS, 'Insufficient permission to view parent records')) {
            return $auth;
        }

        $parentId = $data['parent_id'] ?? null;
        if ($parentId !== null) {
            return $controller->handleResponse($this->familyGroupsManager->getParentDetails((int) $parentId));
        }

        $studentId = $id ?? $data['student_id'] ?? null;
        if ($studentId === null) {
            return $controller->badRequest('Student ID is required');
        }

        $result = $this->api->getStudentParentsInfo($studentId);
        return $controller->handleResponse($result);
    }

    
    
    
    
    
    
    
    
    
    
    
    public function getFamilyParentGet($id, $data, $segments, BaseController $controller) {
        if ($auth = $controller->authorizeStudents(self::PARENT_ACCESS_PERMS, 'Insufficient permission to view parent records')) { return $auth; }
        if (!$id) { return $controller->badRequest('Parent ID required'); }
        return $controller->handleResponse($this->familyGroupsManager->getParentDetails((int) $id));
    }

    public function putFamilyParentUpdate($id, $data, $segments, BaseController $controller) {
        if ($auth = $controller->authorizeStudents(self::STUDENT_EDIT_PERMS, 'Insufficient permission to update parent records')) { return $auth; }
        if (!$id) { return $controller->badRequest('Parent ID required'); }
        return $controller->handleResponse($this->familyGroupsManager->updateParent((int) $id, $data));
    }

    public function getFamilyGroupsMetaV2($id, $data, $segments, BaseController $controller) {
        if (!$controller->getUser()) { return $controller->unauthorized('Authentication required'); }
        return $controller->handleResponse($this->familyGroupsManager->getFamilyGroupsMeta());
    }

    public function getFamilyGroupsV2($id, $data, $segments, BaseController $controller) {
        if (!$controller->getUser()) { return $controller->unauthorized('Authentication required'); }
        return $controller->handleResponse($this->familyGroupsManager->getFamilyGroups(array_merge($_GET, $data)));
    }

    public function getFamilyGroupsStats($id, $data, $segments, BaseController $controller) {
        if ($auth = $controller->authorizeStudents(self::PARENT_ACCESS_PERMS, 'Insufficient permission to view family group statistics')) {
            return $auth;
        }
        return $controller->handleResponse($this->familyGroupsManager->getFamilyGroupStats());
    }

    public function getFamilyGroup($id, $data, $segments, BaseController $controller) {
        if (!$controller->getUser()) { return $controller->unauthorized('Authentication required'); }
        $parentId = $id !== null ? (int)$id : null;
        if ($parentId === null) { return $controller->badRequest('Parent ID is required'); }
        $result = $this->familyGroupsManager->getParentDetails($parentId);
        if (is_array($result) && ($result['success'] ?? false)) {
            $result['data'] = ['parent' => $result['data']['parent'] ?? null, 'students' => $result['data']['children'] ?? []];
        }
        return $controller->handleResponse($result);
    }

    public function postFamilyGroupLinkStudent($id, $data, $segments, BaseController $controller) {
        if (!$controller->getUser()) { return $controller->unauthorized('Authentication required'); }
        $parentId = $id !== null ? (int)$id : null;
        if ($parentId === null) { return $controller->badRequest('Parent ID is required'); }
        return $controller->handleResponse($this->familyGroupsManager->linkStudentToFamilyGroup($parentId, $data));
    }
}
